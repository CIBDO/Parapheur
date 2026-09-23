<?php

namespace App\Enums;

enum TaskSource: string
{
    case Manual = 'manual';
    case Courrier = 'courrier';
    case Ticket = 'ticket';
    case Meeting = 'meeting';
    case Decision = 'decision';
    case Parapheur = 'parapheur';
    case Dossier = 'dossier';
    case Affaire = 'affaire';
    case Appointment = 'appointment';
    case Instruction = 'instruction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuelle',
            self::Courrier => 'Courrier',
            self::Ticket => 'Ticket',
            self::Meeting => 'Réunion',
            self::Decision => 'Décision',
            self::Parapheur => 'Parapheur',
            self::Dossier => 'Dossier',
            self::Affaire => 'Affaire',
            self::Appointment => 'Rendez-vous',
            self::Instruction => 'Instruction',
            self::Other => 'Autre',
        };
    }
}
