<?php

namespace App\Enums;

enum DocumentFolderKind: string
{
    case Institutional = 'institutional';
    case Structure = 'structure';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Institutional => 'Institutionnel',
            self::Structure => 'Structure',
            self::Restricted => 'Restreint',
        };
    }
}
