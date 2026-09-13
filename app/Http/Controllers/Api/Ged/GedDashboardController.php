<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GedDashboardController extends Controller
{
    public function __construct(
        private readonly DocumentSearchService $search,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return response()->json([
            'counts' => $this->search->dashboardCounts($request->user()),
            'indicators' => $this->search->indicators($request->user()),
            'recent' => $this->search->search($request->user(), [
                'sort' => 'created_at',
                'dir' => 'desc',
            ], 8)->items(),
        ]);
    }
}
