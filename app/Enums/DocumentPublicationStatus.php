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

    /**
     * Documents visibles dans la bibliothèque institutionnelle (hors brouillon / rejet).
     * La rétention administrative démarre au versement (`depose`).
     */
    public function isInstitutionallyCatalogued(): bool
    {
        return in_array($this, [
            self::Depose,
            self::EnVerification,
            self::ACorriger,
            self::Valide,
            self::Publie,
            self::Archive,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function institutionalCatalogValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(
                self::cases(),
                static fn (self $status): bool => $status->isInstitutionallyCatalogued(),
            ),
        ));
    }

    public function isEditableDraft(): bool
    {
        return in_array($this, [self::Brouillon, self::Depose, self::ACorriger], true);
    }
}
