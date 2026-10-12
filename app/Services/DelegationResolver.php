<?php

namespace App\Services;

use App\Models\Delegation;
use App\Models\Document;
use App\Models\User;

class DelegationResolver
{
    /**
     * Délégués actifs qui peuvent consulter ou recevoir à la place du délégant.
     *
     * @return list<User>
     */
    public function activeDelegates(User $delegator, string $action = 'consultation'): array
    {
        $today = now()->toDateString();

        return Delegation::query()
            ->with('delegate')
            ->where('delegator_id', $delegator->id)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->get()
            ->filter(fn (Delegation $delegation) => $delegation->delegate && $this->allowsAction($delegation, $action))
            ->map(fn (Delegation $delegation) => $delegation->delegate)
            ->unique('id')
            ->values()
            ->all();
    }

    public function resolveDelegator(User $actor, Document $document, string $action): ?User
    {
        $today = now()->toDateString();

        $delegations = Delegation::query()
            ->with('delegator')
            ->where('delegate_id', $actor->id)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->get();

        foreach ($delegations as $delegation) {
            if (! $this->coversDocument($delegation, $document)) {
                continue;
            }

            if (! $this->allowsAction($delegation, $action)) {
                continue;
            }

            return $delegation->delegator;
        }

        return null;
    }

    private function coversDocument(Delegation $delegation, Document $document): bool
    {
        $types = $delegation->document_type_ids;
        if (! is_array($types) || $types === []) {
            return true;
        }

        return in_array((int) $document->document_type_id, array_map('intval', $types), true);
    }

    private function allowsAction(Delegation $delegation, string $action): bool
    {
        $allowed = $delegation->allowed_actions;
        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        $normalized = array_map('strval', $allowed);

        return in_array($action, $normalized, true)
            || in_array('*', $normalized, true)
            || in_array('all', $normalized, true);
    }
}
