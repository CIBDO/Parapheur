<?php

namespace App\Services\Productions;

use App\Enums\ProductionFrequency;
use App\Models\ProductionObligation;
use App\Models\SlaCalendar;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class RecurrenceService
{
    /**
     * Determine if an obligation must produce an occurrence for the given date.
     */
    public function shouldGenerate(ProductionObligation $obligation, CarbonInterface $date): bool
    {
        $day = $date->copy()->startOfDay();

        if (! $obligation->is_active) {
            return false;
        }

        if ($obligation->valid_from && $day->lt($obligation->valid_from->startOfDay())) {
            return false;
        }

        if ($obligation->valid_until && $day->gt($obligation->valid_until->endOfDay())) {
            return false;
        }

        if ($obligation->isSuspendedOn($day)) {
            return false;
        }

        if (! $this->matchesFrequency($obligation, $day)) {
            return false;
        }

        return $this->isAllowedByCalendar($obligation, $day);
    }

    public function periodKey(ProductionObligation $obligation, CarbonInterface $date): string
    {
        $day = $date->copy()->startOfDay();
        $config = $obligation->schedule_config ?? [];

        return match ($obligation->frequency) {
            ProductionFrequency::Once => 'once-'.($config['date'] ?? $day->toDateString()),
            ProductionFrequency::Daily,
            ProductionFrequency::BusinessDays,
            ProductionFrequency::Weekdays => $day->toDateString(),
            ProductionFrequency::Weekly,
            ProductionFrequency::Biweekly => $day->copy()->startOfWeek()->toDateString(),
            ProductionFrequency::Semimonthly => $day->format('Y-m').'-'.($day->day <= 15 ? 'H1' : 'H2'),
            ProductionFrequency::Monthly => $day->format('Y-m'),
            ProductionFrequency::Quarterly => $day->format('Y').'-Q'.$day->quarter,
            ProductionFrequency::Semiannual => $day->format('Y').'-S'.($day->month <= 6 ? '1' : '2'),
            ProductionFrequency::Annual => $day->format('Y'),
            ProductionFrequency::SpecificDates => $day->toDateString(),
            ProductionFrequency::Custom => ($config['period_prefix'] ?? 'custom').'-'.$day->toDateString(),
            default => $day->toDateString(),
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function periodBounds(ProductionObligation $obligation, CarbonInterface $date): array
    {
        $day = $date->copy()->startOfDay();

        return match ($obligation->frequency) {
            ProductionFrequency::Weekly,
            ProductionFrequency::Biweekly => [
                'start' => $day->copy()->startOfWeek(),
                'end' => $day->copy()->endOfWeek(),
            ],
            ProductionFrequency::Semimonthly => $day->day <= 15
                ? ['start' => $day->copy()->startOfMonth(), 'end' => $day->copy()->startOfMonth()->addDays(14)]
                : ['start' => $day->copy()->startOfMonth()->addDays(15), 'end' => $day->copy()->endOfMonth()],
            ProductionFrequency::Monthly => [
                'start' => $day->copy()->startOfMonth(),
                'end' => $day->copy()->endOfMonth(),
            ],
            ProductionFrequency::Quarterly => [
                'start' => $day->copy()->firstOfQuarter(),
                'end' => $day->copy()->lastOfQuarter(),
            ],
            ProductionFrequency::Semiannual => $day->month <= 6
                ? ['start' => $day->copy()->startOfYear(), 'end' => $day->copy()->month(6)->endOfMonth()]
                : ['start' => $day->copy()->month(7)->startOfMonth(), 'end' => $day->copy()->endOfYear()],
            ProductionFrequency::Annual => [
                'start' => $day->copy()->startOfYear(),
                'end' => $day->copy()->endOfYear(),
            ],
            default => [
                'start' => $day->copy(),
                'end' => $day->copy(),
            ],
        };
    }

    public function deadlineAt(ProductionObligation $obligation, CarbonInterface $date): Carbon
    {
        $tz = $obligation->timezone ?: 'Africa/Bamako';
        $time = $obligation->deadline_time ?: '10:00:00';
        $parts = explode(':', (string) $time);

        return Carbon::parse($date->toDateString(), $tz)
            ->setTime((int) ($parts[0] ?? 10), (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
    }

    private function matchesFrequency(ProductionObligation $obligation, CarbonInterface $day): bool
    {
        $config = $obligation->schedule_config ?? [];

        return match ($obligation->frequency) {
            ProductionFrequency::Once => ($config['date'] ?? null) === $day->toDateString(),
            ProductionFrequency::Daily => true,
            ProductionFrequency::BusinessDays => $day->isWeekday(),
            ProductionFrequency::Weekly => (int) ($config['weekday'] ?? Carbon::FRIDAY) === $day->dayOfWeek,
            ProductionFrequency::Weekdays => in_array($day->dayOfWeek, $config['weekdays'] ?? [], true),
            ProductionFrequency::Biweekly => $this->matchesBiweekly($day, $config),
            ProductionFrequency::Semimonthly => in_array($day->day, $config['days'] ?? [1, 15], true),
            ProductionFrequency::Monthly => $day->day === (int) ($config['day'] ?? 1),
            ProductionFrequency::Quarterly => $day->day === (int) ($config['day'] ?? 1)
                && in_array($day->month, [1, 4, 7, 10], true),
            ProductionFrequency::Semiannual => $day->day === (int) ($config['day'] ?? 1)
                && in_array($day->month, [1, 7], true),
            ProductionFrequency::Annual => $day->month === (int) ($config['month'] ?? 1)
                && $day->day === (int) ($config['day'] ?? 1),
            ProductionFrequency::SpecificDates => in_array($day->toDateString(), $config['dates'] ?? [], true),
            ProductionFrequency::Custom => (bool) ($config['force'] ?? false)
                || in_array($day->toDateString(), $config['dates'] ?? [], true),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function matchesBiweekly(CarbonInterface $day, array $config): bool
    {
        $weekday = (int) ($config['weekday'] ?? Carbon::MONDAY);
        if ($day->dayOfWeek !== $weekday) {
            return false;
        }

        $anchor = Carbon::parse($config['anchor'] ?? $day->copy()->startOfYear()->toDateString())->startOfDay();
        $weeks = (int) floor($anchor->diffInWeeks($day) / 2) * 2;

        return $anchor->copy()->addWeeks($weeks)->isSameDay($day)
            || $anchor->copy()->addWeeks($weeks + 2)->isSameDay($day)
            || abs($anchor->diffInDays($day)) % 14 === 0;
    }

    private function isAllowedByCalendar(ProductionObligation $obligation, CarbonInterface $day): bool
    {
        if (! $obligation->calendar_id) {
            if ($obligation->frequency === ProductionFrequency::BusinessDays && ! $day->isWeekday()) {
                return false;
            }

            return true;
        }

        /** @var SlaCalendar|null $calendar */
        $calendar = $obligation->calendar;
        if (! $calendar || ! $calendar->is_active) {
            return true;
        }

        $exception = $calendar->exceptions()
            ->whereDate('date', $day->toDateString())
            ->first();

        if ($exception) {
            return ! (bool) ($exception->is_closed ?? true);
        }

        $weekdays = $calendar->weekdays ?? [1, 2, 3, 4, 5];

        // Carbon: 0=Sunday … ISO often 1=Monday. Normalize to ISO-like 1-7 if stored that way.
        $dow = $day->dayOfWeekIso;

        return in_array($dow, $weekdays, true) || in_array($day->dayOfWeek, $weekdays, true);
    }
}
