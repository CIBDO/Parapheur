<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $query = Asset::query()
            ->with(['type:id,code,name', 'owner:id,name', 'structure:id,code,name'])
            ->orderBy('name');

        if ($q = $request->input('q')) {
            $like = '%'.addcslashes((string) $q, '%_\\').'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('inventory_number', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('location', 'like', $like);
            });
        }

        if ($request->filled('asset_type_id')) {
            $query->where('asset_type_id', (int) $request->input('asset_type_id'));
        }

        if ($request->filled('structure_id')) {
            $query->where('structure_id', (int) $request->input('structure_id'));
        }

        return response()->json($query->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'asset_type_id' => 'nullable|exists:asset_types,id',
            'inventory_number' => 'nullable|string|max:100',
            'name' => 'required|string|max:200',
            'location' => 'nullable|string|max:255',
            'owner_user_id' => 'nullable|exists:users,id',
            'structure_id' => 'nullable|exists:structures,id',
            'status' => 'nullable|string|max:50',
            'meta' => 'nullable|array',
        ]);

        $asset = Asset::query()->create($validated);

        return response()->json($asset->load(['type', 'owner', 'structure']), 201);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeView($request);

        return response()->json($asset->load(['type', 'owner', 'structure']));
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'asset_type_id' => 'nullable|exists:asset_types,id',
            'inventory_number' => 'nullable|string|max:100',
            'name' => 'sometimes|required|string|max:200',
            'location' => 'nullable|string|max:255',
            'owner_user_id' => 'nullable|exists:users,id',
            'structure_id' => 'nullable|exists:structures,id',
            'status' => 'nullable|string|max:50',
            'meta' => 'nullable|array',
        ]);

        $asset->update($validated);

        return response()->json($asset->fresh(['type', 'owner', 'structure']));
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()->can('ticket.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless(
            $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );
    }
}
