<?php

namespace App\Enums;

enum ProductionFrequency: string
{
    case Once = 'once';
    case Daily = 'daily';
    case BusinessDays = 'business_days';
    case Weekly = 'weekly';
    case Weekdays = 'weekdays';
    case Biweekly = 'biweekly';
    case Semimonthly = 'semimonthly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
    case SpecificDates = 'specific_dates';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Ponctuelle',
            self::Daily => 'Quotidienne',
            self::BusinessDays => 'Jours ouvrables',
            self::Weekly => 'Hebdomadaire',
            self::Weekdays => 'Jours définis de la semaine',
            self::Biweekly => 'Toutes les deux semaines',
            self::Semimonthly => 'Deux fois par mois',
            self::Monthly => 'Mensuelle',
            self::Quarterly => 'Trimestrielle',
            self::Semiannual => 'Semestrielle',
            self::Annual => 'Annuelle',
            self::SpecificDates => 'Dates spécifiques',
            self::Custom => 'Règle personnalisée',
        };
    }
}
