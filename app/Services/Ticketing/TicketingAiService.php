<?php

namespace App\Services\Ticketing;

use App\Models\KnowledgeArticle;
use App\Models\SupportTeam;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use Illuminate\Support\Str;

/**
 * Suggestions assistées Phase 3 — heuristiques locales uniquement (pas d’API externe).
 */
class TicketingAiService
{
    /**
     * @return array{
     *     category: array{id: int, code: string, name: string}|null,
     *     priority: array{id: int, code: string, name: string}|null,
     *     team: array{id: int, code: string, name: string}|null,
     *     knowledge_articles: list<array{id: int, title: string, slug: string|null}>
     * }
     */
    public function suggest(string $title, ?string $description = null): array
    {
        $haystack = Str::lower(trim($title.' '.($description ?? '')));

        return [
            'category' => $this->matchCategory($haystack),
            'priority' => $this->matchPriority($haystack),
            'team' => $this->matchTeam($haystack),
            'knowledge_articles' => $this->matchKnowledge($haystack),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    private function matchCategory(string $haystack): ?array
    {
        $keywords = [
            'reseau' => ['réseau', 'reseau', 'vpn', 'wifi', 'lan', 'connexion'],
            'poste' => ['poste', 'ordinateur', 'pc', 'laptop', 'imprimante'],
            'applicatif' => ['application', 'logiciel', 'bug', 'erreur', 'parapheur', 'ged'],
            'compte' => ['compte', 'mot de passe', 'password', 'login', 'accès', 'acces', 'ldap'],
            'messagerie' => ['mail', 'email', 'messagerie', 'outlook'],
        ];

        $categories = TicketCategory::query()->where('is_active', true)->get();

        foreach ($keywords as $needle => $terms) {
            foreach ($terms as $term) {
                if (! Str::contains($haystack, Str::lower($term))) {
                    continue;
                }
                $match = $categories->first(function (TicketCategory $c) use ($needle, $term) {
                    $blob = Str::lower($c->code.' '.$c->name);

                    return Str::contains($blob, $needle) || Str::contains($blob, Str::lower($term));
                });
                if ($match) {
                    return $match->only(['id', 'code', 'name']);
                }
            }
        }

        return $categories->sortBy('sort_order')->first()?->only(['id', 'code', 'name']);
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    private function matchPriority(string $haystack): ?array
    {
        $urgent = ['urgent', 'critique', 'bloquant', 'panne', 'indisponible', 'production', 'majeur'];
        $low = ['question', 'info', 'information', 'demande', 'amélioration', 'amelioration'];

        $code = null;
        foreach ($urgent as $term) {
            if (Str::contains($haystack, $term)) {
                $code = ['P1', 'CRITIQUE', 'HAUTE', 'HIGH', 'URGENT'];
                break;
            }
        }
        if ($code === null) {
            foreach ($low as $term) {
                if (Str::contains($haystack, $term)) {
                    $code = ['P4', 'BASSE', 'LOW', 'MINEURE'];
                    break;
                }
            }
        }
        if ($code === null) {
            $code = ['P3', 'P2', 'NORMALE', 'MEDIUM', 'MOYENNE'];
        }

        $priority = TicketPriority::query()
            ->where('is_active', true)
            ->whereIn('code', $code)
            ->orderBy('level')
            ->first();

        if (! $priority) {
            $priority = TicketPriority::query()->where('is_active', true)->orderBy('sort_order')->first();
        }

        return $priority?->only(['id', 'code', 'name']);
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    private function matchTeam(string $haystack): ?array
    {
        $map = [
            'N1' => ['poste', 'imprimante', 'mot de passe', 'password', 'compte'],
            'N2' => ['réseau', 'reseau', 'serveur', 'vpn', 'infrastructure'],
            'APP' => ['application', 'logiciel', 'bug', 'parapheur', 'ged'],
        ];

        $teams = SupportTeam::query()->where('is_active', true)->get();

        foreach ($map as $codeHint => $terms) {
            foreach ($terms as $term) {
                if (! Str::contains($haystack, Str::lower($term))) {
                    continue;
                }
                $match = $teams->first(fn (SupportTeam $t) => Str::contains(Str::upper($t->code), $codeHint)
                    || Str::contains(Str::lower($t->name), Str::lower($term)));
                if ($match) {
                    return $match->only(['id', 'code', 'name']);
                }
            }
        }

        return $teams->first()?->only(['id', 'code', 'name']);
    }

    /**
     * @return list<array{id: int, title: string, slug: string|null}>
     */
    private function matchKnowledge(string $haystack): array
    {
        $tokens = collect(preg_split('/\W+/u', $haystack, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 4)
            ->unique()
            ->take(8)
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        $query = KnowledgeArticle::query()
            ->where('status', 'published')
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('title', 'like', '%'.$token.'%')
                        ->orWhere('summary', 'like', '%'.$token.'%')
                        ->orWhere('body', 'like', '%'.$token.'%');
                }
            })
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title', 'slug']);

        return $query->map(fn (KnowledgeArticle $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'slug' => $a->slug,
        ])->all();
    }
}
