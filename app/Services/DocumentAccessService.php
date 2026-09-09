<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;

class DocumentAccessService
{
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

    public function authorize(User $user, Document $document): void
    {
        abort_unless($this->canAccess($user, $document), 403, 'Accès au document refusé.');
    }
}
