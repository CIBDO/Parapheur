<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Organization\OrganizationalContextResolver;

class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('document_template.view') || $user->can('admin.access');
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        $can = $user->can('document_template.view')
            || $user->can('document_template.view_all')
            || $user->can('admin.access');

        if (! $can) {
            return false;
        }

        if (! $template->structure_id) {
            return true;
        }

        $ids = app(OrganizationalContextResolver::class)->resolve($user)->structureIds;

        return in_array((int) $template->structure_id, $ids, true);
    }

    public function create(User $user): bool
    {
        return $user->can('document_template.create') || $user->can('admin.access');
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        if (! $this->view($user, $template)) {
            return false;
        }

        // Admin peut tout modifier
        if ($user->can('admin.access')) {
            return true;
        }

        // Modèles de la structure de l'utilisateur
        if ($template->structure_id && in_array((int) $template->structure_id, app(OrganizationalContextResolver::class)->resolve($user)->structureIds, true)) {
            return $user->can('document_template.update');
        }

        return false;
    }

    public function delete(User $user, DocumentTemplate $template): bool
    {
        return $this->update($user, $template) && $user->can('document_template.delete');
    }

    public function publish(User $user, DocumentTemplate $template): bool
    {
        if (! $this->view($user, $template)) {
            return false;
        }

        return $user->can('document_template.publish') || $user->can('admin.access');
    }
}
