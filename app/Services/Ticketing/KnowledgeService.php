<?php

namespace App\Services\Ticketing;

use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = KnowledgeArticle::query()
            ->with(['category:id,name', 'author:id,name'])
            ->orderByDesc('updated_at');

        if (! empty($filters['q'])) {
            $q = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(function ($qq) use ($q) {
                $qq->where('title', 'like', $q)
                    ->orWhere('summary', 'like', $q)
                    ->orWhere('body', 'like', $q);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['knowledge_category_id'])) {
            $query->where('knowledge_category_id', (int) $filters['knowledge_category_id']);
        }

        return $query->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): KnowledgeArticle
    {
        return DB::transaction(function () use ($actor, $data) {
            $title = $data['title'];
            $slug = $data['slug'] ?? Str::slug($title);

            return KnowledgeArticle::query()->create([
                'knowledge_category_id' => $data['knowledge_category_id'] ?? null,
                'title' => $title,
                'slug' => $slug,
                'summary' => $data['summary'] ?? null,
                'body' => $data['body'] ?? null,
                'status' => Str::lower((string) ($data['status'] ?? 'draft')),
                'author_id' => $actor->id,
            ])->fresh(['category', 'author']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(KnowledgeArticle $article, array $data): KnowledgeArticle
    {
        $allowed = ['knowledge_category_id', 'title', 'slug', 'summary', 'body', 'status'];
        $payload = array_intersect_key($data, array_flip($allowed));

        if (isset($payload['status'])) {
            $payload['status'] = Str::lower((string) $payload['status']);
        }

        if (isset($payload['title']) && empty($payload['slug']) && empty($article->slug)) {
            $payload['slug'] = Str::slug($payload['title']);
        }

        $article->update($payload);

        return $article->fresh(['category', 'author', 'tickets']);
    }

    public function publish(KnowledgeArticle $article): KnowledgeArticle
    {
        $article->update([
            'status' => 'published',
            'published_at' => $article->published_at ?? now(),
        ]);

        return $article->fresh(['category', 'author']);
    }

    public function linkTicket(KnowledgeArticle $article, Ticket $ticket): KnowledgeArticle
    {
        $article->tickets()->syncWithoutDetaching([$ticket->id]);

        return $article->fresh(['tickets']);
    }
}
