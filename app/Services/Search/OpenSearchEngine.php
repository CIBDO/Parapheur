<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchEngineInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * Stub pour migration future vers OpenSearch / Elasticsearch (P2).
 */
class OpenSearchEngine implements SearchEngineInterface
{
    public function search(User $user, array $criteria, int $perPage = 15): LengthAwarePaginator
    {
        throw new RuntimeException('OpenSearch n’est pas encore configuré. Utiliser DatabaseSearchEngine.');
    }
}
