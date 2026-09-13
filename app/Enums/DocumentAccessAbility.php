<?php

namespace App\Enums;

enum DocumentAccessAbility: string
{
    case View = 'view';
    case Download = 'download';
    case Comment = 'comment';
    case Edit = 'edit';
    case Share = 'share';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Consulter',
            self::Download => 'Télécharger',
            self::Comment => 'Commenter',
            self::Edit => 'Modifier',
            self::Share => 'Partager',
        };
    }
}
