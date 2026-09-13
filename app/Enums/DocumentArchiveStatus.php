<?php

namespace App\Enums;

enum DocumentArchiveStatus: string
{
    case Actif = 'actif';
    case AArchiver = 'a_archiver';
    case Archive = 'archive';
    case Gele = 'gele';
    case AVerser = 'a_verser';
    case Verse = 'verse';

    public function label(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::AArchiver => 'À archiver',
            self::Archive => 'Archivé',
            self::Gele => 'Gelé',
            self::AVerser => 'À verser',
            self::Verse => 'Versé',
        };
    }
}
