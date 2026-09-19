<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use InvalidArgumentException;

class TicketStateMachine
{
    /**
     * Map des transitions autorisées : status actuel => [statuts suivants autorisés]
     *
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        TicketStatus::Nouveau->value => [
            TicketStatus::AQualifier->value,
            TicketStatus::Affecte->value,
            TicketStatus::Annule->value,
        ],
        TicketStatus::AQualifier->value => [
            TicketStatus::Affecte->value,
            TicketStatus::Annule->value,
        ],
        TicketStatus::Affecte->value => [
            TicketStatus::PrisEnCharge->value,
            TicketStatus::EnCours->value,
            TicketStatus::Escalade->value,
            TicketStatus::Annule->value,
        ],
        TicketStatus::PrisEnCharge->value => [
            TicketStatus::EnCours->value,
            TicketStatus::EnAttenteDemandeur->value,
            TicketStatus::EnAttenteTiers->value,
            TicketStatus::Escalade->value,
            TicketStatus::Resolu->value,
        ],
        TicketStatus::EnCours->value => [
            TicketStatus::EnAttenteDemandeur->value,
            TicketStatus::EnAttenteTiers->value,
            TicketStatus::Escalade->value,
            TicketStatus::Resolu->value,
        ],
        TicketStatus::EnAttenteDemandeur->value => [
            TicketStatus::EnCours->value,
            TicketStatus::Resolu->value,
            TicketStatus::Annule->value,
        ],
        TicketStatus::EnAttenteTiers->value => [
            TicketStatus::EnCours->value,
            TicketStatus::Resolu->value,
            TicketStatus::Annule->value,
        ],
        TicketStatus::Escalade->value => [
            TicketStatus::Affecte->value,
            TicketStatus::PrisEnCharge->value,
            TicketStatus::EnCours->value,
        ],
        TicketStatus::Resolu->value => [
            TicketStatus::AValider->value,
            TicketStatus::Cloture->value,
            TicketStatus::Reouvert->value,
        ],
        TicketStatus::AValider->value => [
            TicketStatus::Cloture->value,
            TicketStatus::Reouvert->value,
        ],
        TicketStatus::Reouvert->value => [
            TicketStatus::Affecte->value,
            TicketStatus::PrisEnCharge->value,
            TicketStatus::EnCours->value,
        ],
        TicketStatus::Cloture->value => [],
        TicketStatus::Annule->value => [],
    ];

    public function canTransition(TicketStatus $currentStatus, TicketStatus $newStatus): bool
    {
        $allowed = $this->transitions[$currentStatus->value] ?? [];

        return in_array($newStatus->value, $allowed, true);
    }

    public function assertCanTransition(TicketStatus $currentStatus, TicketStatus $newStatus): void
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

    public function transition(TicketStatus $currentStatus, TicketStatus $newStatus): TicketStatus
    {
        $this->assertCanTransition($currentStatus, $newStatus);

        return $newStatus;
    }

    /**
     * @return list<TicketStatus>
     */
    public function allowedTransitions(TicketStatus $currentStatus): array
    {
        $allowed = $this->transitions[$currentStatus->value] ?? [];

        return array_map(fn (string $value) => TicketStatus::from($value), $allowed);
    }
}
