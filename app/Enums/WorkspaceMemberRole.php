<?php

namespace App\Enums;

enum WorkspaceMemberRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Editor = 'editor';
    case Contributor = 'contributor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Manager => 'Gestionnaire',
            self::Editor => 'Éditeur',
            self::Contributor => 'Contributeur',
            self::Viewer => 'Lecteur',
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canEditDocuments(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Editor], true);
    }

    public function canContribute(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Editor, self::Contributor], true);
    }

    public function canManageFolders(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Editor], true);
    }

    public function canShare(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Editor], true);
    }
}
