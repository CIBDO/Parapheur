<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFavorite;
use App\Models\DocumentView;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DocumentEngagementService
{
    public function __construct(
        private readonly DocumentAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function toggleFavorite(User $user, Document $document): bool
    {
        $this->access->authorize($user, $document);

        $existing = DocumentFavorite::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->audit->log('document.unfavorited', $document, ['user_id' => $user->id]);

            return false;
        }

        DocumentFavorite::query()->create([
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);
        $this->audit->log('document.favorited', $document, ['user_id' => $user->id]);

        return true;
    }

    public function isFavorite(User $user, Document $document): bool
    {
        return DocumentFavorite::query()
            ->where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->exists();
    }

    public function recordView(User $user, Document $document): void
    {
        if (! $this->access->canAccess($user, $document)) {
            return;
        }

        DocumentView::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'document_id' => $document->id,
            ],
            [
                'viewed_at' => now(),
            ]
        );
    }

    public function favorites(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $favIds = DocumentFavorite::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->pluck('document_id');

        $query = Document::query()
            ->with(['type', 'structure', 'author', 'latestVersion', 'tags'])
            ->whereIn('id', $favIds);

        $this->access->scopeVisibleTo($query, $user);

        $paginator = $query->paginate($perPage);
        // Réordonner selon l’ordre des favoris
        $ordered = $paginator->getCollection()->sortBy(fn ($d) => $favIds->search($d->id))->values();
        $paginator->setCollection($ordered);

        return $paginator;
    }

    /**
     * Récents : uniquement les documents encore accessibles.
     */
    public function recent(User $user, int $limit = 20): Collection
    {
        $viewedIds = DocumentView::query()
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->limit($limit * 3)
            ->pluck('document_id', 'viewed_at');

        if ($viewedIds->isEmpty()) {
            return collect();
        }

        $query = Document::query()
            ->with(['type', 'structure', 'author', 'latestVersion', 'tags'])
            ->whereIn('id', $viewedIds->values()->all());

        $this->access->scopeVisibleTo($query, $user);

        $docs = $query->get()->keyBy('id');

        return DocumentView::query()
            ->where('user_id', $user->id)
            ->whereIn('document_id', $docs->keys())
            ->orderByDesc('viewed_at')
            ->limit($limit)
            ->get()
            ->map(function (DocumentView $view) use ($docs) {
                $doc = $docs->get($view->document_id);
                if (! $doc) {
                    return null;
                }
                $doc->setAttribute('viewed_at', $view->viewed_at);

                return $doc;
            })
            ->filter()
            ->values();
    }
}
