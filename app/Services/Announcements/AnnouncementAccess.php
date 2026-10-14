<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementConfidentiality;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;

final class AnnouncementAccess
{
    public function canView(User $user, Announcement $announcement): bool
    {
        if (! $user->can('announcement.view') && ! $user->can('announcement.manage')) {
            return false;
        }

        if ($announcement->status->isPublic() && $this->isCurrentRecipient($user, $announcement)) {
            return true;
        }

        if ($announcement->confidentiality->isSensitive()) {
            return $this->isStakeholder($user, $announcement);
        }

        return $this->canManageRecord($user, $announcement);
    }

    public function canManageRecord(User $user, Announcement $announcement): bool
    {
        if ((int) $announcement->author_id === (int) $user->id && ($user->can('announcement.update') || $user->can('announcement.create'))) {
            return true;
        }

        if ((int) $announcement->validator_id === (int) $user->id && $user->can('announcement.approve')) {
            return true;
        }

        if ((int) $announcement->publisher_id === (int) $user->id && $user->can('announcement.publish')) {
            return true;
        }

        if ($user->can('announcement.approve') && (int) $announcement->structure_id === (int) $user->structure_id) {
            return true;
        }

        if ($this->isInstitutionalActor($user) && ($user->can('announcement.publish') || $user->can('announcement.approve') || $user->can('announcement.manage'))) {
            return ! $announcement->confidentiality->isSensitive() || $this->isStakeholder($user, $announcement);
        }

        return $user->can('announcement.manage') && ! $announcement->confidentiality->isSensitive();
    }

    public function canUpdate(User $user, Announcement $announcement): bool
    {
        if (! $user->can('announcement.update') && ! $user->can('announcement.manage')) {
            return false;
        }

        if ($announcement->confidentiality->isSensitive() && ! $this->isStakeholder($user, $announcement) && (int) $announcement->author_id !== (int) $user->id) {
            return false;
        }

        return $this->canManageRecord($user, $announcement) || (int) $announcement->author_id === (int) $user->id;
    }

    public function canSubmit(User $user, Announcement $announcement): bool
    {
        return ($user->can('announcement.submit') || $user->can('announcement.publish'))
            && (int) $announcement->author_id === (int) $user->id;
    }

    public function canApprove(User $user, Announcement $announcement): bool
    {
        if ($announcement->status !== AnnouncementStatus::PendingValidation) {
            return false;
        }

        if ((int) $announcement->validator_id === (int) $user->id && $user->can('announcement.approve')) {
            return true;
        }

        $circuit = $announcement->category?->validation_circuit?->value;
        if ($circuit === 'institutional' && (int) $announcement->approval_step >= 2) {
            return $user->can('announcement.publish') && $this->isInstitutionalActor($user);
        }

        if ($circuit === 'institutional') {
            return $user->can('announcement.approve') && $this->isInstitutionalActor($user);
        }

        return $user->can('announcement.approve')
            && ((int) $announcement->structure_id === (int) $user->structure_id || $this->isInstitutionalActor($user));
    }

    public function canPublish(User $user, Announcement $announcement): bool
    {
        if (! $user->can('announcement.publish') && ! $user->can('announcement.schedule') && ! $user->can('announcement.create')) {
            return false;
        }

        if ($announcement->confidentiality->isSensitive() && ! $this->isStakeholder($user, $announcement) && (int) $announcement->author_id !== (int) $user->id) {
            return false;
        }

        return (int) $announcement->author_id === (int) $user->id
            || $this->isInstitutionalActor($user)
            || $user->can('announcement.manage')
            || (int) $announcement->structure_id === (int) $user->structure_id;
    }

    public function canViewReceipts(User $user, Announcement $announcement): bool
    {
        if (! $user->can('announcement.view_read_receipts') && ! $user->can('announcement.manage')) {
            return false;
        }

        if ($announcement->confidentiality === AnnouncementConfidentiality::Confidential && ! $this->isStakeholder($user, $announcement) && ! $this->isCurrentRecipient($user, $announcement)) {
            return false;
        }

        if ($this->seesAllStatistics($user)) {
            return true;
        }

        return (int) $announcement->structure_id === (int) $user->structure_id
            || (int) $announcement->author_id === (int) $user->id;
    }

    public function seesAllStatistics(User $user): bool
    {
        return $user->hasAnyRole(['Directeur Général', 'DGA', 'Secrétariat DG', 'Administrateur'])
            || $user->can('announcement.manage');
    }

    public function hidesSensitiveAggregates(User $user): bool
    {
        return $user->can('admin.access') && ! $user->hasAnyRole(['Directeur Général', 'DGA', 'Secrétariat DG']);
    }

    public function isCurrentRecipient(User $user, Announcement $announcement): bool
    {
        if ($announcement->relationLoaded('recipients')) {
            return $announcement->recipients->contains(fn ($row) => (int) $row->user_id === (int) $user->id
                && (int) $row->version === (int) $announcement->current_version);
        }

        return $announcement->recipients()
            ->where('user_id', $user->id)
            ->where('version', $announcement->current_version)
            ->exists();
    }

    public function isStakeholder(User $user, Announcement $announcement): bool
    {
        if (in_array((int) $user->id, array_filter([
            (int) $announcement->author_id,
            (int) $announcement->publisher_id,
            (int) $announcement->validator_id,
        ]), true)) {
            return true;
        }

        return $announcement->approvals()->where('actor_id', $user->id)->exists();
    }

    public function isInstitutionalActor(User $user): bool
    {
        return $user->hasAnyRole(['Directeur Général', 'DGA', 'Secrétariat DG', 'Administrateur']);
    }
}
