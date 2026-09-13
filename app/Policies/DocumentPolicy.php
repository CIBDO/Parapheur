<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentAccessService;

/**
 * Policy documentaire — délègue à DocumentAccessService.
 */
class DocumentPolicy
{
    public function __construct(private DocumentAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('ged.view')
            || $user->can('ged.search')
            || $user->can('documents.create')
            || $user->can('documents.act')
            || $user->can('admin.access')
            || $user->can('dashboard.dg');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->access->canAccess($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('ged.create')
            || $user->can('documents.create')
            || $user->can('admin.access');
    }

    public function update(User $user, Document $document): bool
    {
        return $this->access->canEdit($user, $document)
            && ($user->can('ged.update') || $user->can('documents.act') || $user->can('admin.access') || (int) $document->author_id === (int) $user->id);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->access->canDownload($user, $document);
    }

    public function share(User $user, Document $document): bool
    {
        return $this->access->canShare($user, $document);
    }

    public function classify(User $user, Document $document): bool
    {
        return $this->access->canAccess($user, $document)
            && ($user->can('ged.classify') || $user->can('admin.access') || (int) $document->author_id === (int) $user->id);
    }

    public function archive(User $user, Document $document): bool
    {
        return $this->access->canAccess($user, $document)
            && ($user->can('ged.archive') || $user->can('admin.access') || $user->can('documents.validate'));
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('admin.access')
            || ((int) $document->author_id === (int) $user->id && $user->can('ged.create'));
    }

    public function manageClassification(User $user): bool
    {
        return $user->can('ged.manage_classification') || $user->can('admin.access');
    }

    public function manageCategories(User $user): bool
    {
        return $user->can('ged.manage_categories') || $user->can('admin.access');
    }
}
