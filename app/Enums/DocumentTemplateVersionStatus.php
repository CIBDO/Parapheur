<?php

namespace App\Enums;

enum DocumentTemplateVersionStatus: string
{
    case Brouillon = 'brouillon';
    case EnRevision = 'en_revision';
    case Valide = 'valide';
    case Publie = 'publie';
    case Remplace = 'remplace';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnRevision => 'En révision',
            self::Valide => 'Validé',
            self::Publie => 'Publié',
            self::Remplace => 'Remplacé',
        };
    }
}
