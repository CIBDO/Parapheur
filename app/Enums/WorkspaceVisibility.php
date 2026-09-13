<?php

namespace App\Enums;

enum WorkspaceVisibility: string
{
    case Private = 'private';
    case Restricted = 'restricted';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privé',
            self::Restricted => 'Restreint',
            self::Shared => 'Partagé',
        };
    }
}
