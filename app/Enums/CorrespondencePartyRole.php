<?php

namespace App\Enums;

enum CorrespondencePartyRole: string
{
    case From = 'from';
    case To = 'to';
    case Cc = 'cc';
    case Ampliation = 'ampliation';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::From => 'De',
            self::To => 'À',
            self::Cc => 'Copie',
            self::Ampliation => 'Ampliation',
            self::Info => 'Information',
        };
    }
}
