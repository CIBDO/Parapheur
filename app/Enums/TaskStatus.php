<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Brouillon = 'brouillon';
    case Imputee = 'imputee';
    case PriseEnCharge = 'prise_en_charge';
    case EnCours = 'en_cours';
    case EnAttente = 'en_attente';
    case Terminee = 'terminee';
    case AValider = 'a_valider';
    case Validee = 'validee';
    case Retournee = 'retournee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Imputee => 'Imputée',
            self::PriseEnCharge => 'Prise en charge',
            self::EnCours => 'En cours',
            self::EnAttente => 'En attente',
            self::Terminee => 'Terminée',
            self::AValider => 'À valider',
            self::Validee => 'Validée',
            self::Retournee => 'Retournée',
            self::Annulee => 'Annulée',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Validee, self::Annulee], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Validee, self::Annulee], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->isOpen())
        );
    }
}
