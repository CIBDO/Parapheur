<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ArchiveReportController extends Controller
{
    public function __construct(
        private readonly ArchiveReportService $reports,
        private readonly ArchiveAccessService $access,
    ) {}

    public function responsible(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        return response()->json($this->reports->responsibleDashboard($request->user()));
    }

    public function dg(Request $request): JsonResponse
    {
        try {
            return response()->json($this->reports->dgDashboard($request->user()));
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    public function exportMetadata(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.exporter')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $structureId = $request->filled('structure_id') ? (int) $request->integer('structure_id') : null;

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'data' => $this->reports->exportRecordsMetadata($request->user(), $structureId),
        ]);
    }
}
