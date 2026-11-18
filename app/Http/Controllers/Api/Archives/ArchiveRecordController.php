<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveRecord;
use App\Services\Archives\ArchiveClassificationService;
use App\Services\Archives\ArchiveSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveRecordController extends Controller
{
    public function __construct(
        private readonly ArchiveSearchService $search,
        private readonly ArchiveClassificationService $classification,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchiveRecord::class);

        $page = $this->search->searchRecords(
            $request->user(),
            $request->only(['status', 'structure_id', 'fonds_id', 'series_id', 'q']),
            (int) $request->integer('per_page', 15)
        );

        return response()->json($page);
    }

    public function show(Request $request, ArchiveRecord $record): JsonResponse
    {
        $this->authorize('view', $record);

        $record->load([
            'structure',
            'producerUnit',
            'fonds',
            'series',
            'classificationNode',
            'transfer',
            'document.latestVersion',
            'documentVersion',
            'holds',
        ]);

        return response()->json($record);
    }

    public function classify(Request $request, ArchiveRecord $record): JsonResponse
    {
        $this->authorize('classify', $record);

        $data = $request->validate([
            'classification_node_id' => ['nullable', 'integer', 'exists:archive_classification_nodes,id'],
            'fonds_id' => ['nullable', 'integer', 'exists:archive_fonds,id'],
            'series_id' => ['nullable', 'integer', 'exists:archive_series,id'],
        ]);

        $updated = $this->classification->classifyRecord(
            $request->user(),
            $record,
            $data['classification_node_id'] ?? null,
            $data['fonds_id'] ?? null,
            $data['series_id'] ?? null,
        );

        return response()->json($updated);
    }

    public function updateHybrid(Request $request, ArchiveRecord $record): JsonResponse
    {
        $this->authorize('classify', $record);

        $data = $request->validate([
            'medium' => ['required', 'string', 'in:electronique,physique,hybride'],
            'physical_reference' => ['nullable', 'string', 'max:128'],
            'box_id' => ['nullable', 'integer', 'exists:archive_boxes,id'],
            'location_id' => ['nullable', 'integer', 'exists:archive_storage_locations,id'],
        ]);

        $record->fill([
            'medium' => $data['medium'],
            'physical_reference' => $data['physical_reference'] ?? $record->physical_reference,
            'box_id' => array_key_exists('box_id', $data) ? $data['box_id'] : $record->box_id,
            'location_id' => array_key_exists('location_id', $data) ? $data['location_id'] : $record->location_id,
        ])->save();

        return response()->json($record->fresh(['box', 'location', 'document']));
    }
}
