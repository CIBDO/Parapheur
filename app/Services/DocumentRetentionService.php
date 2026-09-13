<?php

namespace App\Services;

use App\Enums\DocumentArchiveStatus;
use App\Models\Document;
use App\Models\DocumentClassificationRule;
use App\Models\DocumentRetentionRule;
use App\Models\User;
use InvalidArgumentException;

class DocumentRetentionService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function applyMatchingRule(Document $document): ?DocumentRetentionRule
    {
        $rule = DocumentRetentionRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($document) {
                $q->where('document_type_id', $document->document_type_id)
                    ->orWhereNull('document_type_id');
            })
            ->where(function ($q) use ($document) {
                $q->where('category_id', $document->category_id)
                    ->orWhereNull('category_id');
            })
            ->orderByRaw('document_type_id is null')
            ->orderByRaw('category_id is null')
            ->first();

        if (! $rule) {
            return null;
        }

        $base = $document->document_date?->copy() ?? now();
        $document->retention_rule_id = $rule->id;
        $document->retention_years = $rule->retention_years;
        $document->retention_until = $base->copy()->addYears($rule->retention_years)->toDateString();
        $document->save();

        return $rule;
    }

    public function placeLegalHold(Document $document, User $actor, string $reason): Document
    {
        if ($reason === '') {
            throw new InvalidArgumentException('Motif de gel requis.');
        }

        $document->legal_hold_at = now();
        $document->legal_hold_reason = $reason;
        $document->archive_status = DocumentArchiveStatus::Gele;
        $document->save();

        $this->audit->log('document.legal_hold', $document, [
            'actor_id' => $actor->id,
            'reason' => $reason,
        ]);

        return $document->fresh();
    }

    public function releaseLegalHold(Document $document, User $actor): Document
    {
        $document->legal_hold_at = null;
        $document->legal_hold_reason = null;
        if ($document->archive_status === DocumentArchiveStatus::Gele) {
            $document->archive_status = $document->archived_at
                ? DocumentArchiveStatus::Archive
                : DocumentArchiveStatus::Actif;
        }
        $document->save();

        $this->audit->log('document.legal_hold_released', $document, [
            'actor_id' => $actor->id,
        ]);

        return $document->fresh();
    }

    /**
     * Antivirus : marqueur de statut (intégration moteur externe en P2).
     */
    public function markAntivirus(Document $document, string $status): Document
    {
        if (! in_array($status, ['pending', 'safe', 'infected', 'failed'], true)) {
            throw new InvalidArgumentException('Statut antivirus invalide.');
        }

        $document->antivirus_status = $status;
        $document->saveQuietly();

        if ($status === 'infected') {
            $this->audit->log('document.antivirus_infected', $document);
        }

        return $document;
    }

    public function assertNotInfected(Document $document): void
    {
        if ($document->antivirus_status === 'infected') {
            throw new InvalidArgumentException('Fichier identifié comme dangereux : accès refusé.');
        }
    }
}
