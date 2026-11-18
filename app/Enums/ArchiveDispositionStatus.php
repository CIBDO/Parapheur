<?php

namespace App\Enums;

enum ArchiveDispositionStatus: string
{
    case Proposition = 'proposition';
    case EnValidation = 'en_validation';
    case Autorisee = 'autorisee';
    case Refusee = 'refusee';
    case Executee = 'executee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Proposition => 'Proposition',
            self::EnValidation => 'En validation',
            self::Autorisee => 'Autorisée',
            self::Refusee => 'Refusée',
            self::Executee => 'Exécutée',
            self::Annulee => 'Annulée',
        };
    }
}
