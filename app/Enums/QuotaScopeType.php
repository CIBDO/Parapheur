<?php

namespace App\Enums;

enum QuotaScopeType: string
{
    case User = 'user';
    case Workspace = 'workspace';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Utilisateur',
            self::Workspace => 'Espace de travail',
        };
    }
}
