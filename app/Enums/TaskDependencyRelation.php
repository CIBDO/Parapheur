<?php

namespace App\Enums;

enum TaskDependencyRelation: string
{
    case Blocks = 'blocks';
    case BlockedBy = 'blocked_by';
    case DependsOn = 'depends_on';
    case RelatedTo = 'related_to';

    public function label(): string
    {
        return match ($this) {
            self::Blocks => 'Bloque',
            self::BlockedBy => 'Bloquée par',
            self::DependsOn => 'Dépend de',
            self::RelatedTo => 'Liée à',
        };
    }

    public function inverse(): ?self
    {
        return match ($this) {
            self::Blocks => self::BlockedBy,
            self::BlockedBy => self::Blocks,
            self::DependsOn => null,
            self::RelatedTo => self::RelatedTo,
        };
    }
}
