<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Brouillon = 'brouillon';
    case Depose = 'depose';
    case EnCircuit = 'en_circuit';
    case Transmis = 'transmis';
    case AConsulter = 'a_consulter';
    case EnConsultation = 'en_consultation';
    case EnAttente = 'en_attente';
    case ACorriger = 'a_corriger';
    case Corrige = 'corrige';
    case AViser = 'a_viser';
    case Vise = 'vise';
    case AValider = 'a_valider';
    case Valide = 'valide';
    case Rejete = 'rejete';
    case Traite = 'traite';
    case Classe = 'classe';
    case Archive = 'archive';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Depose => 'Déposé',
            self::EnCircuit => 'En circuit',
            self::Transmis => 'Transmis',
            self::AConsulter => 'À consulter',
            self::EnConsultation => 'En consultation',
            self::EnAttente => 'En attente',
            self::ACorriger => 'À corriger',
            self::Corrige => 'Corrigé',
            self::AViser => 'À viser',
            self::Vise => 'Visé',
            self::AValider => 'À valider',
            self::Valide => 'Validé',
            self::Rejete => 'Rejeté',
            self::Traite => 'Traité',
            self::Classe => 'Classé',
            self::Archive => 'Archivé',
            self::Annule => 'Annulé',
        };
    }
}
