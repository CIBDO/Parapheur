<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Nouveau = 'NOUVEAU';
    case AQualifier = 'A_QUALIFIER';
    case Affecte = 'AFFECTE';
    case PrisEnCharge = 'PRIS_EN_CHARGE';
    case EnCours = 'EN_COURS';
    case EnAttenteDemandeur = 'EN_ATTENTE_DEMANDEUR';
    case EnAttenteTiers = 'EN_ATTENTE_TIERS';
    case Escalade = 'ESCALADE';
    case Resolu = 'RESOLU';
    case AValider = 'A_VALIDER';
    case Reouvert = 'REOUVERT';
    case Cloture = 'CLOTURE';
    case Annule = 'ANNULE';

    public function label(): string
    {
        return match ($this) {
            self::Nouveau => 'Nouveau',
            self::AQualifier => 'À qualifier',
            self::Affecte => 'Affecté',
            self::PrisEnCharge => 'Pris en charge',
            self::EnCours => 'En cours',
            self::EnAttenteDemandeur => 'En attente demandeur',
            self::EnAttenteTiers => 'En attente tiers',
            self::Escalade => 'Escaladé',
            self::Resolu => 'Résolu',
            self::AValider => 'À valider',
            self::Reouvert => 'Réouvert',
            self::Cloture => 'Clôturé',
            self::Annule => 'Annulé',
        };
    }

    public function pausesSla(): bool
    {
        return in_array($this, [self::EnAttenteDemandeur, self::EnAttenteTiers], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
