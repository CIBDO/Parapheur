<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Models\Document;
use App\Models\User;

class DocumentAccessService
{
    public function __construct(
        private readonly DelegationResolver $delegations,
    ) {}

    public function canAccess(User $user, Document $document): bool
    {
        if ($this->isCircuitParty($user, $document)) {
            return true;
        }

        return $this->hasViewClearance($user, $document);
    }

    /**
     * Destinataire autorisé à recevoir le dossier selon le niveau de classification.
     */
    public function canReceive(User $recipient, Document $document): bool
    {
        $confidentiality = $this->resolveConfidentiality($document);

        return match ($confidentiality) {
            DocumentConfidentiality::TresConfidentiel => $recipient->can('admin.access')
                || $recipient->can('dashboard.dg')
                || $recipient->can('documents.validate'),
            DocumentConfidentiality::Confidentiel => $recipient->can('admin.access')
                || $recipient->can('dashboard.dg')
                || $recipient->can('documents.validate')
                || $recipient->can('documents.vise'),
            default => $recipient->can('documents.act')
                || $recipient->can('documents.create')
                || $recipient->can('admin.access'),
        };
    }

    /**
     * Actions de traitement (viser, valider, retourner…) : destinataire courant,
     * délégataire actif, ou administrateur.
     */
    public function canProcess(User $user, Document $document, string $action = 'act'): bool
    {
        if ($user->can('admin.access')) {
            return true;
        }

        if ((int) $document->current_assignee_id === (int) $user->id) {
            return true;
        }

        // Destinataire parallèle (transmission libre multi-destinataires).
        if ($document->transmissions()
            ->where('to_user_id', $user->id)
            ->whereIn('status', ['pending', 'seen'])
            ->exists()) {
            return true;
        }

        $delegator = $this->delegations->resolveDelegator($user, $document, $action);

        return $delegator !== null
            && (int) $delegator->id === (int) $document->current_assignee_id;
    }

    /**
     * Transmission : destinataire courant, ou auteur tant que le dossier n’est pas figé
     * chez un tiers (brouillon / correction).
     */
    public function canTransmit(User $user, Document $document): bool
    {
        if ($this->canProcess($user, $document, 'act')) {
            return true;
        }

        if ((int) $document->author_id !== (int) $user->id) {
            return false;
        }

        $status = $document->status?->value ?? (string) $document->status;

        return in_array($status, ['brouillon', 'depose', 'a_corriger', 'corrige'], true);
    }

    public function authorize(User $user, Document $document): void
    {
        abort_unless($this->canAccess($user, $document), 403, 'Accès au document refusé.');
    }

    public function authorizeReceive(User $recipient, Document $document): void
    {
        abort_unless(
            $this->canReceive($recipient, $document),
            403,
            'Le destinataire n’a pas le niveau d’habilitation requis pour ce document.'
        );
    }

    public function authorizeProcess(User $user, Document $document, string $action = 'act'): void
    {
        $this->authorize($user, $document);
        abort_unless(
            $this->canProcess($user, $document, $action),
            403,
            'Action réservée au destinataire actuel du dossier.'
        );
    }

    public function authorizeTransmit(User $user, Document $document): void
    {
        $this->authorize($user, $document);
        abort_unless(
            $this->canTransmit($user, $document),
            403,
            'Transmission réservée au destinataire actuel (ou à l’auteur avant prise en charge).'
        );
    }

    /**
     * Partie prenante du circuit (need-to-know) : auteur, destinataire courant, historique de transmission.
     */
    public function isCircuitParty(User $user, Document $document): bool
    {
        if ((int) $document->author_id === (int) $user->id) {
            return true;
        }

        if ((int) $document->current_assignee_id === (int) $user->id) {
            return true;
        }

        return $document->transmissions()
            ->where(function ($query) use ($user) {
                $query->where('to_user_id', $user->id)
                    ->orWhere('from_user_id', $user->id);
            })
            ->exists();
    }

    /**
     * Accès hors circuit selon la classification (aligné réunions / agenda).
     */
    private function hasViewClearance(User $user, Document $document): bool
    {
        $confidentiality = $this->resolveConfidentiality($document);

        if ($confidentiality === DocumentConfidentiality::TresConfidentiel) {
            return $user->can('admin.access');
        }

        if ($confidentiality === DocumentConfidentiality::Confidentiel) {
            return $user->can('admin.access') || $user->can('dashboard.dg');
        }

        if ($confidentiality === DocumentConfidentiality::Restreint) {
            if ($user->can('admin.access') || $user->can('dashboard.dg')) {
                return true;
            }

            return (int) $user->structure_id === (int) $document->structure_id
                && ($user->can('documents.act') || $user->can('documents.create'));
        }

        return $user->can('admin.access') || $user->can('dashboard.dg');
    }

    private function resolveConfidentiality(Document $document): DocumentConfidentiality
    {
        if ($document->confidentiality instanceof DocumentConfidentiality) {
            return $document->confidentiality;
        }

        return DocumentConfidentiality::tryFrom((string) $document->confidentiality)
            ?? DocumentConfidentiality::Normal;
    }
}
