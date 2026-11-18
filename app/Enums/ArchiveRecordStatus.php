<?php

namespace App\Enums;

enum ArchiveRecordStatus: string
{
    case EnVersement = 'en_versement';
    case Conserve = 'conserve';
    case Gele = 'gele';
    case AEliminer = 'a_eliminer';
    case Elimine = 'elimine';
    case Restitue = 'restitue';

    public function label(): string
    {
        return match ($this) {
            self::EnVersement => 'En versement',
            self::Conserve => 'Conservé',
            self::Gele => 'Gelé',
            self::AEliminer => 'À éliminer',
            self::Elimine => 'Éliminé',
            self::Restitue => 'Restitué',
        };
    }
}
