<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveStorageLocation;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveLocationController extends Controller
{
    public function __construct(
        private readonly ArchiveLocationService $locations,
        private readonly ArchiveAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $query = ArchiveStorageLocation::query()
            ->with(['parent', 'structure'])
            ->withCount('boxes')
            ->orderBy('full_code');

        if ($request->filled('structure_id')) {
            $query->where('structure_id', (int) $request->integer('structure_id'));
        } elseif (! $request->user()->can('admin.access') && ! $request->user()->can('archives.administrer')) {
            $query->where('structure_id', (int) ($request->user()->structure_id ?? 0));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', (int) $request->integer('parent_id'));
        }

        if ($request->boolean('roots_only')) {
            $query->whereNull('parent_id');
        }

        $items = $query->get()->map(function (ArchiveStorageLocation $loc) {
            $data = $loc->toArray();
            $data['occupancy_rate'] = $loc->occupancyRate();

            return $data;
        });

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_emplacements')
            || $request->user()->can('archives.gerer_magasins')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'parent_id' => ['nullable', 'integer', 'exists:archive_storage_locations,id'],
            'type' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'capacity_linear_meters' => ['nullable', 'numeric', 'min:0'],
            'capacity_boxes' => ['nullable', 'integer', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'depth_cm' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            return response()->json($this->locations->create($request->user(), $data), 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, ArchiveStorageLocation $location): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $location->load(['parent', 'children', 'structure', 'boxes']);
        $payload = $location->toArray();
        $payload['occupancy_rate'] = $location->occupancyRate();

        return response()->json($payload);
    }

    public function update(Request $request, ArchiveStorageLocation $location): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_emplacements')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'capacity_linear_meters' => ['nullable', 'numeric', 'min:0'],
            'capacity_boxes' => ['nullable', 'integer', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'depth_cm' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->locations->update($request->user(), $location, $data));
    }

    public function occupation(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $structureId = (int) $request->integer('structure_id', (int) ($request->user()->structure_id ?? 0));

        $rooms = ArchiveStorageLocation::query()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->where('structure_id', $structureId)
            ->whereIn('type', ['room', 'cabinet', 'zone'])
            ->orderBy('full_code')
            ->get()
            ->map(function (ArchiveStorageLocation $room) {
                $slots = ArchiveStorageLocation::query()
                    ->where('structure_id', $room->structure_id)
                    ->where('full_code', 'like', $room->full_code.'%')
                    ->whereIn('type', ['shelf', 'slot', 'cabinet'])
                    ->withCount('boxes')
                    ->orderBy('full_code')
                    ->get()
                    ->map(fn (ArchiveStorageLocation $s) => array_merge($s->toArray(), [
                        'occupancy_rate' => $s->occupancyRate(),
                    ]));

                return [
                    'room' => array_merge($room->toArray(), ['occupancy_rate' => $room->occupancyRate()]),
                    'slots' => $slots,
                ];
            });

        return response()->json(['data' => $rooms]);
    }
}
