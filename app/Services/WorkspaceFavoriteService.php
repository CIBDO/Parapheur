<?php

namespace App\Services;

use App\Enums\DocumentOrigin;
use App\Models\BibliographicReference;
use App\Models\Document;
use App\Models\DocumentView;
use App\Models\User;
use App\Models\WorkspaceFavorite;
use App\Models\WorkspaceFolder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WorkspaceFavoriteService
{
    /**
     * @return array{type: class-string<Model>, label: string}
     */
    public function resolveType(string $type): array
    {
        return match (strtolower($type)) {
            'document', Document::class => ['type' => Document::class, 'label' => 'document'],
            'folder', WorkspaceFolder::class => ['type' => WorkspaceFolder::class, 'label' => 'folder'],
            'reference', BibliographicReference::class => ['type' => BibliographicReference::class, 'label' => 'reference'],
            default => throw new InvalidArgumentException('Type de favori non supporté.'),
        };
    }

    public function toggle(User $user, string $type, int $id): array
    {
        $resolved = $this->resolveType($type);
        $model = $resolved['type']::query()->findOrFail($id);

        $existing = WorkspaceFavorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $resolved['type'])
            ->where('favoritable_id', $model->getKey())
            ->first();

        if ($existing) {
            $existing->delete();

            return ['favorited' => false, 'kind' => $resolved['label']];
        }

        WorkspaceFavorite::query()->create([
            'user_id' => $user->id,
            'favoritable_type' => $resolved['type'],
            'favoritable_id' => $model->getKey(),
        ]);

        return ['favorited' => true, 'kind' => $resolved['label']];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listFor(User $user): Collection
    {
        return WorkspaceFavorite::query()
            ->where('user_id', $user->id)
            ->with('favoritable')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (WorkspaceFavorite $fav) {
                $item = $fav->favoritable;
                $kind = match ($fav->favoritable_type) {
                    Document::class => 'document',
                    WorkspaceFolder::class => 'folder',
                    BibliographicReference::class => 'reference',
                    default => class_basename((string) $fav->favoritable_type),
                };

                return [
                    'id' => $fav->id,
                    'kind' => $kind,
                    'favoritable_id' => $fav->favoritable_id,
                    'favoritable_type' => $fav->favoritable_type,
                    'title' => $this->titleOf($item),
                    'name' => $this->titleOf($item),
                    'created_at' => $fav->created_at,
                    'route' => $this->routeOf($kind, (int) $fav->favoritable_id),
                ];
            })
            ->filter(fn ($row) => filled($row['title']))
            ->values();
    }

    public function isFavorite(User $user, Model $model): bool
    {
        return WorkspaceFavorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $model::class)
            ->where('favoritable_id', $model->getKey())
            ->exists();
    }

    /**
     * @return array{viewed: Collection<int, Document>, modified: Collection<int, Document>, favorites: Collection<int, array<string, mixed>>}
     */
    public function homeExtras(User $user): array
    {
        return [
            'favorites' => $this->listFor($user)->take(8)->values(),
            'recent' => $this->modified($user, 8),
            'viewed' => $this->viewed($user, 8),
        ];
    }

    /**
     * @return Collection<int, Document>
     */
    public function modified(User $user, int $limit = 20): Collection
    {
        return Document::query()
            ->whereIn('origin', [DocumentOrigin::Personal->value, DocumentOrigin::Workspace->value])
            ->where(function ($q) use ($user) {
                $q->where('author_id', $user->id)
                    ->orWhereHas('workspaceLinks.workspace.members', fn ($m) => $m->where('user_id', $user->id));
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'object', 'title', 'origin', 'updated_at']);
    }

    /**
     * @return Collection<int, Document>
     */
    public function viewed(User $user, int $limit = 20): Collection
    {
        $ids = DocumentView::query()
            ->where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->limit($limit * 2)
            ->pluck('document_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $docs = Document::query()
            ->whereIn('id', $ids)
            ->whereIn('origin', [DocumentOrigin::Personal->value, DocumentOrigin::Workspace->value])
            ->get(['id', 'object', 'title', 'origin', 'updated_at'])
            ->keyBy('id');

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

    private function titleOf(?Model $item): ?string
    {
        if (! $item) {
            return null;
        }

        if ($item instanceof Document) {
            return $item->title ?: $item->object;
        }

        if ($item instanceof WorkspaceFolder) {
            return $item->name;
        }

        if ($item instanceof BibliographicReference) {
            return $item->title;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeOf(string $kind, int $id): ?array
    {
        return match ($kind) {
            'document' => ['name' => 'espace-documents-id', 'params' => ['id' => $id]],
            'folder' => ['name' => 'espace-dossiers', 'query' => ['folder' => $id]],
            'reference' => ['name' => 'espace-bibliotheque', 'query' => ['ref' => $id]],
            default => null,
        };
    }
}
