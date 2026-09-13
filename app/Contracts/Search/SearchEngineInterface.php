<?php

namespace App\Contracts\Search;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SearchEngineInterface
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    public function search(User $user, array $criteria, int $perPage = 15): LengthAwarePaginator;
}
