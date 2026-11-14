<?php

namespace App\Services\Productions;

use App\Enums\ProductionOccurrenceStatus;
use App\Models\ProductionOccurrence;
use App\Models\ProductionReminderLog;
use App\Models\User;
use App\Notifications\ProductionMorningDigestNotification;
use Carbon\Carbon;

class ProductionReminderService
{
    /**
     * Rappel unique du matin : un email par producteur pour les livrables du jour non encore envoyés.
     *
     * @return array{sent: int, skipped: int}
     */
    public function processMorningDigest(?Carbon $now = null): array
    {
        $now ??= now();
        $today = $now->toDateString();
        $dedupe = 'morning-digest:'.$today;
        $sent = 0;
        $skipped = 0;

        $open = ProductionOccurrence::query()
            ->with(['obligation:id,code,label,producer_id,deputy_id', 'responsible:id,name,email'])
            ->whereNotIn('status', [
                ProductionOccurrenceStatus::Transmise->value,
                ProductionOccurrenceStatus::Cloturee->value,
                ProductionOccurrenceStatus::Annulee->value,
            ])
            ->where(function ($q) use ($today) {
                $q->whereDate('reference_date', $today)
                    ->orWhere(function ($inner) use ($today) {
                        $inner->whereDate('period_start', '<=', $today)
                            ->whereDate('period_end', '>=', $today);
                    });
            })
            ->get();

        if ($open->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        /** @var array<int, list<ProductionOccurrence>> $byUser */
        $byUser = [];

        foreach ($open as $occurrence) {
            $userIds = array_filter([
                $occurrence->responsible_id,
                $occurrence->obligation?->producer_id,
                $occurrence->obligation?->deputy_id,
            ]);
            foreach (array_unique($userIds) as $userId) {
                $byUser[(int) $userId][] = $occurrence;
            }
        }

        foreach ($byUser as $userId => $occurrenceList) {
            $occurrences = collect($occurrenceList);
            $userDedupe = $dedupe.':user:'.$userId;
            if (ProductionReminderLog::query()->where('dedupe_key', $userDedupe)->exists()) {
                $skipped++;

                continue;
            }

            $user = User::query()->find($userId);
            if (! $user || ! $user->email) {
                $skipped++;

                continue;
            }

            $items = $occurrences->unique('id')->map(fn (ProductionOccurrence $o) => (object) [
                'id' => $o->id,
                'reference' => $o->reference,
                'label' => $o->obligation?->label ?? $o->reference,
            ])->values();

            $user->notify(new ProductionMorningDigestNotification($items));

            ProductionReminderLog::query()->create([
                'production_occurrence_id' => $occurrences->first()->id,
                'production_reminder_rule_id' => null,
                'kind' => 'morning_digest',
                'dedupe_key' => $userDedupe,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Conservé pour compatibilité Artisan : délègue au digest du matin.
     *
     * @return array{sent: int, skipped: int}
     */
    public function processDue(?Carbon $now = null): array
    {
        return $this->processMorningDigest($now);
    }
}
