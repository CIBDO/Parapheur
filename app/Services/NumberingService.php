<?php

namespace App\Services;

use App\Enums\NumberingSequenceCode;
use App\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    /**
     * Tables/colonnes qui consomment un code de séquence (pour resync si last_value est en retard).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SEQUENCE_SOURCES = [
        'ARR' => ['correspondences', 'arrival_number'],
        'DEP' => ['correspondences', 'departure_number'],
        'BT' => ['transmission_slips', 'number'],
        'FC' => ['circulation_sheets', 'number'],
        'BE' => ['correspondence_dispatches', 'number'],
        'AR' => ['correspondence_acknowledgements', 'number'],
        'TCK' => ['tickets', 'number'],
        'PRB' => ['problems', 'number'],
        'TSK' => ['tasks', 'reference'],
        'INS' => ['instructions', 'reference'],
        'PRO' => ['production_occurrences', 'reference'],
        'TRP' => ['production_transmissions', 'reference'],
        'DPR' => ['production_folders', 'reference'],
    ];

    private const MAIL_CODES = ['ARR', 'DEP', 'BT', 'FC', 'BE', 'AR'];

    /**
     * Prochain numéro transactionnel.
     * Courrier : PREFIX/CODE_STRUCTURE/ANNEE/000001, compteur isolé par frontière.
     * Autres modules, ou appel sans structure : PREFIX/ANNEE/000001, compteur global.
     */
    public function nextNumber(string|NumberingSequenceCode $code, ?int $year = null, ?int $structureId = null): string
    {
        $codeValue = $code instanceof NumberingSequenceCode ? $code->value : $code;
        $prefix = $code instanceof NumberingSequenceCode ? $code->prefix() : $codeValue;
        $year ??= (int) now()->format('Y');
        $boundaryId = $this->mailBoundaryId($codeValue, $structureId);
        $structureCode = $boundaryId ? $this->structureCode($boundaryId) : null;
        $numberPrefix = $structureCode ? $prefix.'/'.$structureCode : $prefix;

        return DB::transaction(function () use ($codeValue, $prefix, $year, $boundaryId, $numberPrefix) {
            $sequenceQuery = NumberingSequence::query()
                ->where('code', $codeValue)
                ->where('year', $year);

            if ($boundaryId) {
                $sequenceQuery->where('structure_id', $boundaryId);
            } else {
                $sequenceQuery->whereNull('structure_id');
            }

            $sequence = $sequenceQuery->lockForUpdate()->first();

            if (! $sequence) {
                $sequence = NumberingSequence::query()->create([
                    'code' => $codeValue,
                    'year' => $year,
                    'prefix' => $prefix,
                    'padding' => 6,
                    'last_value' => 0,
                    'reset_yearly' => true,
                    'structure_id' => $boundaryId,
                ]);

                $sequence = NumberingSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $existingMax = $this->maxExistingValue($sequence->code, $numberPrefix, $sequence->year);
            if ($existingMax > (int) $sequence->last_value) {
                $sequence->last_value = $existingMax;
            }

            $sequence->last_value = (int) $sequence->last_value + 1;
            $sequence->save();

            return sprintf(
                '%s/%d/%s',
                $numberPrefix,
                $sequence->year,
                str_pad((string) $sequence->last_value, (int) $sequence->padding, '0', STR_PAD_LEFT)
            );
        });
    }

    private function mailBoundaryId(string $code, ?int $structureId): ?int
    {
        if (! $structureId || ! in_array($code, self::MAIL_CODES, true)) {
            return null;
        }

        $structure = \App\Models\Structure::query()->find($structureId);
        if (! $structure) {
            return null;
        }

        return (int) ($structure->boundary_structure_id ?: $structure->id);
    }

    private function structureCode(int $structureId): string
    {
        $code = (string) \App\Models\Structure::query()->whereKey($structureId)->value('code');
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');

        return $code !== '' ? $code : 'S'.$structureId;
    }

    /**
     * Plus grand suffixe numérique déjà attribué pour ce préfixe/année (évite les collisions unique).
     * @param  string  $numberPrefix  Préfixe affiché, par exemple ARR ou ARR/DSI.
     */
    private function maxExistingValue(string $code, string $numberPrefix, int $year): int
    {
        $source = self::SEQUENCE_SOURCES[$code] ?? null;
        if ($source === null) {
            return 0;
        }

        [$table, $column] = $source;
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return 0;
        }

        $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $numberPrefix.'/'.$year.'/').'%';
        $values = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw($column.' like ? escape ?', [$pattern, '\\'])
            ->pluck($column);

        $max = 0;
        foreach ($values as $value) {
            if (preg_match('#/(\d+)$#', (string) $value, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
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
