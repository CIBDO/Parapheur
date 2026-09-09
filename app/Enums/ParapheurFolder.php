<?php

namespace App\Enums;

enum ParapheurFolder: string
{
    case ATraiter = 'a_traiter';
    case AConsulter = 'a_consulter';
    case PourInformation = 'pour_information';
    case AViser = 'a_viser';
    case AValider = 'a_valider';
    case EnAttente = 'en_attente';
    case Retournes = 'retournes';
    case Traites = 'traites';
    case Archives = 'archives';

    public function label(): string
    {
        return match ($this) {
            self::ATraiter => 'À traiter',
            self::AConsulter => 'À consulter',
            self::PourInformation => 'Pour information',
            self::AViser => 'À viser',
            self::AValider => 'À valider',
            self::EnAttente => 'En attente',
            self::Retournes => 'Retournés',
            self::Traites => 'Traités',
            self::Archives => 'Archivés',
        };
    }
}
