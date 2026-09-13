<?php

namespace App\Enums;

enum DocumentOrigin: string
{
    case Parapheur = 'parapheur';
    case Ged = 'ged';
    case Meeting = 'meeting';
    case Appointment = 'appointment';
    case Instruction = 'instruction';
    case Personal = 'personal';
    case Workspace = 'workspace';
    case Courrier = 'courrier';

    public function label(): string
    {
        return match ($this) {
            self::Parapheur => 'Parapheur',
            self::Ged => 'GED',
            self::Meeting => 'Réunion',
            self::Appointment => 'Rendez-vous',
            self::Instruction => 'Instruction',
            self::Personal => 'Personnel',
            self::Workspace => 'Espace de travail',
            self::Courrier => 'Courrier',
        };
    }

    public function isWorkspaceBound(): bool
    {
        return $this === self::Personal || $this === self::Workspace;
    }
}

