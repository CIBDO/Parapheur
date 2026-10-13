<?php

namespace App\Enums;

enum AnnouncementConfidentiality: string
{
    case General = 'general';
    case Targeted = 'targeted';
    case Restricted = 'restricted';
    case Confidential = 'confidential';

    public function isSensitive(): bool
    {
        return in_array($this, [self::Restricted, self::Confidential], true);
    }
}
