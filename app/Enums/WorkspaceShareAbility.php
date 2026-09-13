<?php

namespace App\Enums;

enum WorkspaceShareAbility: string
{
    case View = 'view';
    case Contribute = 'contribute';
    case Edit = 'edit';
    case Manage = 'manage';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Consulter',
            self::Contribute => 'Contribuer',
            self::Edit => 'Modifier',
            self::Manage => 'Gérer',
        };
    }
}
