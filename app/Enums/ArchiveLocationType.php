<?php

namespace App\Enums;

enum ArchiveLocationType: string
{
    case Site = 'site';
    case Building = 'building';
    case Room = 'room';
    case Zone = 'zone';
    case Rack = 'rack';
    case Bay = 'bay';
    case Face = 'face';
    case Shelf = 'shelf';
    case Slot = 'slot';
    case Cabinet = 'cabinet';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Site',
            self::Building => 'Bâtiment',
            self::Room => 'Salle / Magasin',
            self::Zone => 'Zone',
            self::Rack => 'Rayonnage',
            self::Bay => 'Travée',
            self::Face => 'Face',
            self::Shelf => 'Tablette',
            self::Slot => 'Emplacement',
            self::Cabinet => 'Armoire',
        };
    }
}
