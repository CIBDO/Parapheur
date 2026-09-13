<?php

namespace App\Enums;

enum CorrespondenceDirection: string
{
    case Entrant = 'entrant';
    case Sortant = 'sortant';
    case Interne = 'interne';

    public function label(): string
    {
        return match ($this) {
            self::Entrant => 'Entrant',
            self::Sortant => 'Sortant',
            self::Interne => 'Interne',
        };
    }
}
