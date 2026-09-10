<?php

namespace App\Enums;

enum MeetingDecisionStatus: string
{
    case AFaire = 'a_faire';
    case Planifiee = 'planifiee';
    case EnCours = 'en_cours';
    case EnAttente = 'en_attente';
    case Bloquee = 'bloquee';
    case Executee = 'executee';
    case PartiellementExecutee = 'partiellement_executee';
    case EnRetard = 'en_retard';
    case Annulee = 'annulee';
    case Cloturee = 'cloturee';

    public function label(): string
    {
        return match ($this) {
            self::AFaire => 'À faire',
            self::Planifiee => 'Planifiée',
            self::EnCours => 'En cours',
            self::EnAttente => 'En attente',
            self::Bloquee => 'Bloquée',
            self::Executee => 'Exécutée',
            self::PartiellementExecutee => 'Partiellement exécutée',
            self::EnRetard => 'En retard',
            self::Annulee => 'Annulée',
            self::Cloturee => 'Clôturée',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Executee, self::Annulee, self::Cloturee], true);
    }
}
