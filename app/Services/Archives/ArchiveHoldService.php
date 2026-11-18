<?php

namespace App\Services\Archives;

use App\Enums\ArchiveRecordStatus;
use App\Models\ArchiveHold;
use App\Models\ArchiveRecord;
use App\Models\ArchiveRetentionEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArchiveHoldService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    public function place(User $actor, ArchiveRecord $record, string $reason, string $holdType = 'administratif', ?string $notes = null): ArchiveHold
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Le motif du gel est obligatoire.');
        }

        return DB::transaction(function () use ($actor, $record, $reason, $holdType, $notes) {
            $active = $record->holds()->whereNull('lifted_at')->exists();
            if ($active) {
                throw new InvalidArgumentException('Un gel est déjà actif sur cette archive.');
            }

            $hold = ArchiveHold::query()->create([
                'record_id' => $record->id,
                'reason' => $reason,
                'hold_type' => $holdType,
                'placed_by' => $actor->id,
                'placed_at' => now(),
                'notes' => $notes,
            ]);

            $record->status = ArchiveRecordStatus::Gele;
            $record->save();

            ArchiveRetentionEvent::query()->create([
                'record_id' => $record->id,
                'rule_id' => $record->retention_rule_id,
                'event_type' => 'hold',
                'actor_id' => $actor->id,
                'payload' => ['hold_id' => $hold->id, 'reason' => $reason],
            ]);

            $this->audit->log('hold.placed', $record, $actor, [
                'hold_id' => $hold->id,
                'reason' => $reason,
            ]);

            return $hold->load(['placer', 'record']);
        });
    }

    public function lift(User $actor, ArchiveHold $hold, ?string $notes = null): ArchiveHold
    {
        if ($hold->lifted_at) {
            throw new InvalidArgumentException('Ce gel est déjà levé.');
        }

        return DB::transaction(function () use ($actor, $hold, $notes) {
            $hold->fill([
                'lifted_by' => $actor->id,
                'lifted_at' => now(),
                'notes' => trim(($hold->notes ? $hold->notes."\n" : '').($notes ? 'Levée : '.$notes : 'Levée contrôlée')),
            ])->save();

            $record = $hold->record;
            if ($record && ! $record->holds()->whereNull('lifted_at')->exists()) {
                $record->status = ArchiveRecordStatus::Conserve;
                $record->save();
            }

            $this->audit->log('hold.lifted', $hold->record, $actor, [
                'hold_id' => $hold->id,
            ]);

            return $hold->fresh(['lifter', 'record']);
        });
    }

    public function hasActiveHold(ArchiveRecord $record): bool
    {
        return $record->holds()->whereNull('lifted_at')->exists();
    }
}
