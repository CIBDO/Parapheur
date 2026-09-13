<?php

namespace App\Enums;

enum CorrespondenceAssignmentStatus: string
{
    case Transmis = 'transmis';
    case Recu = 'recu';
    case Consulte = 'consulte';
    case PrisEnCharge = 'pris_en_charge';
    case Traite = 'traite';
    case Retourne = 'retourne';
    case Reoriente = 'reoriente';

    public function label(): string
    {
        return match ($this) {
            self::Transmis => 'Transmis',
            self::Recu => 'Reçu',
            self::Consulte => 'Consulté',
            self::PrisEnCharge => 'Pris en charge',
            self::Traite => 'Traité',
            self::Retourne => 'Retourné',
            self::Reoriente => 'Réorienté',
        };
    }
}
