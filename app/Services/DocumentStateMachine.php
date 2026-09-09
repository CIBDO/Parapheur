<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\WorkflowActionType;
use InvalidArgumentException;

class DocumentStateMachine
{
    /**
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
        ],
        DocumentStatus::EnConsultation->value => [
            DocumentStatus::ACorriger->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::EnAttente->value,
            DocumentStatus::Traite->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::EnCircuit->value,
        ],
        DocumentStatus::EnAttente->value => [
            DocumentStatus::AConsulter->value,
            DocumentStatus::AViser->value,
            DocumentStatus::AValider->value,
            DocumentStatus::EnCircuit->value,
            DocumentStatus::Transmis->value,
            DocumentStatus::EnConsultation->value,
        ],
        DocumentStatus::ACorriger->value => [
            DocumentStatus::Corrige->value,
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

    public function assertCanTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException(
                "Transition interdite de {$from->label()} vers {$to->label()}."
            );
        }
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
