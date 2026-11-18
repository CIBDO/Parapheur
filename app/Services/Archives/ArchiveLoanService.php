<?php

namespace App\Services\Archives;

use App\Enums\ArchiveLoanStatus;
use App\Models\ArchiveBox;
use App\Models\ArchiveLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchiveLoanService
{
    public function __construct(
        private readonly ArchiveAuditService $audit,
        private readonly ArchiveMovementService $movements,
        private readonly ArchiveLocationService $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(User $actor, array $data): ArchiveLoan
    {
        $box = null;
        if (! empty($data['box_id'])) {
            $box = ArchiveBox::query()->findOrFail((int) $data['box_id']);
            if (in_array($box->status, ['en_pret', 'eliminee'], true)) {
                throw new InvalidArgumentException('Cette boîte n’est pas disponible au prêt.');
            }
        }

        $loan = ArchiveLoan::query()->create([
            'reference' => 'PR-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
            'status' => ArchiveLoanStatus::Demande,
            'box_id' => $box?->id,
            'record_id' => $data['record_id'] ?? null,
            'structure_id' => (int) ($data['structure_id'] ?? $box?->structure_id ?? $actor->structure_id),
            'requester_id' => $actor->id,
            'purpose' => $data['purpose'] ?? null,
            'loan_days' => (int) ($data['loan_days'] ?? 7),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->log('loan.requested', $loan, $actor);

        return $loan->load(['box', 'requester', 'structure']);
    }

    public function validate(User $actor, ArchiveLoan $loan): ArchiveLoan
    {
        $this->assertStatus($loan, [ArchiveLoanStatus::Demande, ArchiveLoanStatus::Verifie]);

        $loan->fill([
            'status' => ArchiveLoanStatus::Valide,
            'validated_by' => $actor->id,
            'validated_at' => now(),
            'due_at' => now()->addDays($loan->loan_days)->toDateString(),
        ])->save();

        $this->audit->log('loan.validated', $loan, $actor);

        return $loan->fresh(['box', 'requester']);
    }

    public function prepare(User $actor, ArchiveLoan $loan): ArchiveLoan
    {
        $this->assertStatus($loan, [ArchiveLoanStatus::Valide]);
        $loan->fill([
            'status' => ArchiveLoanStatus::Prepare,
            'prepared_at' => now(),
        ])->save();
        $this->audit->log('loan.prepared', $loan, $actor);

        return $loan->fresh(['box']);
    }

    public function checkout(User $actor, ArchiveLoan $loan): ArchiveLoan
    {
        $this->assertStatus($loan, [ArchiveLoanStatus::Prepare, ArchiveLoanStatus::Valide]);

        return DB::transaction(function () use ($actor, $loan) {
            $box = $loan->box;
            $fromId = $box?->location_id;

            $loan->fill([
                'status' => ArchiveLoanStatus::Sorti,
                'checked_out_at' => now(),
                'condition_out' => $box?->physical_state,
            ])->save();

            if ($box) {
                $box->status = 'en_pret';
                $box->save();

                $this->movements->record($actor, [
                    'movement_type' => 'pret',
                    'box_id' => $box->id,
                    'from_location_id' => $fromId,
                    'structure_id' => $loan->structure_id,
                    'motif' => 'Sortie prêt '.$loan->reference,
                    'validated_by' => $actor->id,
                ]);

                if ($fromId) {
                    $loc = $box->location;
                    if ($loc) {
                        $this->locations->refreshOccupation($loc);
                    }
                }
            }

            $this->audit->log('loan.checked_out', $loan, $actor);

            return $loan->fresh(['box', 'requester']);
        });
    }

    public function returnLoan(User $actor, ArchiveLoan $loan, ?string $conditionIn = null): ArchiveLoan
    {
        $this->assertStatus($loan, [ArchiveLoanStatus::Sorti, ArchiveLoanStatus::Remis, ArchiveLoanStatus::EnRetard]);

        return DB::transaction(function () use ($actor, $loan, $conditionIn) {
            $box = $loan->box;

            $loan->fill([
                'status' => ArchiveLoanStatus::Retourne,
                'returned_at' => now(),
                'condition_in' => $conditionIn ?: $loan->condition_out,
            ])->save();

            if ($box) {
                $box->status = 'en_magasin';
                if ($conditionIn) {
                    $box->physical_state = $conditionIn;
                }
                $box->save();

                $this->movements->record($actor, [
                    'movement_type' => 'retour',
                    'box_id' => $box->id,
                    'to_location_id' => $box->location_id,
                    'structure_id' => $loan->structure_id,
                    'motif' => 'Retour prêt '.$loan->reference,
                ]);

                if ($box->location_id) {
                    $this->locations->refreshOccupation($box->location);
                }
            }

            $loan->status = ArchiveLoanStatus::Clos;
            $loan->save();

            $this->audit->log('loan.returned', $loan, $actor);

            return $loan->fresh(['box', 'requester']);
        });
    }

    public function refuse(User $actor, ArchiveLoan $loan, string $reason): ArchiveLoan
    {
        $this->assertStatus($loan, [ArchiveLoanStatus::Demande, ArchiveLoanStatus::Verifie]);
        $loan->fill([
            'status' => ArchiveLoanStatus::Refuse,
            'validated_by' => $actor->id,
            'validated_at' => now(),
            'notes' => trim(($loan->notes ? $loan->notes."\n" : '').'Refus : '.$reason),
        ])->save();

        $this->audit->log('loan.refused', $loan, $actor, ['reason' => $reason]);

        return $loan->fresh(['box', 'requester']);
    }

    /**
     * @param  list<ArchiveLoanStatus>  $allowed
     */
    private function assertStatus(ArchiveLoan $loan, array $allowed): void
    {
        $status = $loan->status instanceof ArchiveLoanStatus
            ? $loan->status
            : ArchiveLoanStatus::from((string) $loan->status);

        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Transition de prêt interdite depuis '.$status->value.'.');
        }
    }
}
