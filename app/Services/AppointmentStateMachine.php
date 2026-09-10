<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use InvalidArgumentException;

class AppointmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private array $transitions = [
        AppointmentStatus::Brouillon->value => [
            AppointmentStatus::DemandeRecue->value,
            AppointmentStatus::AExaminer->value,
            AppointmentStatus::AValider->value,
            AppointmentStatus::Valide->value,
            AppointmentStatus::Confirme->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::DemandeRecue->value => [
            AppointmentStatus::AExaminer->value,
            AppointmentStatus::EnAttente->value,
            AppointmentStatus::CreneauAProposer->value,
            AppointmentStatus::CreneauPropose->value,
            AppointmentStatus::AValider->value,
            AppointmentStatus::Refuse->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::AExaminer->value => [
            AppointmentStatus::EnAttente->value,
            AppointmentStatus::CreneauAProposer->value,
            AppointmentStatus::CreneauPropose->value,
            AppointmentStatus::AValider->value,
            AppointmentStatus::Refuse->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::EnAttente->value => [
            AppointmentStatus::AExaminer->value,
            AppointmentStatus::CreneauAProposer->value,
            AppointmentStatus::Refuse->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::CreneauAProposer->value => [
            AppointmentStatus::CreneauPropose->value,
            AppointmentStatus::AValider->value,
            AppointmentStatus::EnAttente->value,
            AppointmentStatus::Refuse->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::CreneauPropose->value => [
            AppointmentStatus::AValider->value,
            AppointmentStatus::CreneauAProposer->value,
            AppointmentStatus::Refuse->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::AValider->value => [
            AppointmentStatus::Valide->value,
            AppointmentStatus::CreneauPropose->value,
            AppointmentStatus::CreneauAProposer->value,
            AppointmentStatus::EnAttente->value,
            AppointmentStatus::Refuse->value,
            AppointmentStatus::Reporte->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::Valide->value => [
            AppointmentStatus::Confirme->value,
            AppointmentStatus::Pret->value,
            AppointmentStatus::Reporte->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::Confirme->value => [
            AppointmentStatus::Pret->value,
            AppointmentStatus::EnCours->value,
            AppointmentStatus::Reporte->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::Pret->value => [
            AppointmentStatus::EnCours->value,
            AppointmentStatus::Reporte->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::EnCours->value => [
            AppointmentStatus::Termine->value,
            AppointmentStatus::SuiteADonner->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::Termine->value => [
            AppointmentStatus::SuiteADonner->value,
            AppointmentStatus::Cloture->value,
        ],
        AppointmentStatus::SuiteADonner->value => [
            AppointmentStatus::Cloture->value,
        ],
        AppointmentStatus::Cloture->value => [
            AppointmentStatus::Archive->value,
        ],
        AppointmentStatus::Reporte->value => [
            AppointmentStatus::AExaminer->value,
            AppointmentStatus::CreneauAProposer->value,
            AppointmentStatus::CreneauPropose->value,
            AppointmentStatus::AValider->value,
            AppointmentStatus::Confirme->value,
            AppointmentStatus::Annule->value,
        ],
        AppointmentStatus::Refuse->value => [
            AppointmentStatus::Archive->value,
        ],
        AppointmentStatus::Annule->value => [
            AppointmentStatus::Archive->value,
        ],
        AppointmentStatus::Archive->value => [],
    ];

    public function canTransition(AppointmentStatus $from, AppointmentStatus $to): bool
    {
        return in_array($to->value, $this->transitions[$from->value] ?? [], true);
    }

    public function assertCanTransition(AppointmentStatus $from, AppointmentStatus $to): void
    {
        if ($this->canTransition($from, $to)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Transition de statut interdite : %s → %s.',
            $from->label(),
            $to->label()
        ));
    }

    /**
     * @return list<string>
     */
    public function allowedFrom(AppointmentStatus $from): array
    {
        return $this->transitions[$from->value] ?? [];
    }
}
