<?php

namespace App\Enums;

enum DocumentPublicationStatus: string
{
    case Brouillon = 'brouillon';
    case Depose = 'depose';
    case EnVerification = 'en_verification';
    case ACorriger = 'a_corriger';
    case Valide = 'valide';
    case Publie = 'publie';
    case Archive = 'archive';
    case Rejete = 'rejete';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Depose => 'Déposé',
            self::EnVerification => 'En vérification',
            self::ACorriger => 'À corriger',
            self::Valide => 'Validé',
            self::Publie => 'Publié',
            self::Archive => 'Archivé',
            self::Rejete => 'Rejeté',
        };
    }

    public function isPubliclyListed(): bool
    {
        return $this === self::Publie;
    }

    public function isEditableDraft(): bool
    {
        return in_array($this, [self::Brouillon, self::Depose, self::ACorriger], true);
    }
}
