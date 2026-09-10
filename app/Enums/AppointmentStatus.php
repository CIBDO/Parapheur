<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Brouillon = 'brouillon';
    case DemandeRecue = 'demande_recue';
    case AExaminer = 'a_examiner';
    case EnAttente = 'en_attente';
    case CreneauAProposer = 'creneau_a_proposer';
    case CreneauPropose = 'creneau_propose';
    case AValider = 'a_valider';
    case Valide = 'valide';
    case Confirme = 'confirme';
    case Reporte = 'reporte';
    case Refuse = 'refuse';
    case Annule = 'annule';
    case Pret = 'pret';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case SuiteADonner = 'suite_a_donner';
    case Cloture = 'cloture';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::DemandeRecue => 'Demande reçue',
            self::AExaminer => 'À examiner',
            self::EnAttente => 'En attente',
            self::CreneauAProposer => 'Créneau à proposer',
            self::CreneauPropose => 'Créneau proposé',
            self::AValider => 'À valider',
            self::Valide => 'Validé',
            self::Confirme => 'Confirmé',
            self::Reporte => 'Reporté',
            self::Refuse => 'Refusé',
            self::Annule => 'Annulé',
            self::Pret => 'Prêt',
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::SuiteADonner => 'Suite à donner',
            self::Cloture => 'Clôturé',
            self::Archive => 'Archivé',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Refuse, self::Annule, self::Archive], true);
    }

    public function isScheduled(): bool
    {
        return in_array($this, [
            self::CreneauPropose,
            self::AValider,
            self::Valide,
            self::Confirme,
            self::Pret,
            self::EnCours,
            self::Reporte,
        ], true);
    }

    public function blocksCalendarByDefault(): bool
    {
        return in_array($this, [
            self::Valide,
            self::Confirme,
            self::Pret,
            self::EnCours,
            self::CreneauPropose,
            self::AValider,
        ], true);
    }

    public function allowsPhysicalDelete(): bool
    {
        return in_array($this, [self::Brouillon], true);
    }
}
