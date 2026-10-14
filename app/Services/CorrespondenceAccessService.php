<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Models\Correspondence;
use App\Models\User;
use App\Services\Organization\OrganizationalContextResolver;

class CorrespondenceAccessService
{
    public function __construct(
        private readonly MailVisibilityScope $visibility,
        private readonly OrganizationalContextResolver $contexts,
    ) {}

    /**
     * Vérifie si l'utilisateur peut consulter la correspondance.
     */
    public function canView(User $user, Correspondence $correspondence): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (! $this->contexts->allowsPermission($user, 'mail.view') && ! $this->contexts->allowsPermission($user, 'mail.view_all')) {
            return false;
        }

        if (! $this->canAccessConfidentiality($user, $correspondence)) {
            return false;
        }

        return $this->visibility->allows($user, $correspondence);
    }

    /**
     * Vérifie l'accès selon la confidentialité.
     */
    public function canAccessConfidentiality(User $user, Correspondence $correspondence): bool
    {
        $confidentiality = $correspondence->confidentiality;

        if (! $confidentiality || $confidentiality === DocumentConfidentiality::Normal) {
            return true;
        }

        if ($confidentiality === DocumentConfidentiality::Confidentiel) {
            return $this->contexts->allowsPermission($user, 'mail.view_confidential')
                || $this->contexts->allowsPermission($user, 'mail.view_very_confidential');
        }

        if ($confidentiality === DocumentConfidentiality::TresConfidentiel) {
            return $this->contexts->allowsPermission($user, 'mail.view_very_confidential');
        }

        return true;
    }

    public function canEdit(User $user, Correspondence $correspondence): bool
    {
        if (! $this->canView($user, $correspondence)) {
            return false;
        }

        return $this->contexts->allowsPermission($user, 'mail.update');
    }

    public function canAssign(User $user, Correspondence $correspondence): bool
    {
        if (! $this->canView($user, $correspondence)) {
            return false;
        }

        return $this->contexts->allowsPermission($user, 'mail.assign');
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

        return $hasActiveAssignment && $this->contexts->allowsPermission($user, 'mail.process');
    }
}
