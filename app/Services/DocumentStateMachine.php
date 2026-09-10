<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\WorkflowActionType;
use InvalidArgumentException;

class DocumentStateMachine
{
    /**
     * Graphe des transitions métier (arêtes directes).
     * Les actions UI (classer, valider…) peuvent emprunter un chemin
     * d’étapes intermédiaires via {@see path()}.
     *
     * @var array<string, list<string>>
     */
    private array $transitions = [
        DocumentStatus::Brouillon->value => [
            DocumentStatus::Depose->value,
            DocumentStatus::Annule->value,
        ],
        DocumentStatus::Depose->value => [
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::Annule->value,
        ],
        DocumentStatus::EnCircuit->value => [
            DocumentStatus::Transmis->value,
            DocumentStatus::AConsulter->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::EnAttente->value,
            DocumentStatus::Traite->value,
        ],
        DocumentStatus::Transmis->value => [
            DocumentStatus::AConsulter->value,
            DocumentStatus::EnConsultation->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::EnAttente->value,
        ],
        DocumentStatus::AConsulter->value => [
            DocumentStatus::EnConsultation->value,
            DocumentStatus::EnAttente->value,
            DocumentStatus::ACorriger->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::Traite->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Rejete->value,
        ],
        DocumentStatus::EnConsultation->value => [
            DocumentStatus::ACorriger->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::EnAttente->value,
            DocumentStatus::Traite->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Rejete->value,
        ],
        DocumentStatus::EnAttente->value => [
            DocumentStatus::AConsulter->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::EnConsultation->value,
            DocumentStatus::Traite->value,
            DocumentStatus::ACorriger->value,
        ],
        DocumentStatus::ACorriger->value => [
            DocumentStatus::Corrige->value,
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::AConsulter->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::Annule->value,
        ],
        DocumentStatus::Corrige->value => [
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::AConsulter->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
        ],
        DocumentStatus::AViser->value => [
            DocumentStatus::Vise->value,
            DocumentStatus::ACorriger->value,
            DocumentStatus::Rejete->value,
            DocumentStatus::EnAttente->value,
            DocumentStatus::EnConsultation->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::Traite->value,
        ],
        DocumentStatus::Vise->value => [
            DocumentStatus::AValider->value,
            DocumentStatus::AConsulter->value,
            DocumentStatus::AViser->value,
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::Traite->value,
            DocumentStatus::Classe->value,
        ],
        DocumentStatus::AValider->value => [
            DocumentStatus::Valide->value,
            DocumentStatus::Rejete->value,
            DocumentStatus::ACorriger->value,
            DocumentStatus::EnAttente->value,
            DocumentStatus::EnConsultation->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::Traite->value,
        ],
        DocumentStatus::Valide->value => [
            DocumentStatus::Traite->value,
            DocumentStatus::Classe->value,
            DocumentStatus::Archive->value,
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::AConsulter->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
        ],
        DocumentStatus::Rejete->value => [
            DocumentStatus::ACorriger->value,
            DocumentStatus::Annule->value,
            DocumentStatus::Classe->value,
            DocumentStatus::Traite->value,
        ],
        DocumentStatus::Traite->value => [
            DocumentStatus::Classe->value,
            DocumentStatus::Archive->value,
        ],
        DocumentStatus::Classe->value => [
            DocumentStatus::Archive->value,
        ],
        DocumentStatus::Archive->value => [],
        DocumentStatus::Annule->value => [],
    ];

    public function canTransition(DocumentStatus $from, DocumentStatus $to): bool
    {
        return in_array($to->value, $this->transitions[$from->value] ?? [], true);
    }

    /**
     * @return list<DocumentStatus>
     */
    public function allowedNext(DocumentStatus $from): array
    {
        return array_map(
            fn (string $value) => DocumentStatus::from($value),
            $this->transitions[$from->value] ?? [],
        );
    }

    /**
     * Chemin le plus court (BFS) de $from vers $to, bornes incluses.
     * Ex. En consultation → Traité → Classé.
     *
     * @return list<DocumentStatus>|null
     */
    public function path(DocumentStatus $from, DocumentStatus $to): ?array
    {
        if ($from === $to) {
            return [$from];
        }

        $queue = [[$from]];
        $visited = [$from->value => true];

        while ($queue !== []) {
            $currentPath = array_shift($queue);
            $last = $currentPath[array_key_last($currentPath)];

            foreach ($this->allowedNext($last) as $next) {
                if (isset($visited[$next->value])) {
                    continue;
                }

                $nextPath = [...$currentPath, $next];

                if ($next === $to) {
                    return $nextPath;
                }

                $visited[$next->value] = true;
                $queue[] = $nextPath;
            }
        }

        return null;
    }

    public function canReach(DocumentStatus $from, DocumentStatus $to): bool
    {
        return $this->path($from, $to) !== null;
    }

    public function assertCanTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        if ($this->canTransition($from, $to)) {
            return;
        }

        // Transition intuitive : autoriser si un chemin métier existe
        // (ex. Classer depuis « En consultation » via Traité → Classé).
        if ($this->canReach($from, $to)) {
            return;
        }

        $suggestions = array_map(
            fn (DocumentStatus $status) => $status->label(),
            $this->allowedNext($from),
        );

        $hint = $suggestions === []
            ? 'Aucune transition sortante n’est définie pour ce statut.'
            : 'Statuts directement accessibles : '.implode(', ', $suggestions).'.';

        throw new InvalidArgumentException(
            "Transition interdite de {$from->label()} vers {$to->label()}. {$hint}"
        );
    }

    /**
     * Étapes intermédiaires à appliquer pour atteindre $to (hors statut courant).
     *
     * @return list<DocumentStatus>
     */
    public function stepsTo(DocumentStatus $from, DocumentStatus $to): array
    {
        if ($from === $to) {
            return [];
        }

        $path = $this->path($from, $to);
        if ($path === null) {
            $this->assertCanTransition($from, $to);

            return [];
        }

        return array_slice($path, 1);
    }

    public function statusAfterAction(WorkflowActionType $action, DocumentStatus $current): DocumentStatus
    {
        return match ($action) {
            WorkflowActionType::PriseConnaissance => DocumentStatus::EnConsultation,
            WorkflowActionType::RetourCorrection, WorkflowActionType::DemandeComplement => DocumentStatus::ACorriger,
            WorkflowActionType::Viser => DocumentStatus::Vise,
            WorkflowActionType::Valider => DocumentStatus::Valide,
            WorkflowActionType::Rejeter => DocumentStatus::Rejete,
            WorkflowActionType::MettreEnAttente => DocumentStatus::EnAttente,
            WorkflowActionType::Classer => DocumentStatus::Classe,
            WorkflowActionType::Archiver => DocumentStatus::Archive,
            WorkflowActionType::Transmettre, WorkflowActionType::Reaffecter => DocumentStatus::Transmis,
            WorkflowActionType::Commenter, WorkflowActionType::Avis, WorkflowActionType::Recommandation, WorkflowActionType::Instruction => $current,
            default => $current,
        };
    }
}
