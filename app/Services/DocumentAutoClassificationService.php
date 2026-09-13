<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentClassificationRule;
use App\Models\User;

class DocumentAutoClassificationService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Applique la première règle active correspondant au document (type + structure).
     */
    public function applyAfterStatus(Document $document, string $status, ?User $actor = null): bool
    {
        if ($document->classification_node_id) {
            return false;
        }

        $rule = DocumentClassificationRule::query()
            ->where('is_active', true)
            ->where('trigger_status', $status)
            ->where(function ($q) use ($document) {
                $q->whereNull('document_type_id')
                    ->orWhere('document_type_id', $document->document_type_id);
            })
            ->where(function ($q) use ($document) {
                $q->whereNull('structure_id')
                    ->orWhere('structure_id', $document->structure_id);
            })
            ->orderBy('priority')
            ->first();

        if (! $rule) {
            return false;
        }

        $actor ??= $document->author;
        if (! $actor) {
            return false;
        }

        $this->documents->assignClassification($document, $actor, $rule->target_classification_node_id);
        $this->audit->log('document.auto_classified', $document, [
            'rule_id' => $rule->id,
            'node_id' => $rule->target_classification_node_id,
            'trigger' => $status,
        ]);

        return true;
    }
}
