<?php

namespace App\Enums;

enum ArchiveCommunicationStatus: string
{
    case Demande = 'demande';
    case Autorisee = 'autorisee';
    case Refusee = 'refusee';
    case Expiree = 'expiree';
    case Revoquee = 'revoquee';

    public function label(): string
    {
        return match ($this) {
            self::Demande => 'Demande',
            self::Autorisee => 'Autorisée',
            self::Refusee => 'Refusée',
            self::Expiree => 'Expirée',
            self::Revoquee => 'Révoquée',
        };
    }
}
