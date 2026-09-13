<?php

namespace App\Enums;

enum DocumentLinkRelation: string
{
    case RelatedTo = 'related_to';
    case Replaces = 'replaces';
    case Supersedes = 'supersedes';
    case AnnexOf = 'annex_of';
    case ResponseTo = 'response_to';
    case GeneratedFrom = 'generated_from';
    case VersionOf = 'version_of';

    public function label(): string
    {
        return match ($this) {
            self::RelatedTo => 'Lié à',
            self::Replaces => 'Remplace',
            self::Supersedes => 'Rend obsolète',
            self::AnnexOf => 'Annexe de',
            self::ResponseTo => 'Réponse à',
            self::GeneratedFrom => 'Généré depuis',
            self::VersionOf => 'Version de',
        };
    }
}
