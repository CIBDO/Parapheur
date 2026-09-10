<?php

namespace App\Enums;

enum CalendarUnavailabilityKind: string
{
    case Mission = 'mission';
    case Conge = 'conge';
    case Deplacement = 'deplacement';
    case ReunionExterne = 'reunion_externe';
    case Indisponibilite = 'indisponibilite';
    case PauseInstitutionnelle = 'pause_institutionnelle';
    case Absence = 'absence';
    case CreneauReserve = 'creneau_reserve';

    public function label(): string
    {
        return match ($this) {
            self::Mission => 'Mission',
            self::Conge => 'Congé',
            self::Deplacement => 'Déplacement',
            self::ReunionExterne => 'Réunion externe',
            self::Indisponibilite => 'Indisponibilité',
            self::PauseInstitutionnelle => 'Pause institutionnelle',
            self::Absence => 'Absence',
            self::CreneauReserve => 'Créneau réservé',
        };
    }
}
