<?php

namespace App\Enums;

enum AppointmentMeetingMode: string
{
    case Presentiel = 'presentiel';
    case Visioconference = 'visioconference';
    case Telephone = 'telephone';
    case Hybride = 'hybride';
    case Externe = 'externe';

    public function label(): string
    {
        return match ($this) {
            self::Presentiel => 'Présentiel',
            self::Visioconference => 'Visioconférence',
            self::Telephone => 'Téléphone',
            self::Hybride => 'Hybride',
            self::Externe => 'Externe',
        };
    }
}
