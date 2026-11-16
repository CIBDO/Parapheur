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
    /** @deprecated Conservé pour lecture legacy ; migrer vers Validation (pas de SE au Mali). */
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
            self::Validation, self::Signature => 'Pour validation',
            self::Revision => 'Pour révision',
        };
    }

    /** Actions proposées à la création / transmission (sans « signature »). */
    public static function selectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $case !== self::Signature,
        ));
    }
}
