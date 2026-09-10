<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;

class DocumentAccessService
{
    public function __construct(
        private readonly DelegationResolver $delegations,
    ) {}

    public function canAccess(User $user, Document $document): bool
    {
        if ($user->can('admin.access')) {
            return true;
        }

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
}