<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchEngineInterface;
use App\Models\User;
use App\Services\DocumentSearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Moteur de recherche basé sur MySQL/MariaDB (P0/P1).
 * Remplaçable ultérieurement par OpenSearchEngine.
 */
class DatabaseSearchEngine implements SearchEngineInterface
{
    public function __construct(
        private readonly DocumentSearchService $search,
    ) {}

    public function search(User $user, array $criteria, int $perPage = 15): LengthAwarePaginator
    {
        return $this->search->search($user, $criteria, $perPage);
    }
}
