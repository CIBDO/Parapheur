<?php

namespace App\Enums;

enum DocumentAttachmentKind: string
{
    case PieceJointe = 'piece_jointe';
    case Annexe = 'annexe';
    case Complement = 'complement';
    case Justificatif = 'justificatif';
    case Reference = 'reference';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::PieceJointe => 'Pièce jointe',
            self::Annexe => 'Annexe',
            self::Complement => 'Complément',
            self::Justificatif => 'Document justificatif',
            self::Reference => 'Document de référence',
            self::Final => 'Document final',
        };
    }
}
