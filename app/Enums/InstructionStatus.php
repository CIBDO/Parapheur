<?php

namespace App\Enums;

enum InstructionStatus: string
{
    case Brouillon = 'brouillon';
    case AFaire = 'a_faire';
    case EnCours = 'en_cours';
    case Executee = 'executee';
    case Cloturee = 'cloturee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::AFaire => 'À faire',
            self::EnCours => 'En cours',
            self::Executee => 'Exécutée',
            self::Cloturee => 'Clôturée',
            self::Annulee => 'Annulée',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Brouillon, self::AFaire, self::EnCours], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::AFaire->value, self::EnCours->value];
    }
}
