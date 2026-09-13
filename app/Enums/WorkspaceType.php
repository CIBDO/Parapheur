<?php

namespace App\Enums;

enum WorkspaceType: string
{
    case Personal = 'personal';
    case Shared = 'shared';
    case Team = 'team';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personnel',
            self::Shared => 'Partagé',
            self::Team => 'Équipe',
            self::Project => 'Projet',
        };
    }
}
