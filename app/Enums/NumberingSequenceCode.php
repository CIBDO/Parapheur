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
    case ProductionOccurrence = 'PRO';
    case ProductionTransmission = 'TRP';
    case ProductionFolder = 'DPR';

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
            self::ProductionOccurrence => 'Occurrence de production',
            self::ProductionTransmission => 'Transmission de production',
            self::ProductionFolder => 'Dossier de production',
        };
    }


    public function prefix(): string
    {
        return $this->value;
    }
}
