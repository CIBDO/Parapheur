<?php

namespace App\Enums;

enum InstructionStatus: string
{
    case Brouillon = 'brouillon';
    case AFaire = 'a_faire';
    case Emise = 'emise';
    case PriseEnCharge = 'prise_en_charge';
    case EnCours = 'en_cours';
    case EnAttente = 'en_attente';
    case Executee = 'executee';
    case EnValidation = 'en_validation';
    case Cloturee = 'cloturee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::AFaire => 'À faire',
            self::Emise => 'Émise',
            self::PriseEnCharge => 'Prise en charge',
            self::EnCours => 'En cours',
            self::EnAttente => 'En attente',
            self::Executee => 'Exécutée',
            self::EnValidation => 'En validation',
            self::Cloturee => 'Clôturée',
            self::Annulee => 'Annulée',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::Brouillon,
            self::AFaire,
            self::Emise,
            self::PriseEnCharge,
            self::EnCours,
            self::EnAttente,
            self::EnValidation,
        ], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->isOpen())
        );
    }

    /** @return list<string> */
    public static function imputationValues(): array
    {
        return [
            self::Brouillon->value,
            self::AFaire->value,
            self::EnCours->value,
            self::Executee->value,
            self::Cloturee->value,
            self::Annulee->value,
        ];
    }

    /** @return list<string> */
    public static function directiveValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
