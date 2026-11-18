<?php

namespace App\Enums;

enum ArchivePackageType: string
{
    case Sip = 'sip';
    case Aip = 'aip';
    case Dip = 'dip';

    public function label(): string
    {
        return match ($this) {
            self::Sip => 'SIP (versement)',
            self::Aip => 'AIP (archivage)',
            self::Dip => 'DIP (diffusion)',
        };
    }
}
