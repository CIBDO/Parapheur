<?php

namespace App\Enums;

enum CorrespondenceMedium: string
{
    case Physique = 'physique';
    case Electronique = 'electronique';
    case Hybride = 'hybride';

    public function label(): string
    {
        return match ($this) {
            self::Physique => 'Physique',
            self::Electronique => 'Électronique',
            self::Hybride => 'Hybride',
        };
    }
}
