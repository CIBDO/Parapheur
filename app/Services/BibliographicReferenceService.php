<?php

namespace App\Services;

use App\Models\BibliographicReference;
use App\Models\ReferenceAuthor;
use App\Models\ReferenceCollection;
use App\Models\ReferenceNote;
use App\Models\ReferenceTag;
use App\Models\ReferenceType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BibliographicReferenceService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $scope = $filters['scope'] ?? 'mine'; // mine|institutional|pending|all

        $query = BibliographicReference::query()
            ->with(['type', 'authors', 'tags', 'collections']);

        if ($scope === 'institutional') {
            $query->where('publication_status', 'institutional');
        } elseif ($scope === 'pending') {
            abort_unless($user->can('library.moderate') || $user->can('admin.access'), 403);
            $query->where('publication_status', 'proposed');
        } elseif ($scope === 'all') {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhere('publication_status', 'institutional');
                if ($user->can('library.moderate') || $user->can('admin.access')) {
                    $q->orWhere('publication_status', 'proposed');
                }
            });
        } else {
            $query->where('owner_id', $user->id);
        }

        if (! empty($filters['q'])) {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('institutional_author', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhere('doi', 'like', $like)
                    ->orWhere('abstract', 'like', $like)
                    ->orWhere('organization', 'like', $like);
            });
        }

        if (! empty($filters['reference_type_id'])) {
            $query->where('reference_type_id', $filters['reference_type_id']);
        }

        if (! empty($filters['publication_year'])) {
            $query->where('publication_year', $filters['publication_year']);
        }

        if (! empty($filters['tag'])) {
            $slug = Str::slug((string) $filters['tag']);
            $query->whereHas('tags', fn ($t) => $t->where('slug', $slug));
        }

        return $query->orderByDesc('updated_at')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{reference: BibliographicReference, duplicates: list<BibliographicReference>}
     */
    public function create(User $owner, array $data): array
    {
        if (empty($data['reference_type_id'])) {
            $data['reference_type_id'] = ReferenceType::query()->where('code', 'AUTRE')->value('id')
                ?? ReferenceType::query()->orderBy('id')->value('id');
        }

        $duplicates = $this->findDuplicates($data, $owner);

        $reference = DB::transaction(function () use ($owner, $data) {
            $reference = BibliographicReference::query()->create([
                'owner_id' => $owner->id,
                'workspace_id' => $data['workspace_id'] ?? null,
                'structure_id' => $data['structure_id'] ?? $owner->structure_id,
                'reference_type_id' => $data['reference_type_id'],
                'title' => $data['title'],
                'institutional_author' => $data['institutional_author'] ?? null,
                'publication_year' => $data['publication_year'] ?? null,
                'publication_date' => $data['publication_date'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'publisher' => $data['publisher'] ?? null,
                'organization' => $data['organization'] ?? null,
                'country' => $data['country'] ?? null,
                'language' => $data['language'] ?? 'fr',
                'abstract' => $data['abstract'] ?? null,
                'source_url' => $data['source_url'] ?? null,
                'source_title' => $data['source_title'] ?? null,
                'accessed_at' => $data['accessed_at'] ?? null,
                'doi' => $data['doi'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'issn' => $data['issn'] ?? null,
                'external_id' => $data['external_id'] ?? null,
                'visibility' => $data['visibility'] ?? 'private',
                'document_id' => $data['document_id'] ?? null,
                'content_hash' => $data['content_hash'] ?? null,
                'publication_status' => $data['publication_status'] ?? 'personal',
            ]);

            $this->syncAuthors($reference, $data['authors'] ?? []);
            $this->syncTags($reference, $data['tags'] ?? []);

            if (! empty($data['collection_ids']) && is_array($data['collection_ids'])) {
                $reference->collections()->sync($data['collection_ids']);
            }

            return $reference->fresh(['type', 'authors', 'tags', 'collections']);
        });

        return ['reference' => $reference, 'duplicates' => $duplicates];
    }

    public function proposeInstitutional(BibliographicReference $reference, User $actor): BibliographicReference
    {
        if ((int) $reference->owner_id !== (int) $actor->id && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Seul le propriétaire peut proposer cette référence.');
        }

        if ($reference->publication_status === 'institutional') {
            throw new InvalidArgumentException('Référence déjà institutionnelle.');
        }

        $reference->publication_status = 'proposed';
        $reference->proposed_at = now();
        $reference->proposed_by = $actor->id;
        $reference->save();

        $this->audit->log('library.reference_proposed', $reference, ['actor_id' => $actor->id]);

        return $reference->fresh(['type', 'authors', 'tags']);
    }

    public function moderate(
        BibliographicReference $reference,
        User $moderator,
        bool $approve,
        ?string $note = null,
    ): BibliographicReference {
        abort_unless($moderator->can('library.moderate') || $moderator->can('admin.access'), 403);

        if ($reference->publication_status !== 'proposed') {
            throw new InvalidArgumentException('Cette référence n’est pas en attente de validation.');
        }

        $reference->publication_status = $approve ? 'institutional' : 'personal';
        $reference->reviewed_at = now();
        $reference->reviewed_by = $moderator->id;
        $reference->review_note = $note;
        if ($approve) {
            $reference->visibility = 'restricted';
        }
        $reference->save();

        $this->audit->log($approve ? 'library.reference_approved' : 'library.reference_rejected', $reference, [
            'actor_id' => $moderator->id,
            'note' => $note,
        ]);

        return $reference->fresh(['type', 'authors', 'tags']);
    }

    public function upsertNote(BibliographicReference $reference, User $user, string $body): ReferenceNote
    {
        $note = ReferenceNote::query()->updateOrCreate(
            [
                'reference_id' => $reference->id,
                'user_id' => $user->id,
            ],
            ['body' => $body]
        );

        return $note;
    }

    public function myNote(BibliographicReference $reference, User $user): ?ReferenceNote
    {
        return ReferenceNote::query()
            ->where('reference_id', $reference->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<BibliographicReference>
     */
    public function findDuplicates(array $data, User $owner): array
    {
        $query = BibliographicReference::query()->where(function ($q) use ($owner) {
            $q->where('owner_id', $owner->id)
                ->orWhere('publication_status', 'institutional');
        });

        $query->where(function ($q) use ($data) {
            $q->where('title', $data['title'] ?? '');
            if (! empty($data['doi'])) {
                $q->orWhere('doi', $data['doi']);
            }
            if (! empty($data['reference_number'])) {
                $q->orWhere('reference_number', $data['reference_number']);
            }
            if (! empty($data['source_url'])) {
                $q->orWhere('source_url', $data['source_url']);
            }
            if (! empty($data['content_hash'])) {
                $q->orWhere('content_hash', $data['content_hash']);
            }
        });

        return $query->limit(5)->get()->all();
    }

    public function types(): Collection
    {
        return ReferenceType::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCollection(User $owner, array $data): ReferenceCollection
    {
        return ReferenceCollection::query()->create([
            'owner_id' => $owner->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'is_institutional' => (bool) ($data['is_institutional'] ?? false),
        ]);
    }

    public function collectionsFor(User $user): Collection
    {
        return ReferenceCollection::query()
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhere('is_institutional', true);
            })
            ->orderByDesc('is_institutional')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<string|array{name: string}>  $authors
     */
    private function syncAuthors(BibliographicReference $reference, array $authors): void
    {
        $sync = [];
        foreach (array_values($authors) as $i => $author) {
            $name = is_string($author) ? $author : ($author['name'] ?? null);
            if (! $name) {
                continue;
            }
            $model = ReferenceAuthor::query()->firstOrCreate(
                ['normalized_name' => Str::lower(Str::ascii($name))],
                ['name' => $name]
            );
            $sync[$model->id] = ['sort_order' => $i];
        }
        $reference->authors()->sync($sync);
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncTags(BibliographicReference $reference, array $tags): void
    {
        $ids = [];
        foreach ($tags as $tag) {
            if (! is_string($tag) || trim($tag) === '') {
                continue;
            }
            $slug = Str::slug($tag);
            $model = ReferenceTag::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => trim($tag)]
            );
            $ids[] = $model->id;
        }
        $reference->tags()->sync($ids);
    }
}
