<?php

namespace App\Services\Archives;

use App\Enums\ArchiveDispositionStatus;
use App\Enums\ArchiveRecordStatus;
use App\Models\ArchiveDispositionApproval;
use App\Models\ArchiveDispositionItem;
use App\Models\ArchiveDispositionRequest;
use App\Models\ArchiveRecord;
use App\Models\ArchiveRetentionEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchiveDispositionService
{
    public function __construct(
        private readonly ArchiveAuditService $audit,
        private readonly ArchiveHoldService $holds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $recordIds
     */
    public function propose(User $actor, array $data, array $recordIds): ArchiveDispositionRequest
    {
        if (! $actor->can('archives.proposer_elimination') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Seuls les agents habilités peuvent proposer un sort final.');
        }

        if ($recordIds === []) {
            throw new InvalidArgumentException('Au moins une archive est requise.');
        }

        return DB::transaction(function () use ($actor, $data, $recordIds) {
            $request = ArchiveDispositionRequest::query()->create([
                'reference' => 'SF-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'status' => ArchiveDispositionStatus::Proposition,
                'disposition_type' => $data['disposition_type'] ?? 'elimination',
                'structure_id' => (int) $data['structure_id'],
                'proposed_by' => $actor->id,
                'title' => $data['title'],
                'justification' => $data['justification'] ?? null,
                'legal_basis' => $data['legal_basis'] ?? null,
                'proposed_at' => now(),
            ]);

            foreach ($recordIds as $recordId) {
                $record = ArchiveRecord::query()->findOrFail($recordId);

                $status = 'inscrit';
                $notes = null;
                if ($this->holds->hasActiveHold($record)) {
                    $status = 'bloque_gel';
                    $notes = 'Gel actif — exclu de l’exécution tant que le gel n’est pas levé.';
                }

                ArchiveDispositionItem::query()->create([
                    'disposition_request_id' => $request->id,
                    'record_id' => $record->id,
                    'box_id' => $record->box_id,
                    'status' => $status,
                    'notes' => $notes,
                ]);

                if ($status === 'inscrit' && ($data['disposition_type'] ?? 'elimination') === 'elimination') {
                    $record->status = ArchiveRecordStatus::AEliminer;
                    $record->save();
                }

                ArchiveRetentionEvent::query()->create([
                    'record_id' => $record->id,
                    'rule_id' => $record->retention_rule_id,
                    'event_type' => 'disposition_proposed',
                    'actor_id' => $actor->id,
                    'payload' => ['disposition_request_id' => $request->id],
                ]);
            }

            $request->status = ArchiveDispositionStatus::EnValidation;
            $request->save();

            $this->audit->log('disposition.proposed', $request, $actor, [
                'items' => count($recordIds),
            ]);

            return $request->fresh(['items.record', 'proposer', 'structure']);
        });
    }

    public function authorize(User $actor, ArchiveDispositionRequest $request, ?string $pvReference = null, ?string $comment = null): ArchiveDispositionRequest
    {
        if (! $actor->can('archives.autoriser_elimination') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Autorisation réservée à une autorité distincte.');
        }

        if ((int) $actor->id === (int) $request->proposed_by && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Séparation des rôles : le proposant ne peut pas autoriser.');
        }

        $this->assertStatus($request, [ArchiveDispositionStatus::EnValidation, ArchiveDispositionStatus::Proposition]);

        return DB::transaction(function () use ($actor, $request, $pvReference, $comment) {
            ArchiveDispositionApproval::query()->create([
                'disposition_request_id' => $request->id,
                'approver_id' => $actor->id,
                'decision' => 'autorise',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $request->fill([
                'status' => ArchiveDispositionStatus::Autorisee,
                'authorized_by' => $actor->id,
                'authorized_at' => now(),
                'pv_reference' => $pvReference ?: ('PV-'.$request->reference),
            ])->save();

            $this->audit->log('disposition.authorized', $request, $actor, [
                'pv_reference' => $request->pv_reference,
            ]);

            return $request->fresh(['items.record', 'approvals', 'authorizer']);
        });
    }

    public function refuse(User $actor, ArchiveDispositionRequest $request, string $reason): ArchiveDispositionRequest
    {
        if (! $actor->can('archives.autoriser_elimination') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Refus réservé à une autorité habilitée.');
        }

        $this->assertStatus($request, [ArchiveDispositionStatus::EnValidation, ArchiveDispositionStatus::Proposition]);

        return DB::transaction(function () use ($actor, $request, $reason) {
            ArchiveDispositionApproval::query()->create([
                'disposition_request_id' => $request->id,
                'approver_id' => $actor->id,
                'decision' => 'refuse',
                'comment' => $reason,
                'decided_at' => now(),
            ]);

            $request->fill([
                'status' => ArchiveDispositionStatus::Refusee,
                'refused_at' => now(),
                'refusal_reason' => $reason,
                'authorized_by' => $actor->id,
            ])->save();

            foreach ($request->items as $item) {
                if ($item->record && $item->record->status === ArchiveRecordStatus::AEliminer) {
                    $item->record->status = ArchiveRecordStatus::Conserve;
                    $item->record->save();
                }
                $item->status = 'exclu';
                $item->save();
            }

            $this->audit->log('disposition.refused', $request, $actor, ['reason' => $reason]);

            return $request->fresh(['items.record', 'approvals']);
        });
    }

    public function execute(User $actor, ArchiveDispositionRequest $request): ArchiveDispositionRequest
    {
        if (! $actor->can('archives.executer_elimination') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Exécution réservée à un rôle distinct.');
        }

        if ((int) $actor->id === (int) $request->proposed_by && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Séparation des rôles : le proposant ne peut pas exécuter.');
        }

        if ((int) $actor->id === (int) $request->authorized_by && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Séparation des rôles : l’autorisant ne peut pas exécuter.');
        }

        $this->assertStatus($request, [ArchiveDispositionStatus::Autorisee]);

        return DB::transaction(function () use ($actor, $request) {
            $items = $request->items()->with('record.holds')->get();

            foreach ($items as $item) {
                if ($item->status === 'bloque_gel' || ($item->record && $this->holds->hasActiveHold($item->record))) {
                    $label = $item->record ? $item->record->permanent_id : 'une archive';
                    throw new InvalidArgumentException('Exécution refusée : gel actif sur '.$label.'.');
                }
            }

            foreach ($items as $item) {
                if ($request->disposition_type === 'elimination' && $item->record) {
                    $item->record->status = ArchiveRecordStatus::Elimine;
                    $item->record->save();
                }

                $item->status = 'execute';
                $item->save();
            }

            $request->fill([
                'status' => ArchiveDispositionStatus::Executee,
                'executed_by' => $actor->id,
                'executed_at' => now(),
                'evidence_path' => 'archives/disposition/'.$request->reference.'.json',
            ])->save();

            $this->audit->log('disposition.executed', $request, $actor, [
                'pv_reference' => $request->pv_reference,
                'evidence_path' => $request->evidence_path,
            ]);

            return $request->fresh(['items.record', 'executor', 'authorizer', 'proposer']);
        });
    }

    /**
     * @param  list<ArchiveDispositionStatus>  $allowed
     */
    private function assertStatus(ArchiveDispositionRequest $request, array $allowed): void
    {
        $status = $request->status instanceof ArchiveDispositionStatus
            ? $request->status
            : ArchiveDispositionStatus::from((string) $request->status);

        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Transition interdite depuis '.$status->value.'.');
        }
    }
}
