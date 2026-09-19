<?php

namespace App\Services;

use App\Enums\NumberingSequenceCode;
use App\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    /**
     * Prochain numéro transactionnel. Format : PREFIX/YEAR/000001
     * Les compteurs MVP sont globaux (structure_id ignoré pour l'unicité).
     */
    public function nextNumber(string|NumberingSequenceCode $code, ?int $year = null, ?int $structureId = null): string
    {
        $codeValue = $code instanceof NumberingSequenceCode ? $code->value : $code;
        $prefix = $code instanceof NumberingSequenceCode ? $code->prefix() : $codeValue;
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($codeValue, $prefix, $year) {
            $sequence = NumberingSequence::query()
                ->where('code', $codeValue)
                ->where('year', $year)
                ->whereNull('structure_id')
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = NumberingSequence::query()->create([
                    'code' => $codeValue,
                    'year' => $year,
                    'prefix' => $prefix,
                    'padding' => 6,
                    'last_value' => 0,
                    'reset_yearly' => true,
                    'structure_id' => null,
                ]);

                $sequence = NumberingSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_value = (int) $sequence->last_value + 1;
            $sequence->save();

            return sprintf(
                '%s/%d/%s',
                $sequence->prefix,
                $sequence->year,
                str_pad((string) $sequence->last_value, (int) $sequence->padding, '0', STR_PAD_LEFT)
            );
        });
    }

    public function generate(NumberingSequenceCode|string $code, ?int $structureId = null): string
    {
        return $this->nextNumber($code, null, $structureId);
    }

    public function generateArrivalNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::Arrival, null, $structureId);
    }

    public function generateDepartureNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::Departure, null, $structureId);
    }

    public function generateTransmissionSlipNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::TransmissionSlip, null, $structureId);
    }

    public function generateCirculationSheetNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::CirculationSheet, null, $structureId);
    }

    public function generateDispatchSlipNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::DispatchSlip, null, $structureId);
    }

    public function generateAcknowledgementNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::Acknowledgement, null, $structureId);
    }

    public function generateTicketNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::Ticket, null, $structureId);
    }

    public function generateProblemNumber(?int $structureId = null): string
    {
        return $this->nextNumber(NumberingSequenceCode::Problem, null, $structureId);
    }
}
