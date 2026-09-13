<?php

namespace App\Enums;

enum DocumentOrigin: string
{
    case Parapheur = 'parapheur';
    case Ged = 'ged';
    case Meeting = 'meeting';
    case Appointment = 'appointment';
    case Instruction = 'instruction';

    public function label(): string
    {
        return match ($this) {
            self::Parapheur => 'Parapheur',
            self::Ged => 'GED',
            self::Meeting => 'Réunion',
            self::Appointment => 'Rendez-vous',
            self::Instruction => 'Instruction',
        };
    }
}
