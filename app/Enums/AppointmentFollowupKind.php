<?php

namespace App\Enums;

enum AppointmentFollowupKind: string
{
    case Aucune = 'aucune';
    case Instruction = 'instruction';
    case Tache = 'tache';
    case Reunion = 'reunion';
    case Document = 'document';
    case Reponse = 'reponse';
    case NouveauRdv = 'nouveau_rdv';
    case Transmission = 'transmission';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Aucune => 'Aucune suite',
            self::Instruction => 'Instruction',
            self::Tache => 'Tâche',
            self::Reunion => 'Réunion',
            self::Document => 'Document à préparer',
            self::Reponse => 'Réponse à transmettre',
            self::NouveauRdv => 'Nouveau rendez-vous',
            self::Transmission => 'Transmission à une Direction',
            self::Autre => 'Autre',
        };
    }
}
