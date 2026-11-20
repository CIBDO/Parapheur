<?php

namespace App\Enums;

enum ArchiveFinalDisposition: string
{
    case ConservationDefinitive = 'conservation_definitive';
    case Transfert = 'transfert';
    case Reevaluation = 'reevaluation';
    case Elimination = 'elimination';

    public function label(): string
    {
        return match ($this) {
            self::ConservationDefinitive => 'Conservation définitive',
            self::Transfert => 'Transfert',
            self::Reevaluation => 'Réévaluation',
            self::Elimination => 'Élimination',
        };
    }
}
