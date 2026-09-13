<?php

namespace App\Enums;

enum NumberingSequenceCode: string
{
    case Arrival = 'ARR';
    case Departure = 'DEP';
    case TransmissionSlip = 'BT';
    case CirculationSheet = 'FC';

    public function label(): string
    {
        return match ($this) {
            self::Arrival => 'Arrivée',
            self::Departure => 'Départ',
            self::TransmissionSlip => 'Bordereau transmission',
            self::CirculationSheet => 'Fiche circulation',
        };
    }

    public function prefix(): string
    {
        return $this->value;
    }
}
