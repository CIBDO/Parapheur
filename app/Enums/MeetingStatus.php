<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Brouillon = 'brouillon';
    case EnPreparation = 'en_preparation';
    case ConvocationAValider = 'convocation_a_valider';
    case Planifiee = 'planifiee';
    case Convoquee = 'convoquee';
    case ConfirmationEnCours = 'confirmation_en_cours';
    case Prete = 'prete';
    case EnCours = 'en_cours';
    case Suspendue = 'suspendue';
    case Terminee = 'terminee';
    case CrEnRedaction = 'cr_en_redaction';
    case CrEnValidation = 'cr_en_validation';
    case CrValide = 'cr_valide';
    case Cloturee = 'cloturee';
    case Annulee = 'annulee';
    case Reportee = 'reportee';
    case Archivee = 'archivee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnPreparation => 'En préparation',
            self::ConvocationAValider => 'Convocation à valider',
            self::Planifiee => 'Planifiée',
            self::Convoquee => 'Convoquée',
            self::ConfirmationEnCours => 'Confirmation en cours',
            self::Prete => 'Prête',
            self::EnCours => 'En cours',
            self::Suspendue => 'Suspendue',
            self::Terminee => 'Terminée',
            self::CrEnRedaction => 'CR en rédaction',
            self::CrEnValidation => 'CR en validation',
            self::CrValide => 'CR validé',
            self::Cloturee => 'Clôturée',
            self::Annulee => 'Annulée',
            self::Reportee => 'Reportée',
            self::Archivee => 'Archivée',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Annulee, self::Archivee], true);
    }

    public function allowsPhysicalDelete(): bool
    {
        return in_array($this, [self::Brouillon, self::EnPreparation], true);
    }

    public function agendaLocked(): bool
    {
        return in_array($this, [
            self::Convoquee,
            self::ConfirmationEnCours,
            self::Prete,
            self::EnCours,
            self::Suspendue,
            self::Terminee,
            self::CrEnRedaction,
            self::CrEnValidation,
            self::CrValide,
            self::Cloturee,
            self::Archivee,
            self::Annulee,
        ], true);
    }
}
