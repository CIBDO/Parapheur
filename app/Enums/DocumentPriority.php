<?php

namespace App\Enums;

enum DocumentPriority: string
{
    case Normale = 'normale';
    case Importante = 'importante';
    case Urgente = 'urgente';
    case TresUrgente = 'tres_urgente';

    public function label(): string
    {
        return match ($this) {
            self::Normale => 'Normale',
            self::Importante => 'Importante',
            self::Urgente => 'Urgente',
            self::TresUrgente => 'Très urgente',
        };
    }
}
