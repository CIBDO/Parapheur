<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\ClassificationNode;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use App\Services\DocumentClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GedClassificationController extends Controller
{
    public function __construct(
        private readonly DocumentClassificationService $classification,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $data = $request->validate([
            'structure_id' => ['nullable', 'exists:structures,id'],
            'all' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->classification->tree(
                $data['structure_id'] ?? null,
                ! ($data['all'] ?? false),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageClassification($request->user()), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:classification_nodes,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $node = $this->classification->create($data, $request->user());

        return response()->json($node, 201);
    }

    public function update(Request $request, ClassificationNode $classificationNode): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageClassification($request->user()), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:classification_nodes,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'code' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $node = $this->classification->update($classificationNode, $data, $request->user());

        return response()->json($node);
    }

    public function destroy(Request $request, ClassificationNode $classificationNode): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageClassification($request->user()), 403);

        $this->classification->delete($classificationNode, $request->user());

        return response()->json(['message' => 'Nœud supprimé.']);
    }
}
