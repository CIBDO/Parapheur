<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use InvalidArgumentException;

class MeetingStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private array $transitions = [
        MeetingStatus::Brouillon->value => [
            MeetingStatus::EnPreparation->value,
            MeetingStatus::Planifiee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::EnPreparation->value => [
            MeetingStatus::ConvocationAValider->value,
            MeetingStatus::Planifiee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::ConvocationAValider->value => [
            MeetingStatus::Convoquee->value,
            MeetingStatus::EnPreparation->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::Planifiee->value => [
            MeetingStatus::EnPreparation->value,
            MeetingStatus::ConvocationAValider->value,
            MeetingStatus::Convoquee->value,
            MeetingStatus::Prete->value,
            MeetingStatus::EnCours->value,
            MeetingStatus::Reportee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::Convoquee->value => [
            MeetingStatus::ConfirmationEnCours->value,
            MeetingStatus::Prete->value,
            MeetingStatus::EnCours->value,
            MeetingStatus::Reportee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::ConfirmationEnCours->value => [
            MeetingStatus::Prete->value,
            MeetingStatus::EnCours->value,
            MeetingStatus::Reportee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::Prete->value => [
            MeetingStatus::EnCours->value,
            MeetingStatus::Reportee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::EnCours->value => [
            MeetingStatus::Suspendue->value,
            MeetingStatus::Terminee->value,
        ],
        MeetingStatus::Suspendue->value => [
            MeetingStatus::EnCours->value,
            MeetingStatus::Terminee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::Terminee->value => [
            MeetingStatus::CrEnRedaction->value,
            MeetingStatus::Cloturee->value,
        ],
        MeetingStatus::CrEnRedaction->value => [
            MeetingStatus::CrEnValidation->value,
        ],
        MeetingStatus::CrEnValidation->value => [
            MeetingStatus::CrValide->value,
            MeetingStatus::CrEnRedaction->value,
        ],
        MeetingStatus::CrValide->value => [
            MeetingStatus::Cloturee->value,
        ],
        MeetingStatus::Cloturee->value => [
            MeetingStatus::Archivee->value,
        ],
        MeetingStatus::Reportee->value => [
            MeetingStatus::EnPreparation->value,
            MeetingStatus::Planifiee->value,
            MeetingStatus::Convoquee->value,
            MeetingStatus::Annulee->value,
        ],
        MeetingStatus::Annulee->value => [],
        MeetingStatus::Archivee->value => [],
    ];

    public function canTransition(MeetingStatus $from, MeetingStatus $to): bool
    {
        return in_array($to->value, $this->transitions[$from->value] ?? [], true);
    }

    public function assertCanTransition(MeetingStatus $from, MeetingStatus $to): void
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
    public function allowedFrom(MeetingStatus $from): array
    {
        return $this->transitions[$from->value] ?? [];
    }
}
