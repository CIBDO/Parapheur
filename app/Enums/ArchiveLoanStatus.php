<?php

namespace App\Enums;

enum ArchiveLoanStatus: string
{
    case Demande = 'demande';
    case Verifie = 'verifie';
    case Valide = 'valide';
    case Prepare = 'prepare';
    case Sorti = 'sorti';
    case Remis = 'remis';
    case EnRetard = 'en_retard';
    case Retourne = 'retourne';
    case Clos = 'clos';
    case Refuse = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::Demande => 'Demande',
            self::Verifie => 'Vérifié',
            self::Valide => 'Validé',
            self::Prepare => 'Préparé',
            self::Sorti => 'Sorti',
            self::Remis => 'Remis',
            self::EnRetard => 'En retard',
            self::Retourne => 'Retourné',
            self::Clos => 'Clos',
            self::Refuse => 'Refusé',
        };
    }
}
