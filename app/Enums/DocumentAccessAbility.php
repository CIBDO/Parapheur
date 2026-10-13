<?php

namespace App\Enums;

enum DocumentAccessAbility: string
{
    case View = 'view';
    case Download = 'download';
    case Comment = 'comment';
    case Edit = 'edit';
    case Share = 'share';
    case Upload = 'upload';
    case Validate = 'validate';
    case Publish = 'publish';
    case Archive = 'archive';
    case ManageAcl = 'manage_acl';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Consulter',
            self::Download => 'Télécharger',
            self::Comment => 'Commenter',
            self::Edit => 'Modifier',
            self::Share => 'Partager',
            self::Upload => 'Téléverser',
            self::Validate => 'Valider',
            self::Publish => 'Publier',
            self::Archive => 'Archiver',
            self::ManageAcl => 'Gérer les permissions',
        };
    }
}
