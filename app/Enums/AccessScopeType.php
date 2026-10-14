<?php

namespace App\Enums;

enum AccessScopeType: string
{
    case Personnel = 'personnel';
    case Unite = 'unite';
    case Hierarchique = 'hierarchique';
    case Structure = 'structure';
    case Specifique = 'specifique';
    case Transversal = 'transversal';

    public function label(): string
    {
        return match ($this) {
            self::Personnel => 'Personnel',
            self::Unite => 'Unité',
            self::Hierarchique => 'Hiérarchique',
            self::Structure => 'Structure',
            self::Specifique => 'Spécifique',
            self::Transversal => 'Transversal',
        };
    }
}
