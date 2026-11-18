<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Services\Archives\ArchivePreventiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchivePreventiveController extends Controller
{
    public function __construct(private readonly ArchivePreventiveService $preventive) {}

    public function events(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $structureId = (int) $request->integer('structure_id');
        abort_if($structureId <= 0, 422, 'structure_id requis');

        return response()->json($this->preventive->listEvents(
            $structureId,
            (int) $request->integer('per_page', 20)
        ));
    }

    public function storeEvent(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'location_id' => ['nullable', 'integer', 'exists:archive_storage_locations,id'],
            'box_id' => ['nullable', 'integer', 'exists:archive_boxes,id'],
            'event_type' => ['required', 'string', 'max:64'],
            'severity' => ['nullable', 'string', 'in:info,warning,critical'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
        ]);

        return response()->json($this->preventive->reportEvent($request->user(), $data), 201);
    }

    public function climate(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $structureId = (int) $request->integer('structure_id');
        abort_if($structureId <= 0, 422, 'structure_id requis');

        return response()->json($this->preventive->listClimate(
            $structureId,
            (int) $request->integer('per_page', 20)
        ));
    }

    public function storeClimate(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'location_id' => ['required', 'integer', 'exists:archive_storage_locations,id'],
            'temperature_c' => ['nullable', 'numeric'],
            'humidity_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'measured_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json($this->preventive->recordClimate($request->user(), $data), 201);
    }
}
