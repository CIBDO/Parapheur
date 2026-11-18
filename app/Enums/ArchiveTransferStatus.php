<?php

namespace App\Enums;

enum ArchiveTransferStatus: string
{
    case Brouillon = 'brouillon';
    case Soumis = 'soumis';
    case EnControle = 'en_controle';
    case Rejete = 'rejete';
    case Accepte = 'accepte';
    case PrisEnCharge = 'pris_en_charge';
    case Conserve = 'conserve';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Soumis => 'Soumis',
            self::EnControle => 'En contrôle',
            self::Rejete => 'Rejeté',
            self::Accepte => 'Accepté',
            self::PrisEnCharge => 'Pris en charge',
            self::Conserve => 'Conservé',
        };
    }
}
