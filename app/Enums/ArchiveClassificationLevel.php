<?php

namespace App\Enums;

enum ArchiveClassificationLevel: string
{
    case Fonds = 'fonds';
    case SousFonds = 'sous_fonds';
    case Serie = 'serie';
    case SousSerie = 'sous_serie';
    case Dossier = 'dossier';
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::Fonds => 'Fonds',
            self::SousFonds => 'Sous-fonds',
            self::Serie => 'Série',
            self::SousSerie => 'Sous-série',
            self::Dossier => 'Dossier',
            self::Piece => 'Pièce',
        };
    }
}
