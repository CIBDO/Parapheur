<?php

namespace App\Enums;

enum DocumentTemplateKind: string
{
    case BordereauTransmission = 'bordereau_transmission';
    case BordereauEnvoi = 'bordereau_envoi';
    case FicheCirculation = 'fiche_circulation';
    case AccuseReception = 'accuse_reception';
    case Lettre = 'lettre';
    case Note = 'note';
    case Formulaire = 'formulaire';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::BordereauTransmission => 'Bordereau de transmission',
            self::BordereauEnvoi => 'Bordereau d\'envoi',
            self::FicheCirculation => 'Fiche de circulation',
            self::AccuseReception => 'Accusé de réception',
            self::Lettre => 'Lettre',
            self::Note => 'Note',
            self::Formulaire => 'Formulaire',
            self::Autre => 'Autre',
        };
    }
}
