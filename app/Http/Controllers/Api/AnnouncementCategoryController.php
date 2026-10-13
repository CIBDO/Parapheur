<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnnouncementCategory;
use App\Models\AnnouncementImportance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementCategoryController extends Controller
{
    public function importances(): JsonResponse
    {
        return response()->json(
            AnnouncementImportance::query()->where('is_active', true)->orderBy('rank')->get()
        );
    }

    public function index(): JsonResponse
    {
        return response()->json(
            AnnouncementCategory::query()->with('defaultImportance')->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AnnouncementCategory::class);
        $category = AnnouncementCategory::query()->create($this->validated($request));

        return response()->json($category->load('defaultImportance'), 201);
    }

    public function update(Request $request, AnnouncementCategory $category): JsonResponse
    {
        $this->authorize('update', $category);
        $category->update($this->validated($request, $category));

        return response()->json($category->refresh()->load('defaultImportance'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AnnouncementCategory $category = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('announcement_categories', 'code')->ignore($category?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validation_circuit' => ['required', 'in:direct,structure,institutional'],
            'default_importance_id' => ['nullable', 'integer', 'exists:announcement_importances,id'],
            'default_confidentiality' => ['sometimes', 'in:general,targeted,restricted,confidential'],
            'retention_days' => ['nullable', 'integer', 'min:1'],
            'acknowledgement_required' => ['sometimes', 'boolean'],
            'revalidate_on_change' => ['sometimes', 'boolean'],
            'notify_on_publish' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
