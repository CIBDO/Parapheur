<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Policies\DocumentPolicy;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GedCategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return response()->json([
            'data' => DocumentCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageCategories($request->user()), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:document_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = DocumentCategory::query()->create($data + ['is_active' => $data['is_active'] ?? true]);
        $this->audit->log('document_category.created', $category);

        return response()->json($category, 201);
    }

    public function update(Request $request, DocumentCategory $documentCategory): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageCategories($request->user()), 403);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('document_categories', 'code')->ignore($documentCategory->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $documentCategory->update($data);
        $this->audit->log('document_category.updated', $documentCategory);

        return response()->json($documentCategory->fresh());
    }

    public function destroy(Request $request, DocumentCategory $documentCategory): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageCategories($request->user()), 403);

        if ($documentCategory->documents()->exists()) {
            return response()->json(['message' => 'Des documents utilisent encore cette catégorie.'], 422);
        }

        $this->audit->log('document_category.deleted', $documentCategory);
        $documentCategory->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }
}
