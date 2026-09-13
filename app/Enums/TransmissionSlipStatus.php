<?php

namespace App\Enums;

enum TransmissionSlipStatus: string
{
    case Brouillon = 'brouillon';
    case Genere = 'genere';
    case EnModification = 'en_modification';
    case AValider = 'a_valider';
    case Valide = 'valide';
    case Imprime = 'imprime';
    case Transmis = 'transmis';
    case Recu = 'recu';
    case Retourne = 'retourne';
    case Cloture = 'cloture';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Genere => 'Généré',
            self::EnModification => 'En modification',
            self::AValider => 'À valider',
            self::Valide => 'Validé',
            self::Imprime => 'Imprimé',
            self::Transmis => 'Transmis',
            self::Recu => 'Reçu',
            self::Retourne => 'Retourné',
            self::Cloture => 'Clôturé',
            self::Annule => 'Annulé',
        };
    }
}
