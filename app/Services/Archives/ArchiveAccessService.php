<?php

namespace App\Services\Archives;

use App\Enums\DocumentConfidentiality;
use App\Models\ArchiveAccessGrant;
use App\Models\ArchiveRecord;
use App\Models\ArchiveTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ArchiveAccessService
{
    public function canViewModule(User $user): bool
    {
        return $user->can('archives.consulter')
            || $user->can('archives.rechercher')
            || $user->can('archives.verser')
            || $user->can('archives.administrer')
            || $user->can('admin.access');
    }

    public function canViewTransfer(User $user, ArchiveTransfer $transfer): bool
    {
        if (! $this->canViewModule($user)) {
            return false;
        }

        if ($user->can('admin.access') || $user->can('archives.administrer') || $user->can('archives.valider_versement')) {
            return true;
        }

        if ((int) $transfer->created_by === (int) $user->id) {
            return true;
        }

        return $this->sameStructureBoundary($user, (int) $transfer->structure_id);
    }

    public function canViewRecord(User $user, ArchiveRecord $record): bool
    {
        if (! $this->canViewModule($user)) {
            return false;
        }

        if (! $this->passesConfidentiality($user, $record->confidentiality)) {
            return false;
        }

        if ($user->can('admin.access') || $user->can('archives.administrer')) {
            return true;
        }

        if ($this->hasActiveGrant($user, $record, 'view')) {
            return true;
        }

        return $this->sameStructureBoundary($user, (int) $record->structure_id);
    }

    public function canDownloadRecord(User $user, ArchiveRecord $record): bool
    {
        if (! $this->canViewRecord($user, $record)) {
            return false;
        }

        if ($user->can('archives.telecharger') || $user->can('admin.access') || $user->can('archives.administrer')) {
            return true;
        }

        return $this->hasActiveGrant($user, $record, 'download');
    }

    public function hasActiveGrant(User $user, ArchiveRecord $record, string $ability = 'view'): bool
    {
        return ArchiveAccessGrant::query()
            ->where('record_id', $record->id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $q) use ($ability) {
                $q->where('ability', $ability);
                if ($ability === 'view') {
                    $q->orWhere('ability', 'download');
                }
            })
            ->exists();
    }

    /**
     * @param  Builder<ArchiveTransfer>  $query
     * @return Builder<ArchiveTransfer>
     */
    public function scopeTransfersFor(User $user, Builder $query): Builder
    {
        if ($user->can('admin.access') || $user->can('archives.administrer') || $user->can('archives.valider_versement')) {
            return $query;
        }

        $structureId = (int) ($user->structure_id ?? 0);

        return $query->where(function (Builder $q) use ($user, $structureId) {
            $q->where('created_by', $user->id);
            if ($structureId > 0) {
                $q->orWhere('structure_id', $structureId);
            }
        });
    }

    /**
     * @param  Builder<ArchiveRecord>  $query
     * @return Builder<ArchiveRecord>
     */
    public function scopeRecordsFor(User $user, Builder $query): Builder
    {
        if ($user->can('admin.access') || $user->can('archives.administrer')) {
            return $this->applyConfidentialityFilter($user, $query);
        }

        $structureId = (int) ($user->structure_id ?? 0);
        $query->where('structure_id', $structureId > 0 ? $structureId : -1);

        return $this->applyConfidentialityFilter($user, $query);
    }

    private function sameStructureBoundary(User $user, int $structureId): bool
    {
        if ($structureId <= 0) {
            return false;
        }

        return (int) ($user->structure_id ?? 0) === $structureId;
    }

    private function passesConfidentiality(User $user, ?string $level): bool
    {
        $level = $level ?: DocumentConfidentiality::Normal->value;

        if ($level === DocumentConfidentiality::TresConfidentiel->value) {
            return $user->can('mail.view_very_confidential')
                || $user->can('admin.access')
                || $user->can('archives.administrer');
        }

        if ($level === DocumentConfidentiality::Confidentiel->value) {
            return $user->can('mail.view_confidential')
                || $user->can('admin.access')
                || $user->can('archives.administrer')
                || $user->can('archives.valider_versement');
        }

        return true;
    }

    /**
     * @param  Builder<ArchiveRecord>  $query
     * @return Builder<ArchiveRecord>
     */
    private function applyConfidentialityFilter(User $user, Builder $query): Builder
    {
        if ($user->can('admin.access') || $user->can('archives.administrer') || $user->can('mail.view_very_confidential')) {
            return $query;
        }

        if ($user->can('mail.view_confidential') || $user->can('archives.valider_versement')) {
            return $query->where('confidentiality', '!=', DocumentConfidentiality::TresConfidentiel->value);
        }

        return $query->whereNotIn('confidentiality', [
            DocumentConfidentiality::Confidentiel->value,
            DocumentConfidentiality::TresConfidentiel->value,
        ]);
    }
}
