<?php

namespace App\Enums;

enum PublicationWorkflow: string
{
    case Simple = 'simple';
    case Controlled = 'controlled';
    case Hierarchical = 'hierarchical';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Circuit simple',
            self::Controlled => 'Circuit contrôlé',
            self::Hierarchical => 'Circuit hiérarchique',
        };
    }
}
