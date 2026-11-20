<?php

namespace App\Enums;

enum ArchiveLocationStatus: string
{
    case Disponible = 'disponible';
    case Occupe = 'occupe';
    case Indisponible = 'indisponible';
    case Quarantaine = 'quarantaine';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Occupe => 'Occupé',
            self::Indisponible => 'Indisponible',
            self::Quarantaine => 'Quarantaine',
        };
    }
}
