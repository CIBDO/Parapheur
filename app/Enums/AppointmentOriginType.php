<?php

namespace App\Enums;

enum AppointmentOriginType: string
{
    case Agent = 'agent';
    case Directeur = 'directeur';
    case Structure = 'structure';
    case Partenaire = 'partenaire';
    case Institution = 'institution';
    case Fournisseur = 'fournisseur';
    case Particulier = 'particulier';
    case Secretariat = 'secretariat';
    case DirecteurGeneral = 'directeur_general';

    public function label(): string
    {
        return match ($this) {
            self::Agent => 'Agent DGTCP',
            self::Directeur => 'Directeur',
            self::Structure => 'Structure rattachée',
            self::Partenaire => 'Partenaire',
            self::Institution => 'Institution',
            self::Fournisseur => 'Fournisseur',
            self::Particulier => 'Particulier',
            self::Secretariat => 'Secrétariat',
            self::DirecteurGeneral => 'Directeur Général',
        };
    }
}
