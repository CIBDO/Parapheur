<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use InvalidArgumentException;

class TaskStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        TaskStatus::Brouillon->value => [
            TaskStatus::Imputee->value,
            TaskStatus::Annulee->value,
        ],
        TaskStatus::Imputee->value => [
            TaskStatus::PriseEnCharge->value,
            TaskStatus::EnCours->value,
            TaskStatus::Annulee->value,
        ],
        TaskStatus::PriseEnCharge->value => [
            TaskStatus::EnCours->value,
            TaskStatus::EnAttente->value,
            TaskStatus::Annulee->value,
        ],
        TaskStatus::EnCours->value => [
            TaskStatus::EnAttente->value,
            TaskStatus::Terminee->value,
            TaskStatus::Annulee->value,
        ],
        TaskStatus::EnAttente->value => [
            TaskStatus::EnCours->value,
            TaskStatus::Annulee->value,
        ],
        TaskStatus::Terminee->value => [
            TaskStatus::AValider->value,
            TaskStatus::Validee->value,
        ],
        TaskStatus::AValider->value => [
            TaskStatus::Validee->value,
            TaskStatus::Retournee->value,
        ],
        TaskStatus::Retournee->value => [
            TaskStatus::EnCours->value,
            TaskStatus::Terminee->value,
            TaskStatus::Annulee->value,
        ],
        TaskStatus::Validee->value => [],
        TaskStatus::Annulee->value => [],
    ];

    public function canTransition(TaskStatus $current, TaskStatus $next): bool
    {
        $allowed = $this->transitions[$current->value] ?? [];

        return in_array($next->value, $allowed, true);
    }

    public function assertCanTransition(TaskStatus $current, TaskStatus $next): void
    {
        if (! $this->canTransition($current, $next)) {
            throw new InvalidArgumentException(
                sprintf('Transition invalide : %s → %s', $current->label(), $next->label())
            );
        }
    }

    /**
     * @return list<TaskStatus>
     */
    public function allowedTransitions(TaskStatus $current): array
    {
        $allowed = $this->transitions[$current->value] ?? [];

        return array_map(fn (string $v) => TaskStatus::from($v), $allowed);
    }
}
