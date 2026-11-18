<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveInventory;
use App\Models\ArchiveMovement;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveInventoryController extends Controller
{
    public function __construct(
        private readonly ArchiveInventoryService $inventories,
        private readonly ArchiveAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_recolement')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access')
            || $this->access->canViewModule($request->user()),
            403
        );

        $query = ArchiveInventory::query()
            ->with(['structure', 'location', 'creator'])
            ->orderByDesc('id');

        if ($request->filled('structure_id')) {
            $query->where('structure_id', (int) $request->integer('structure_id'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_recolement')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'location_id' => ['nullable', 'integer', 'exists:archive_storage_locations,id'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json($this->inventories->create($request->user(), $data), 201);
    }

    public function show(Request $request, ArchiveInventory $inventory): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        return response()->json($inventory->load(['items.box', 'items.expectedLocation', 'location', 'structure']));
    }

    public function start(Request $request, ArchiveInventory $inventory): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_recolement') || $request->user()->can('admin.access'), 403);

        try {
            return response()->json($this->inventories->start($request->user(), $inventory));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function scan(Request $request, ArchiveInventory $inventory): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_recolement') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:128'],
        ]);

        try {
            return response()->json($this->inventories->scan($request->user(), $inventory, $data['code']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function close(Request $request, ArchiveInventory $inventory): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_recolement') || $request->user()->can('admin.access'), 403);

        try {
            return response()->json($this->inventories->close($request->user(), $inventory));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function movements(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $query = ArchiveMovement::query()
            ->with(['box', 'fromLocation', 'toLocation', 'actor'])
            ->orderByDesc('moved_at');

        if ($request->filled('box_id')) {
            $query->where('box_id', (int) $request->integer('box_id'));
        }

        if ($request->filled('structure_id')) {
            $query->where('structure_id', (int) $request->integer('structure_id'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 20)));
    }
}
