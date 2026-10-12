<?php

namespace App\Services\Productions;

use App\Enums\ProductionOccurrenceStatus;
use App\Models\ProductionOccurrence;
use App\Models\ProductionReminderLog;
use App\Models\User;
use App\Notifications\ProductionReminderNotification;
use Carbon\Carbon;

class ProductionReminderService
{
    /**
     * Process reminder rules for open occurrences. Idempotent via dedupe_key.
     *
     * @return array{sent: int, skipped: int}
     */
    public function processDue(?Carbon $now = null): array
    {
        $now ??= now();
        $sent = 0;
        $skipped = 0;

        ProductionOccurrence::query()
            ->with(['obligation.reminderRules', 'obligation.producer', 'obligation.deputy', 'responsible'])
            ->whereNotIn('status', [
                ProductionOccurrenceStatus::Transmise->value,
                ProductionOccurrenceStatus::Cloturee->value,
                ProductionOccurrenceStatus::Annulee->value,
            ])
            ->whereNotNull('deadline_at')
            ->orderBy('id')
            ->chunkById(100, function ($occurrences) use ($now, &$sent, &$skipped) {
                foreach ($occurrences as $occurrence) {
                    $rules = $occurrence->obligation?->reminderRules?->where('is_active', true) ?? collect();
                    foreach ($rules as $rule) {
                        if ($this->shouldStop($occurrence, $rule->stop_when)) {
                            $skipped++;

                            continue;
                        }

                        $triggerAt = $this->triggerAt($occurrence->deadline_at, $rule->offset_type, (int) $rule->offset_minutes);
                        if ($now->lt($triggerAt)) {
                            $skipped++;

                            continue;
                        }

                        $slot = $triggerAt->format('YmdHi');
                        if ($rule->repeat_every_minutes) {
                            $minutesPast = $triggerAt->diffInMinutes($now);
                            $slot = $triggerAt->copy()
                                ->addMinutes((int) (floor($minutesPast / $rule->repeat_every_minutes) * $rule->repeat_every_minutes))
                                ->format('YmdHi');
                        }

                        $dedupe = "occ:{$occurrence->id}:rule:{$rule->id}:slot:{$slot}";
                        if (ProductionReminderLog::query()->where('dedupe_key', $dedupe)->exists()) {
                            $skipped++;

                            continue;
                        }

                        $targets = $this->resolveTargets($occurrence, $rule->target);
                        foreach ($targets as $user) {
                            $user->notify(new ProductionReminderNotification(
                                $occurrence,
                                'reminder',
                                (bool) $rule->escalate,
                                in_array($rule->target, ['producer', 'deputy', 'validator', 'hierarchy', 'recipient'], true)
                                    ? $rule->target
                                    : 'producer',
                            ));
                        }

                        ProductionReminderLog::query()->create([
                            'production_occurrence_id' => $occurrence->id,
                            'production_reminder_rule_id' => $rule->id,
                            'kind' => $rule->escalate ? 'escalate' : 'reminder',
                            'dedupe_key' => $dedupe,
                            'sent_at' => now(),
                        ]);
                        $sent++;
                    }
                }
            });

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    private function shouldStop(ProductionOccurrence $occurrence, string $stopWhen): bool
    {
        return match ($stopWhen) {
            'deposited' => (bool) $occurrence->deposited_at,
            'validated' => (bool) $occurrence->validated_at,
            'closed' => $occurrence->status === ProductionOccurrenceStatus::Cloturee,
            default => (bool) $occurrence->transmitted_at
                || $occurrence->status === ProductionOccurrenceStatus::Transmise,
        };
    }

    private function triggerAt(Carbon $deadline, string $offsetType, int $minutes): Carbon
    {
        return match ($offsetType) {
            'after' => $deadline->copy()->addMinutes($minutes),
            'at_deadline' => $deadline->copy(),
            default => $deadline->copy()->subMinutes($minutes),
        };
    }

    /**
     * @return list<User>
     */
    private function resolveTargets(ProductionOccurrence $occurrence, string $target): array
    {
        $users = [];
        $obligation = $occurrence->obligation;

        if ($target === 'producer' && $occurrence->responsible) {
            $users[] = $occurrence->responsible;
        }
        if ($target === 'deputy' && $obligation?->deputy) {
            $users[] = $obligation->deputy;
        }
        if ($target === 'validator') {
            foreach ($occurrence->validations()->where('decision', 'pending')->with('validator')->get() as $validation) {
                if ($validation->validator) {
                    $users[] = $validation->validator;
                }
            }
        }
        if ($target === 'hierarchy' && $obligation?->structure_id) {
            User::role(['Directeur', 'Chef de division', 'Chef de section'])
                ->where('structure_id', $obligation->structure_id)
                ->where('is_active', true)
                ->get()
                ->each(fn ($u) => $users[] = $u);
        }
        if ($target === 'recipient') {
            foreach ($obligation?->recipients ?? [] as $actor) {
                if ($actor->user) {
                    $users[] = $actor->user;
                }
            }
        }

        $unique = [];
        foreach ($users as $user) {
            $unique[$user->id] = $user;
        }

        return array_values($unique);
    }
}
