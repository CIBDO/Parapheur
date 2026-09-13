<?php

namespace App\Policies;

use App\Models\Correspondence;
use App\Models\User;
use App\Services\CorrespondenceAccessService;

class CorrespondencePolicy
{
    public function __construct(
        private readonly CorrespondenceAccessService $accessService
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('mail.view') || $user->can('admin.access');
    }

    public function view(User $user, Correspondence $correspondence): bool
    {
        return $this->accessService->canView($user, $correspondence);
    }

    public function create(User $user): bool
    {
        return $user->can('mail.create') || $user->can('admin.access');
    }

    public function update(User $user, Correspondence $correspondence): bool
    {
        return $this->accessService->canEdit($user, $correspondence);
    }

    public function delete(User $user, Correspondence $correspondence): bool
    {
        if (! $this->accessService->canEdit($user, $correspondence)) {
            return false;
        }

        // Seuls les courriers en statut très précoce peuvent être supprimés
        if (! in_array($correspondence->status?->value, ['recu', 'enregistre'], true)) {
            return false;
        }

        return $user->can('mail.delete') || $user->can('admin.access');
    }

    public function assign(User $user, Correspondence $correspondence): bool
    {
        return $this->accessService->canAssign($user, $correspondence);
    }

    public function takeCharge(User $user, Correspondence $correspondence): bool
    {
        return $this->accessService->canProcess($user, $correspondence);
    }

    public function reply(User $user, Correspondence $correspondence): bool
    {
        if (! $this->accessService->canView($user, $correspondence)) {
            return false;
        }

        return $user->can('mail.reply') || $user->can('admin.access');
    }

    public function dispatch(User $user, Correspondence $correspondence): bool
    {
        if (! $this->accessService->canView($user, $correspondence)) {
            return false;
        }

        return $user->can('mail.dispatch') || $user->can('admin.access');
    }

    public function archive(User $user, Correspondence $correspondence): bool
    {
        if (! $this->accessService->canView($user, $correspondence)) {
            return false;
        }

        return $user->can('mail.archive') || $user->can('admin.access');
    }
}
