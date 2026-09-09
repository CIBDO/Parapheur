<?php

namespace App\Support;

class Civilities
{
    public const OPTIONS = [
        'M.',
        'Mme',
        'Mlle',
        'Dr',
        'Pr',
        'Me',
    ];

    public static function values(): array
    {
        return self::OPTIONS;
    }
}
