<?php

namespace App\Enums;

enum CorrespondenceStatus: string
{
    case Recu = 'recu';
    case Enregistre = 'enregistre';
    case AQualifier = 'a_qualifier';
    case AAffecter = 'a_affecter';
    case Affecte = 'affecte';
    case PrisEnCharge = 'pris_en_charge';
    case EnTraitement = 'en_traitement';
    case EnAttente = 'en_attente';
    case EnAttenteComplement = 'en_attente_complement';
    case ProjetReponse = 'projet_reponse';
    case AViser = 'a_viser';
    case AValider = 'a_valider';
    case ASigner = 'a_signer';
    case Valide = 'valide';
    case Signe = 'signe';
    case Repondu = 'repondu';
    case AExpedier = 'a_expedier';
    case Expedie = 'expedie';
    case AccuseRecu = 'accuse_recu';
    case Classe = 'classe';
    case Archive = 'archive';
    case Annule = 'annule';

    public function label(): string
    {
        return match($this) {
            self::Recu => 'Reçu',
            self::Enregistre => 'Enregistré',
            self::AQualifier => 'À qualifier',
            self::AAffecter => 'À affecter',
            self::Affecte => 'Affecté',
            self::PrisEnCharge => 'Pris en charge',
            self::EnTraitement => 'En traitement',
            self::EnAttente => 'En attente',
            self::EnAttenteComplement => 'En attente de complément',
            self::ProjetReponse => 'Projet de réponse',
            self::AViser => 'À viser',
            self::AValider => 'À valider',
            self::ASigner => 'À signer',
            self::Valide => 'Validé',
            self::Signe => 'Signé',
            self::Repondu => 'Répondu',
            self::AExpedier => 'À expédier',
            self::Expedie => 'Expédié',
            self::AccuseRecu => 'Accusé de réception',
            self::Classe => 'Classé',
            self::Archive => 'Archivé',
            self::Annule => 'Annulé',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Recu, self::AQualifier => 'secondary',
            self::Enregistre, self::AAffecter => 'info',
            self::Affecte, self::PrisEnCharge => 'primary',
            self::EnTraitement, self::ProjetReponse => 'warning',
            self::EnAttente, self::EnAttenteComplement => 'default',
            self::AViser, self::AValider, self::ASigner => 'purple',
            self::Valide, self::Signe => 'indigo',
            self::Repondu, self::AccuseRecu => 'success',
            self::AExpedier => 'orange',
            self::Expedie => 'teal',
            self::Classe, self::Archive => 'default',
            self::Annule => 'error',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Classe,
            self::Archive,
            self::Annule,
        ]);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        // États terminaux ne peuvent pas changer
        if ($this->isTerminal()) {
            return false;
        }

        // Logique de transition simplifiée - à affiner selon les besoins métier
        return !$newStatus->isTerminal() || $newStatus !== self::Annule;
    }
}
