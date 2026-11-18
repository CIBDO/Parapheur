<?php

namespace App\Services;

use App\Enums\DocumentArchiveStatus;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentPublicationStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche documentaire GED — sécurité intégrée à la requête.
 */
class DocumentSearchService
{
    /** @var list<string> */
    private const WORK_SCOPES = [
        'mine', 'shared', 'to_process', 'to_validate', 'my_deposits', 'all',
    ];

    public function __construct(
        private readonly DocumentAccessService $access,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function search(User $user, array $criteria, int $perPage = 15): LengthAwarePaginator
    {
        $criteria['_user_id'] = $user->id;
        $query = $this->baseFilteredQuery($user, $criteria)
            ->with([
                'type:id,code,name',
                'category:id,code,name',
                'domain:id,code,name',
                'structure:id,code,name',
                'author:id,name,email',
                'classificationNode:id,code,name,path',
                'folder:id,code,name,path',
                'latestVersion',
                'tags:id,name,slug',
            ]);

        [$sort, $dir] = $this->resolveSort($criteria);

        return $query->orderBy($sort, $dir)->paginate($perPage);
    }

    /**
     * Catalogue institutionnel paginé + facettes SQL (sans OpenSearch).
     *
     * @param  array<string, mixed>  $criteria
     * @return array{data: mixed, total: int, per_page: int, current_page: int, last_page: int, facets: array<string, mixed>}
     */
    public function catalog(User $user, array $criteria, int $perPage = 15): array
    {
        if (! isset($criteria['catalog']) || $criteria['catalog'] === '' || $criteria['catalog'] === null) {
            $criteria['catalog'] = 'institutional';
        }

        $paginator = $this->search($user, $criteria, $perPage);
        $facetQuery = $this->baseFilteredQuery($user, array_merge($criteria, ['_user_id' => $user->id]));

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'facets' => $this->buildFacets($facetQuery),
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function baseFilteredQuery(User $user, array $criteria): Builder
    {
        $query = Document::query();
        $this->access->scopeVisibleTo($query, $user);
        $criteria['_user_id'] = $criteria['_user_id'] ?? $user->id;

        if (! array_key_exists('origin', $criteria) || $criteria['origin'] === '' || $criteria['origin'] === null) {
            if (empty($criteria['include_workspace_origins'])) {
                $query->whereNotIn('origin', [
                    DocumentOrigin::Personal->value,
                    DocumentOrigin::Workspace->value,
                ]);
            }
        }

        $this->applyInstitutionalCatalogScope($query, $criteria);
        $this->applyFilters($query, $criteria);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{0: string, 1: string}
     */
    private function resolveSort(array $criteria): array
    {
        $sort = $criteria['sort'] ?? 'created_at';
        $dir = strtolower((string) ($criteria['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = [
            'created_at', 'document_date', 'reference', 'object', 'title',
            'status', 'priority', 'confidentiality', 'updated_at',
            'published_at', 'publication_status', 'exercise_year',
        ];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return [$sort, $dir];
    }

    /**
     * @return array<string, list<array{value: mixed, label: string|null, total: int}>>
     */
    private function buildFacets(Builder $base): array
    {
        $mapFacet = function (Builder $query, string $column) {
            return (clone $query)
                ->whereNotNull($column)
                ->selectRaw($column.' as value, count(*) as total')
                ->groupBy($column)
                ->orderByDesc('total')
                ->limit(30)
                ->get()
                ->map(fn ($row) => [
                    'value' => $row->value,
                    'label' => (string) $row->value,
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all();
        };

        $structures = (clone $base)
            ->whereNotNull('structure_id')
            ->selectRaw('structure_id as value, count(*) as total')
            ->groupBy('structure_id')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        $structureNames = \App\Models\Structure::query()
            ->whereIn('id', $structures->pluck('value')->all())
            ->pluck('name', 'id');

        $types = (clone $base)
            ->whereNotNull('document_type_id')
            ->selectRaw('document_type_id as value, count(*) as total')
            ->groupBy('document_type_id')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        $typeNames = \App\Models\DocumentType::query()
            ->whereIn('id', $types->pluck('value')->all())
            ->pluck('name', 'id');

        return [
            'origin' => $mapFacet($base, 'origin'),
            'publication_status' => $mapFacet($base, 'publication_status'),
            'confidentiality' => $mapFacet($base, 'confidentiality'),
            'status' => $mapFacet($base, 'status'),
            'exercise_year' => $mapFacet($base, 'exercise_year'),
            'structure' => $structures->map(fn ($row) => [
                'value' => (int) $row->value,
                'label' => $structureNames[(int) $row->value] ?? (string) $row->value,
                'total' => (int) $row->total,
            ])->values()->all(),
            'document_type' => $types->map(fn ($row) => [
                'value' => (int) $row->value,
                'label' => $typeNames[(int) $row->value] ?? (string) $row->value,
                'total' => (int) $row->total,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, int>
     */
    public function dashboardCounts(User $user): array
    {
        $base = function () use ($user) {
            return $this->access->scopeVisibleTo(Document::query(), $user)
                ->whereNotIn('origin', [
                    DocumentOrigin::Personal->value,
                    DocumentOrigin::Workspace->value,
                ]);
        };

        return [
            'actifs' => (clone $base())->where('archive_status', DocumentArchiveStatus::Actif->value)->count(),
            'archives' => (clone $base())->where(function (Builder $q) {
                $q->where('archive_status', DocumentArchiveStatus::Archive->value)
                    ->orWhereNotNull('archived_at');
            })->count(),
            'ce_mois' => (clone $base())->where('created_at', '>=', now()->startOfMonth())->count(),
            'a_traiter' => (clone $base())->where('current_assignee_id', $user->id)
                ->whereNull('archived_at')
                ->whereNotIn('status', ['archive', 'annule', 'traite', 'valide'])->count(),
            'confidentiels' => (clone $base())->whereIn('confidentiality', ['confidentiel', 'tres_confidentiel'])->count(),
            'non_classes' => (clone $base())->whereNull('classification_node_id')->count(),
            'mes_documents' => (clone $base())->where('author_id', $user->id)->count(),
            'partages' => (clone $base())->whereHas('accessRules', function (Builder $r) use ($user) {
                $r->where('user_id', $user->id)
                    ->where(function (Builder $exp) {
                        $exp->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })->count(),
            'favoris' => (clone $base())->whereHas('favorites', fn (Builder $f) => $f->where('user_id', $user->id))->count(),
            'geles' => (clone $base())->where(function (Builder $q) {
                $q->where('archive_status', DocumentArchiveStatus::Gele->value)
                    ->orWhereNotNull('legal_hold_at');
            })->count(),
            'sans_index' => (clone $base())->whereNull('indexed_at')->count(),
            'a_valider' => (clone $base())->whereIn('publication_status', ['en_verification', 'valide'])->count(),
            'publies' => (clone $base())->where('publication_status', 'publie')->count(),
            'mes_depots' => (clone $base())->where('author_id', $user->id)
                ->where('origin', DocumentOrigin::Ged->value)->count(),
        ];
    }

    /**
     * Indicateurs volume (agrégats simples pour dashboard).
     *
     * @return array<string, mixed>
     */
    public function indicators(User $user): array
    {
        $visibleIds = $this->access->scopeVisibleTo(Document::query(), $user)
            ->whereNotIn('origin', [
                DocumentOrigin::Personal->value,
                DocumentOrigin::Workspace->value,
            ])
            ->pluck('id');

        $base = Document::query()->whereIn('id', $visibleIds);

        $byType = (clone $base)
            ->selectRaw('document_type_id, count(*) as total')
            ->groupBy('document_type_id')
            ->get()
            ->map(function ($row) {
                $type = \App\Models\DocumentType::query()->find($row->document_type_id);

                return [
                    'type_id' => $row->document_type_id,
                    'type' => $type?->name,
                    'total' => (int) $row->total,
                ];
            });

        $byYear = (clone $base)
            ->whereNotNull('document_date')
            ->get(['document_date'])
            ->groupBy(fn ($d) => $d->document_date?->format('Y') ?: 'n/a')
            ->map(fn ($items, $year) => ['year' => $year, 'total' => $items->count()])
            ->sortKeysDesc()
            ->values()
            ->take(10);

        return [
            'volume_total' => $visibleIds->count(),
            'by_type' => $byType,
            'by_year' => $byYear,
            'unclassified' => (clone $base)->whereNull('classification_node_id')->count(),
            'missing_metadata' => (clone $base)->where(function (Builder $q) {
                $q->whereNull('title')->orWhereNull('document_type_id')->orWhereNull('structure_id');
            })->count(),
        ];
    }

    /**
     * Bibliothèque institutionnelle = documents versés (depose+), jamais les brouillons.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function applyInstitutionalCatalogScope(Builder $query, array $criteria): void
    {
        $catalog = strtolower(trim((string) ($criteria['catalog'] ?? '')));
        $scope = (string) ($criteria['scope'] ?? '');

        if ($catalog === '' || $catalog === 'default') {
            $catalog = in_array($scope, self::WORK_SCOPES, true) ? 'work' : 'institutional';
        }

        if ($catalog === 'all' || $catalog === 'work') {
            return;
        }

        // Filtre publication explicite : l’appelant pilote le périmètre
        if (isset($criteria['publication_status'])
            && $criteria['publication_status'] !== ''
            && $criteria['publication_status'] !== null) {
            return;
        }

        if ($catalog !== 'institutional') {
            return;
        }

        $query->whereIn(
            'publication_status',
            DocumentPublicationStatus::institutionalCatalogValues()
        );
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function applyFilters(Builder $query, array $criteria): void
    {
        if (! empty($criteria['q'])) {
            $term = trim((string) $criteria['q']);
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $driver = $query->getConnection()->getDriverName();

            $query->where(function (Builder $w) use ($like, $term, $driver) {
                $w->where('reference', 'like', $like)
                    ->orWhere('dossier_number', 'like', $like)
                    ->orWhere('object', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('source', 'like', $like)
                    ->orWhereHas('tags', function (Builder $t) use ($like) {
                        $t->where('name', 'like', $like)->orWhere('slug', 'like', $like);
                    })
                    ->orWhereHas('indexContents', function (Builder $i) use ($like, $term, $driver) {
                        if (in_array($driver, ['mysql', 'mariadb'], true) && $term !== '') {
                            $i->whereFullText('content', $term);
                        } else {
                            $i->where('content', 'like', $like);
                        }
                    });
            });
        }

        foreach (['reference', 'dossier_number', 'object', 'title'] as $exactLike) {
            if (! empty($criteria[$exactLike])) {
                $query->where($exactLike, 'like', '%'.$criteria[$exactLike].'%');
            }
        }

        foreach ([
            'document_type_id', 'category_id', 'domain_id', 'structure_id', 'owner_structure_id',
            'author_id', 'classification_node_id', 'folder_id', 'status', 'priority',
            'confidentiality', 'origin', 'archive_status', 'expected_action',
            'publication_status', 'exercise_year',
        ] as $field) {
            if (isset($criteria[$field]) && $criteria[$field] !== '' && $criteria[$field] !== null) {
                $query->where($field, $criteria[$field]);
            }
        }

        if (! empty($criteria['type_id']) && empty($criteria['document_type_id'])) {
            $query->where('document_type_id', $criteria['type_id']);
        }

        if (! empty($criteria['keywords'])) {
            $keywords = is_array($criteria['keywords'])
                ? $criteria['keywords']
                : array_filter(array_map('trim', explode(',', (string) $criteria['keywords'])));
            foreach ($keywords as $kw) {
                $query->whereJsonContains('keywords', $kw);
            }
        }

        if (! empty($criteria['tag']) || ! empty($criteria['tags'])) {
            $tags = $criteria['tags'] ?? $criteria['tag'];
            $tags = is_array($tags) ? $tags : [$tags];
            $query->whereHas('tags', function (Builder $t) use ($tags) {
                $t->whereIn('slug', $tags)->orWhereIn('name', $tags);
            });
        }

        if (! empty($criteria['document_date_from'])) {
            $query->whereDate('document_date', '>=', $criteria['document_date_from']);
        }
        if (! empty($criteria['document_date_to'])) {
            $query->whereDate('document_date', '<=', $criteria['document_date_to']);
        }
        if (! empty($criteria['created_from'])) {
            $query->whereDate('created_at', '>=', $criteria['created_from']);
        }
        if (! empty($criteria['created_to'])) {
            $query->whereDate('created_at', '<=', $criteria['created_to']);
        }

        if (! empty($criteria['exercice'])) {
            $year = (int) $criteria['exercice'];
            $query->where(function (Builder $y) use ($year) {
                $y->where('exercise_year', $year)
                    ->orWhereYear('document_date', $year);
            });
        }

        if (isset($criteria['unclassified']) && filter_var($criteria['unclassified'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('classification_node_id');
        }

        if (! empty($criteria['scope'])) {
            match ($criteria['scope']) {
                'mine' => $query->where('author_id', $criteria['_user_id'] ?? 0),
                'shared' => $query->whereHas('accessRules'),
                'to_process' => $query->where('current_assignee_id', $criteria['_user_id'] ?? 0)
                    ->whereNull('archived_at'),
                'to_validate' => $query->whereIn('publication_status', ['en_verification', 'valide']),
                'published' => $query->where('publication_status', 'publie'),
                'institutional' => $query->whereIn(
                    'publication_status',
                    DocumentPublicationStatus::institutionalCatalogValues()
                ),
                'my_deposits' => $query->where('author_id', $criteria['_user_id'] ?? 0)
                    ->where('origin', DocumentOrigin::Ged->value),
                'archives' => $query->where(function (Builder $a) {
                    $a->where('archive_status', DocumentArchiveStatus::Archive->value)
                        ->orWhere('publication_status', 'archive')
                        ->orWhereNotNull('archived_at');
                }),
                default => null,
            };
        }

        if (! empty($criteria['meeting_id'])) {
            $query->whereHas('meetings', fn (Builder $m) => $m->where('meetings.id', $criteria['meeting_id']));
        }
    }
}
