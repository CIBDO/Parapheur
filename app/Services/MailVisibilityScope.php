<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Models\Correspondence;
use App\Models\User;
use App\Services\Organization\OrganizationalContextResolver;
use Illuminate\Database\Eloquent\Builder;

class MailVisibilityScope
{
    public function __construct(
        private readonly OrganizationalContextResolver $contexts,
    ) {}

    public function apply(Builder $query, User $user): Builder
    {
        $this->constrainOwner($query, $user);
        $this->constrainConfidentiality($query, $user);

        return $query;
    }

    public function allows(User $user, Correspondence $correspondence): bool
    {
        if ($correspondence->owner_user_id === $user->id) {
            return true;
        }

        if ($correspondence->assignments()->where('to_user_id', $user->id)->exists()) {
            return true;
        }

        if ($correspondence->participants()->active()->where('user_id', $user->id)->exists()) {
            return true;
        }

        $ids = $this->contexts->resolve($user)->structureIds;

        if ($correspondence->structure_id === null && $correspondence->unit_structure_id === null) {
            return $correspondence->registered_by === $user->id;
        }

        if ($ids !== [] && $correspondence->structure_id && in_array((int) $correspondence->structure_id, $ids, true)) {
            return true;
        }

        if ($ids !== [] && $correspondence->unit_structure_id && in_array((int) $correspondence->unit_structure_id, $ids, true)) {
            return true;
        }

        if ($ids === []) {
            return false;
        }

        return $correspondence->participants()->active()->whereIn('structure_id', $ids)->exists();
    }

    public function applyToSlips(Builder $query, User $user): Builder
    {
        $ids = $this->contexts->resolve($user)->structureIds;

        return $query->where(function (Builder $q) use ($user, $ids) {
            $q->where('created_by', $user->id);

            if ($ids !== []) {
                $q->orWhereIn('from_structure_id', $ids)
                    ->orWhereIn('to_structure_id', $ids);
            }
        });
    }

    private function constrainOwner(Builder $query, User $user): void
    {
        $ids = $this->contexts->resolve($user)->structureIds;

        $query->where(function (Builder $q) use ($user, $ids) {
            $q->where('owner_user_id', $user->id)
                ->orWhereHas('assignments', fn (Builder $a) => $a->where('to_user_id', $user->id))
                ->orWhereHas('participants', fn (Builder $p) => $p->active()->where('user_id', $user->id))
                ->orWhere(function (Builder $legacy) use ($user) {
                    $legacy->whereNull('structure_id')
                        ->whereNull('unit_structure_id')
                        ->where('registered_by', $user->id);
                });

            if ($ids !== []) {
                $q->orWhereIn('structure_id', $ids)
                    ->orWhereIn('unit_structure_id', $ids)
                    ->orWhereHas('participants', fn (Builder $p) => $p->active()->whereIn('structure_id', $ids));
            }
        });
    }

    private function constrainConfidentiality(Builder $query, User $user): void
    {
        $canConfidential = $this->contexts->allowsPermission($user, 'mail.view_confidential')
            || $this->contexts->allowsPermission($user, 'mail.view_very_confidential');
        $canVery = $this->contexts->allowsPermission($user, 'mail.view_very_confidential');

        if (! $canConfidential) {
            $query->where(function (Builder $q) {
                $q->whereNull('confidentiality')
                    ->orWhereNotIn('confidentiality', [
                        DocumentConfidentiality::Confidentiel->value,
                        DocumentConfidentiality::TresConfidentiel->value,
                    ]);
            });

            return;
        }

        if (! $canVery) {
            $query->where(function (Builder $q) {
                $q->whereNull('confidentiality')
                    ->orWhere('confidentiality', '!=', DocumentConfidentiality::TresConfidentiel->value);
            });
        }
    }
}
