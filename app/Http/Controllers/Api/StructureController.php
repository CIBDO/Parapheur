<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Structure;
use App\Models\StructureType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StructureController extends Controller
{
    public function index(): JsonResponse
    {
        $structures = Structure::query()
            ->with(['type', 'parent:id,code,name'])
            ->withCount(['users', 'children'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($structures);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $structure = Structure::query()->create($data);
        $structure->load(['type', 'parent:id,code,name']);

        return response()->json($structure, 201);
    }

    public function update(Request $request, Structure $structure): JsonResponse
    {
        $data = $this->validated($request, $structure);

        if (isset($data['parent_id']) && (int) $data['parent_id'] === $structure->id) {
            return response()->json([
                'message' => 'Une structure ne peut pas être son propre parent.',
            ], 422);
        }

        $structure->update($data);
        $structure->load(['type', 'parent:id,code,name']);

        return response()->json($structure);
    }

    public function destroy(Structure $structure): JsonResponse
    {
        if ($structure->children()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une structure qui a des sous-structures.',
            ], 422);
        }

        if ($structure->users()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une structure rattachée à des utilisateurs.',
            ], 422);
        }

        if ($structure->documents()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une structure liée à des documents.',
            ], 422);
        }

        $structure->delete();

        return response()->json(['message' => 'Structure supprimée']);
    }

    public function types(): JsonResponse
    {
        return response()->json(
            StructureType::query()->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:structure_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = StructureType::query()->create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json($type, 201);
    }

    public function updateType(Request $request, StructureType $structureType): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('structure_types', 'code')->ignore($structureType->id)],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $structureType->update([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? $structureType->sort_order,
        ]);

        return response()->json($structureType->fresh());
    }

    public function destroyType(StructureType $structureType): JsonResponse
    {
        if ($structureType->structures()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un type encore utilisé par des structures.',
            ], 422);
        }

        $structureType->delete();

        return response()->json(['message' => 'Type de structure supprimé']);
    }

    private function validated(Request $request, ?Structure $structure = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('structures', 'code')->ignore($structure?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'structure_type_id' => ['nullable', 'exists:structure_types,id'],
            'parent_id' => ['nullable', 'exists:structures,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($structure?->is_active ?? true);
        $data['sort_order'] = $data['sort_order'] ?? ($structure?->sort_order ?? 0);

        return $data;
    }
}
