<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnifiedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnifiedSearchController extends Controller
{
    public function __construct(
        private readonly UnifiedSearchService $search,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->search->search($request->user(), $data, $data['limit'] ?? 50)
        );
    }
}
