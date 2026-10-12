<?php

namespace App\Enums;

enum InstructionKind: string
{
    case Imputation = 'imputation';
    case Directive = 'directive';

    public function label(): string
    {
        return match ($this) {
            self::Imputation => 'Imputation',
            self::Directive => 'Directive',
        };
    }
}
