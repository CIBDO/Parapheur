<?php

namespace App\Enums;

enum ProductionOccurrenceStatus: string
{
    case AProduire = 'a_produire';
    case EnPreparation = 'en_preparation';
    case Deposee = 'deposee';
    case ACompleter = 'a_completer';
    case Soumise = 'soumise_validation';
    case EnValidation = 'en_validation';
    case Rejetee = 'rejetee';
    case Validee = 'validee';
    case PreteATransmettre = 'prete_a_transmettre';
    case Transmise = 'transmise';
    case Cloturee = 'cloturee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::AProduire => 'À produire',
            self::EnPreparation => 'En préparation',
            self::Deposee => 'Déposée',
            self::ACompleter => 'À compléter',
            self::Soumise => 'Soumise à validation',
            self::EnValidation => 'En validation',
            self::Rejetee => 'Rejetée',
            self::Validee => 'Validée',
            self::PreteATransmettre => 'Prête à transmettre',
            self::Transmise => 'Transmise',
            self::Cloturee => 'Clôturée',
            self::Annulee => 'Annulée',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Cloturee, self::Annulee, self::Transmise], true);
    }

    public function allowsDeposit(): bool
    {
        return in_array($this, [
            self::AProduire,
            self::EnPreparation,
            self::Deposee,
            self::ACompleter,
            self::Rejetee,
        ], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
