<?php

namespace App\Enums;

enum ExpectedAction: string
{
    case Information = 'information';
    case Consultation = 'consultation';
    case Avis = 'avis';
    case Observations = 'observations';
    case Instruction = 'instruction';
    case Visa = 'visa';
    case Validation = 'validation';
    case Revision = 'revision';
    case Signature = 'signature';

    public function label(): string
    {
        return match ($this) {
            self::Information => 'Pour information',
            self::Consultation => 'Pour consultation',
            self::Avis => 'Pour avis',
            self::Observations => 'Pour observations',
            self::Instruction => 'Pour instruction',
            self::Visa => 'Pour visa',
            self::Validation => 'Pour validation',
            self::Revision => 'Pour révision',
            self::Signature => 'Pour signature',
        };
    }
}
