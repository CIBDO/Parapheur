<?php

namespace App\Enums;

enum MeetingDocumentKind: string
{
    case DocumentPrincipal = 'document_principal';
    case PieceJointe = 'piece_jointe';
    case Annexe = 'annexe';
    case Presentation = 'presentation';
    case Rapport = 'rapport';
    case Tableau = 'tableau';
    case DocumentDeTravail = 'document_de_travail';
    case CompteRendu = 'compte_rendu';
    case Pv = 'pv';
    case Convocation = 'convocation';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::DocumentPrincipal => 'Document principal',
            self::PieceJointe => 'Pièce jointe',
            self::Annexe => 'Annexe',
            self::Presentation => 'Présentation',
            self::Rapport => 'Rapport',
            self::Tableau => 'Tableau',
            self::DocumentDeTravail => 'Document de travail',
            self::CompteRendu => 'Compte rendu',
            self::Pv => 'Procès-verbal',
            self::Convocation => 'Convocation',
            self::Autre => 'Autre',
        };
    }
}
