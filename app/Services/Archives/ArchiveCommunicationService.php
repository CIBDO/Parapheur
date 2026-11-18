<?php

namespace App\Services\Archives;

use App\Enums\ArchiveCommunicationStatus;
use App\Models\ArchiveAccessGrant;
use App\Models\ArchiveCommunicationRequest;
use App\Models\ArchiveRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchiveCommunicationService
{
    public function __construct(
        private readonly ArchiveAuditService $audit,
        private readonly ArchiveAccessService $access,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(User $actor, ArchiveRecord $record, array $data): ArchiveCommunicationRequest
    {
        if (! $this->access->canViewRecord($actor, $record) && ! $actor->can('archives.communiquer')) {
            // demande possible même si pas encore grant — mais pas pour hors structure sauf admin
            if (! $actor->can('admin.access') && (int) $actor->structure_id !== (int) $record->structure_id) {
                throw new InvalidArgumentException('Demande hors périmètre structure.');
            }
        }

        $request = ArchiveCommunicationRequest::query()->create([
            'reference' => 'COM-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
            'status' => ArchiveCommunicationStatus::Demande,
            'record_id' => $record->id,
            'requester_id' => $actor->id,
            'structure_id' => $record->structure_id,
            'purpose' => $data['purpose'] ?? null,
            'access_mode' => $data['access_mode'] ?? 'consultation',
        ]);

        $this->audit->log('communication.requested', $record, $actor, [
            'request_id' => $request->id,
        ]);

        return $request->load(['record', 'requester']);
    }

    public function authorize(User $actor, ArchiveCommunicationRequest $request, int $days = 30): ArchiveCommunicationRequest
    {
        if (! $actor->can('archives.communiquer') && ! $actor->can('archives.administrer') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Habilitation insuffisante pour autoriser une communication.');
        }

        $this->assertStatus($request, [ArchiveCommunicationStatus::Demande]);

        return DB::transaction(function () use ($actor, $request, $days) {
            $expires = now()->addDays(max(1, $days));

            $request->fill([
                'status' => ArchiveCommunicationStatus::Autorisee,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'expires_at' => $expires,
            ])->save();

            $ability = $request->access_mode === 'telechargement' ? 'download' : 'view';

            ArchiveAccessGrant::query()->create([
                'communication_request_id' => $request->id,
                'record_id' => $request->record_id,
                'user_id' => $request->requester_id,
                'ability' => $ability,
                'granted_by' => $actor->id,
                'granted_at' => now(),
                'expires_at' => $expires,
            ]);

            $this->audit->log('communication.authorized', $request->record, $actor, [
                'request_id' => $request->id,
                'expires_at' => $expires->toIso8601String(),
            ]);

            return $request->fresh(['record', 'requester', 'grants']);
        });
    }

    public function refuse(User $actor, ArchiveCommunicationRequest $request, string $reason): ArchiveCommunicationRequest
    {
        if (! $actor->can('archives.communiquer') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Habilitation insuffisante.');
        }

        $this->assertStatus($request, [ArchiveCommunicationStatus::Demande]);

        $request->fill([
            'status' => ArchiveCommunicationStatus::Refusee,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_notes' => $reason,
        ])->save();

        $this->audit->log('communication.refused', $request->record, $actor, [
            'request_id' => $request->id,
            'reason' => $reason,
        ]);

        return $request->fresh(['record', 'requester']);
    }

    public function revoke(User $actor, ArchiveCommunicationRequest $request): ArchiveCommunicationRequest
    {
        if (! $actor->can('archives.communiquer') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Habilitation insuffisante.');
        }

        return DB::transaction(function () use ($actor, $request) {
            $request->status = ArchiveCommunicationStatus::Revoquee;
            $request->save();

            $request->grants()->whereNull('revoked_at')->update([
                'revoked_at' => now(),
            ]);

            $this->audit->log('communication.revoked', $request->record, $actor, [
                'request_id' => $request->id,
            ]);

            return $request->fresh(['grants']);
        });
    }

    /**
     * @param  list<ArchiveCommunicationStatus>  $allowed
     */
    private function assertStatus(ArchiveCommunicationRequest $request, array $allowed): void
    {
        $status = $request->status instanceof ArchiveCommunicationStatus
            ? $request->status
            : ArchiveCommunicationStatus::from((string) $request->status);

        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Transition interdite depuis '.$status->value.'.');
        }
    }
}
