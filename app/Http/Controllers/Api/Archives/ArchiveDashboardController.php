<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveTransfer;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveDashboardController extends Controller
{
    public function __construct(
        private readonly ArchiveSearchService $search,
        private readonly ArchiveAccessService $access,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $pending = $this->search->searchTransfers($request->user(), ['to_control' => true], 5);
        $recent = $this->search->searchRecords($request->user(), [], 5);

        return response()->json([
            'counts' => $this->search->dashboardCounts($request->user()),
            'pending_transfers' => $pending->items(),
            'recent_records' => $recent->items(),
        ]);
    }
}
