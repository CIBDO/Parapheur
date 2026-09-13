<?php

namespace App\Services;

use App\Enums\CorrespondenceStatus;
use InvalidArgumentException;

class CorrespondenceStateMachine
{
    /**
     * Map des transitions autorisées : status actuel => [statuts suivants autorisés]
     */
    protected array $transitions = [
        CorrespondenceStatus::Recu->value => [
            CorrespondenceStatus::Enregistre->value,
            CorrespondenceStatus::Annule->value,
        ],
        CorrespondenceStatus::Enregistre->value => [
            CorrespondenceStatus::AQualifier->value,
            CorrespondenceStatus::AAffecter->value,
            CorrespondenceStatus::Affecte->value,
            CorrespondenceStatus::Classe->value,
            CorrespondenceStatus::Annule->value,
        ],
        CorrespondenceStatus::AQualifier->value => [
            CorrespondenceStatus::AAffecter->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::AAffecter->value => [
            CorrespondenceStatus::Affecte->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::Affecte->value => [
            CorrespondenceStatus::PrisEnCharge->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::PrisEnCharge->value => [
            CorrespondenceStatus::EnTraitement->value,
            CorrespondenceStatus::EnAttente->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::EnTraitement->value => [
            CorrespondenceStatus::EnAttente->value,
            CorrespondenceStatus::EnAttenteComplement->value,
            CorrespondenceStatus::ProjetReponse->value,
            CorrespondenceStatus::Repondu->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::EnAttente->value => [
            CorrespondenceStatus::EnTraitement->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::EnAttenteComplement->value => [
            CorrespondenceStatus::EnTraitement->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::ProjetReponse->value => [
            CorrespondenceStatus::AViser->value,
            CorrespondenceStatus::AValider->value,
            CorrespondenceStatus::ASigner->value,
            CorrespondenceStatus::Valide->value,
            CorrespondenceStatus::EnTraitement->value,
        ],
        CorrespondenceStatus::AViser->value => [
            CorrespondenceStatus::AValider->value,
            CorrespondenceStatus::ASigner->value,
            CorrespondenceStatus::Valide->value,
            CorrespondenceStatus::EnTraitement->value,
        ],
        CorrespondenceStatus::AValider->value => [
            CorrespondenceStatus::ASigner->value,
            CorrespondenceStatus::Valide->value,
            CorrespondenceStatus::EnTraitement->value,
        ],
        CorrespondenceStatus::ASigner->value => [
            CorrespondenceStatus::Signe->value,
            CorrespondenceStatus::Valide->value,
            CorrespondenceStatus::EnTraitement->value,
        ],
        CorrespondenceStatus::Valide->value => [
            CorrespondenceStatus::AExpedier->value,
            CorrespondenceStatus::Expedie->value,
            CorrespondenceStatus::Repondu->value,
        ],
        CorrespondenceStatus::Signe->value => [
            CorrespondenceStatus::AExpedier->value,
            CorrespondenceStatus::Expedie->value,
            CorrespondenceStatus::Repondu->value,
        ],
        CorrespondenceStatus::AExpedier->value => [
            CorrespondenceStatus::Expedie->value,
        ],
        CorrespondenceStatus::Expedie->value => [
            CorrespondenceStatus::AccuseRecu->value,
            CorrespondenceStatus::Repondu->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::AccuseRecu->value => [
            CorrespondenceStatus::Repondu->value,
            CorrespondenceStatus::Classe->value,
        ],
        CorrespondenceStatus::Repondu->value => [
            CorrespondenceStatus::Classe->value,
            CorrespondenceStatus::Archive->value,
        ],
        CorrespondenceStatus::Classe->value => [
            CorrespondenceStatus::Archive->value,
        ],
        CorrespondenceStatus::Archive->value => [],
        CorrespondenceStatus::Annule->value => [],
    ];

    public function canTransition(CorrespondenceStatus $currentStatus, CorrespondenceStatus $newStatus): bool
    {
        $allowed = $this->transitions[$currentStatus->value] ?? [];

        return in_array($newStatus->value, $allowed, true);
    }

    public function assertCanTransition(CorrespondenceStatus $currentStatus, CorrespondenceStatus $newStatus): void
    {
        if (! $this->canTransition($currentStatus, $newStatus)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Transition invalide : %s → %s',
                    $currentStatus->label(),
                    $newStatus->label()
                )
            );
        }
    }

    public function transition(CorrespondenceStatus $currentStatus, CorrespondenceStatus $newStatus): CorrespondenceStatus
    {
        $this->assertCanTransition($currentStatus, $newStatus);

        return $newStatus;
    }

    /**
     * Retourne les statuts autorisés depuis le statut actuel.
     *
     * @return array<CorrespondenceStatus>
     */
    public function allowedTransitions(CorrespondenceStatus $currentStatus): array
    {
        $allowed = $this->transitions[$currentStatus->value] ?? [];

        return array_map(fn (string $value) => CorrespondenceStatus::from($value), $allowed);
    }
}
