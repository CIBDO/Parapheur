<?php

namespace App\Services;

use App\Enums\DocumentOrigin;
use App\Models\BibliographicReference;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceDocumentLink;
use Illuminate\Support\Collection;

/**
 * Recherche transversale Bureau Numérique (GED + mon espace + partagés + références).
 */
class UnifiedSearchService
{
    public function __construct(
        private readonly DocumentAccessService $access,
        private readonly DocumentSearchService $gedSearch,
        private readonly BibliographicReferenceService $library,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function search(User $user, array $criteria, int $limit = 50): array
    {
        $q = trim((string) ($criteria['q'] ?? ''));
        $results = collect();

        // GED
        if ($user->can('ged.view') || $user->can('ged.search') || $user->can('admin.access')) {
            $ged = $this->gedSearch->search($user, [
                'q' => $q,
                'per_page' => min(20, $limit),
            ], min(20, $limit));

            foreach ($ged->items() as $doc) {
                /** @var Document $doc */
                $results->push($this->mapDocument($doc, 'ged', 'GED'));
            }
        }

        // Mon espace + partagés (origins personal/workspace)
        if ($user->can('workspace.access') || $user->can('admin.access')) {
            $wsQuery = Document::query()
                ->with(['latestVersion', 'type:id,code,name', 'author:id,name'])
                ->whereIn('origin', [DocumentOrigin::Personal->value, DocumentOrigin::Workspace->value]);

            $this->access->scopeVisibleTo($wsQuery, $user);

            if ($q !== '') {
                $like = '%'.$q.'%';
                $wsQuery->where(function ($qq) use ($like) {
                    $qq->where('object', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('reference', 'like', $like);
                });
            }

            foreach ($wsQuery->orderByDesc('updated_at')->limit(20)->get() as $doc) {
                $shared = WorkspaceDocumentLink::query()
                    ->where('document_id', $doc->id)
                    ->whereHas('workspace.shares', fn ($s) => $s->where('grantee_user_id', $user->id))
                    ->exists();

                $provenance = $shared
                    ? 'shared'
                    : ((string) ($doc->origin?->value ?? $doc->origin) === DocumentOrigin::Workspace->value ? 'shared' : 'personal');
                $label = match ($provenance) {
                    'shared' => 'PARTAGÉ',
                    default => 'MON ESPACE',
                };

                $results->push($this->mapDocument($doc, $provenance, $label));
            }
        }

        // Références
        if ($user->can('library.access') || $user->can('admin.access')) {
            $refs = $this->library->search($user, [
                'q' => $q,
                'scope' => $criteria['library_scope'] ?? 'all',
            ], 15);

            foreach ($refs->items() as $ref) {
                /** @var BibliographicReference $ref */
                $results->push([
                    'id' => $ref->id,
                    'provenance' => 'reference',
                    'provenance_label' => 'RÉFÉRENCE',
                    'title' => $ref->title,
                    'subtitle' => collect([
                        $ref->type?->name,
                        $ref->institutional_author,
                        $ref->publication_year,
                    ])->filter()->implode(' · '),
                    'updated_at' => $ref->updated_at,
                    'url' => '/espace/bibliotheque?ref='.$ref->id,
                    'meta' => [
                        'publication_status' => $ref->publication_status,
                    ],
                ]);
            }
        }

        $sorted = $results->sortByDesc(fn ($r) => strtotime((string) ($r['updated_at'] ?? 'now')))->values();
        $data = $sorted->take($limit)->all();

        return [
            'data' => $data,
            'total' => count($data),
            'q' => $q,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDocument(Document $doc, string $provenance, string $label): array
    {
        return [
            'id' => $doc->id,
            'provenance' => $provenance,
            'provenance_label' => $label,
            'title' => $doc->title ?: $doc->object,
            'subtitle' => collect([
                $doc->reference,
                $doc->type?->name,
                $doc->author?->name,
            ])->filter()->implode(' · '),
            'updated_at' => $doc->updated_at,
            'url' => match ($provenance) {
                'ged' => '/ged/'.$doc->id,
                default => '/espace/documents/'.$doc->id,
            },
            'meta' => [
                'origin' => $doc->origin?->value ?? $doc->origin,
                'status' => $doc->status?->value ?? $doc->status,
            ],
        ];
    }
}
