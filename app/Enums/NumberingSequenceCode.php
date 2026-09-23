<?php

namespace App\Enums;

enum NumberingSequenceCode: string
{
    case Arrival = 'ARR';
    case Departure = 'DEP';
    case TransmissionSlip = 'BT';
    case CirculationSheet = 'FC';
    case DispatchSlip = 'BE';
    case Acknowledgement = 'AR';
    case Ticket = 'TCK';
    case Problem = 'PRB';
    case Task = 'TSK';
    case Instruction = 'INS';

    public function label(): string
    {
        return match ($this) {
            self::Arrival => 'Arrivée',
            self::Departure => 'Départ',
            self::TransmissionSlip => 'Bordereau transmission',
            self::CirculationSheet => 'Fiche circulation',
            self::DispatchSlip => 'Bordereau envoi',
            self::Acknowledgement => 'Accusé de réception',
            self::Ticket => 'Ticket',
            self::Problem => 'Problème',
            self::Task => 'Tâche',
            self::Instruction => 'Instruction',
        };
    }

    public function prefix(): string
    {
        return $this->value;
    }
}
