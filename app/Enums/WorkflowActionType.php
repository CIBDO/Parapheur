<?php

namespace App\Enums;

enum WorkflowActionType: string
{
    case PriseConnaissance = 'prise_connaissance';
    case Commenter = 'commenter';
    case Avis = 'avis';
    case Instruction = 'instruction';
    case DemandeComplement = 'demande_complement';
    case RetourCorrection = 'retour_correction';
    case Valider = 'valider';
    case Rejeter = 'rejeter';
    case Viser = 'viser';
    case Transmettre = 'transmettre';
    case Reaffecter = 'reaffecter';
    case MettreEnAttente = 'mettre_en_attente';
    case Classer = 'classer';
    case Archiver = 'archiver';

    public function label(): string
    {
        return match ($this) {
            self::PriseConnaissance => 'Prise de connaissance',
            self::Commenter => 'Commenter',
            self::Avis => 'Émettre un avis',
            self::Instruction => 'Donner une instruction',
            self::DemandeComplement => 'Demander un complément',
            self::RetourCorrection => 'Retourner pour correction',
            self::Valider => 'Valider',
            self::Rejeter => 'Rejeter',
            self::Viser => 'Viser',
            self::Transmettre => 'Transmettre',
            self::Reaffecter => 'Réaffecter',
            self::MettreEnAttente => 'Mettre en attente',
            self::Classer => 'Classer',
            self::Archiver => 'Archiver',
        };
    }
}
