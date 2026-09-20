<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Models\Correspondence;
use App\Models\User;

class CorrespondenceAccessService
{
    /**
     * Vérifie si l'utilisateur peut consulter la correspondance.
     */
    public function canView(User $user, Correspondence $correspondence): bool
    {
        // Admin a tous les droits
        if ($user->can('admin.access')) {
            return true;
        }

        // Permission générale (consultation limitée ou vue globale)
        if (! $user->can('mail.view') && ! $user->can('mail.view_all')) {
            return false;
        }

        // Vérifier la confidentialité (même avec view_all)
        if (! $this->canAccessConfidentiality($user, $correspondence)) {
            return false;
        }

        // Vue globale bureau courrier
        if ($user->can('mail.view_all')) {
            return true;
        }

        // L'utilisateur propriétaire peut toujours voir
        if ($correspondence->owner_user_id === $user->id) {
            return true;
        }

        // L'utilisateur qui a enregistré peut voir
        if ($correspondence->registered_by === $user->id) {
            return true;
        }

        // Membre de la structure propriétaire
        if ($correspondence->structure_id && $user->structure_id === $correspondence->structure_id) {
            return true;
        }

        // A une affectation
        return $correspondence->assignments()
            ->where('to_user_id', $user->id)
            ->exists();
    }

    /**
     * Vérifie l'accès selon la confidentialité.
     */
    protected function canAccessConfidentiality(User $user, Correspondence $correspondence): bool
    {
        $confidentiality = $correspondence->confidentiality;

        if (! $confidentiality || $confidentiality === DocumentConfidentiality::Normal) {
            return true;
        }

        if ($confidentiality === DocumentConfidentiality::Confidentiel) {
            return $user->can('mail.view_confidential') || $user->can('admin.access');
        }

        if ($confidentiality === DocumentConfidentiality::TresConfidentiel) {
            return $user->can('mail.view_very_confidential') || $user->can('admin.access');
        }

        return true;
    }

    public function canEdit(User $user, Correspondence $correspondence): bool
    {
        if (! $this->canView($user, $correspondence)) {
            return false;
        }

        return $user->can('mail.update') || $user->can('admin.access');
    }

    public function canAssign(User $user, Correspondence $correspondence): bool
    {
        if (! $this->canView($user, $correspondence)) {
            return false;
        }

        return $user->can('mail.assign') || $user->can('admin.access');
    }

    public function canProcess(User $user, Correspondence $correspondence): bool
    {
        if (! $this->canView($user, $correspondence)) {
            return false;
        }

        // Doit avoir une affectation active
        $hasActiveAssignment = $correspondence->assignments()
            ->where('to_user_id', $user->id)
            ->whereIn('status', ['transmis', 'recu', 'pris_en_charge'])
            ->exists();

        return $hasActiveAssignment && $user->can('mail.process');
    }
}
