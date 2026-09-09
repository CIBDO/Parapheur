<?php

namespace App\Enums;

enum DocumentConfidentiality: string
{
    case Normal = 'normal';
    case Restreint = 'restreint';
    case Confidentiel = 'confidentiel';
    case TresConfidentiel = 'tres_confidentiel';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Restreint => 'Restreint',
            self::Confidentiel => 'Confidentiel',
            self::TresConfidentiel => 'Très confidentiel',
        };
    }
}
