<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Models\CorrespondenceChannel;
use App\Models\CorrespondencePriority;
use App\Models\CorrespondenceCategory;
use App\Models\CorrespondenceQualification;
use App\Models\CorrespondenceAssignmentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailAdminController extends Controller
{
    // ========== Canaux ==========

    public function channelsIndex(): JsonResponse
    {
        return response()->json(CorrespondenceChannel::query()->orderBy('sort_order')->orderBy('name')->get());
    }

    public function channelsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:correspondence_channels,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $channel = CorrespondenceChannel::query()->create($validated);

        return response()->json($channel, 201);
    }

    public function channelsUpdate(Request $request, CorrespondenceChannel $channel): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:correspondence_channels,code,'.$channel->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $channel->update($validated);

        return response()->json($channel);
    }

    public function channelsToggle(CorrespondenceChannel $channel): JsonResponse
    {
        $channel->is_active = ! $channel->is_active;
        $channel->save();

        return response()->json($channel);
    }

    public function channelsDestroy(CorrespondenceChannel $channel): JsonResponse
    {
        $channel->delete();

        return response()->json(['message' => 'Canal supprimé']);
    }

    // ========== Catégories ==========

    public function categoriesIndex(): JsonResponse
    {
        return response()->json(CorrespondenceCategory::query()->orderBy('sort_order')->orderBy('name')->get());
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:correspondence_categories,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $category = CorrespondenceCategory::query()->create($validated);

        return response()->json($category, 201);
    }

    public function categoriesUpdate(Request $request, CorrespondenceCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:correspondence_categories,code,'.$category->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function categoriesToggle(CorrespondenceCategory $category): JsonResponse
    {
        $category->is_active = ! $category->is_active;
        $category->save();

        return response()->json($category);
    }

    public function categoriesDestroy(CorrespondenceCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée']);
    }

    // ========== Qualifications ==========

    public function qualificationsIndex(): JsonResponse
    {
        return response()->json(CorrespondenceQualification::query()->orderBy('sort_order')->orderBy('name')->get());
    }

    public function qualificationsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:correspondence_qualifications,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $qualification = CorrespondenceQualification::query()->create($validated);

        return response()->json($qualification, 201);
    }

    public function qualificationsUpdate(Request $request, CorrespondenceQualification $qualification): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:correspondence_qualifications,code,'.$qualification->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $qualification->update($validated);

        return response()->json($qualification);
    }

    public function qualificationsToggle(CorrespondenceQualification $qualification): JsonResponse
    {
        $qualification->is_active = ! $qualification->is_active;
        $qualification->save();

        return response()->json($qualification);
    }

    public function qualificationsDestroy(CorrespondenceQualification $qualification): JsonResponse
    {
        $qualification->delete();

        return response()->json(['message' => 'Qualification supprimée']);
    }

    // ========== Actions ==========

    public function actionsIndex(): JsonResponse
    {
        return response()->json(CorrespondenceAssignmentAction::query()->orderBy('sort_order')->orderBy('name')->get());
    }

    public function actionsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:correspondence_assignment_actions,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $action = CorrespondenceAssignmentAction::query()->create($validated);

        return response()->json($action, 201);
    }

    public function actionsUpdate(Request $request, CorrespondenceAssignmentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:correspondence_assignment_actions,code,'.$action->id,
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $action->update($validated);

        return response()->json($action);
    }

    public function actionsToggle(CorrespondenceAssignmentAction $action): JsonResponse
    {
        $action->is_active = ! $action->is_active;
        $action->save();

        return response()->json($action);
    }

    public function actionsDestroy(CorrespondenceAssignmentAction $action): JsonResponse
    {
        $action->delete();

        return response()->json(['message' => 'Action supprimée']);
    }

    public function prioritiesIndex(): JsonResponse
    {
        return response()->json(
            CorrespondencePriority::query()->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function prioritiesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:correspondence_priorities,code',
            'name' => 'required|string|max:200',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_urgent' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $priority = CorrespondencePriority::query()->create($validated);

        return response()->json($priority, 201);
    }

    public function prioritiesUpdate(Request $request, CorrespondencePriority $mailPriority): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:correspondence_priorities,code,'.$mailPriority->id,
            'name' => 'sometimes|required|string|max:200',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_urgent' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['code']) && $validated['code'] !== $mailPriority->code
            && Correspondence::query()->where('priority', $mailPriority->code)->exists()) {
            return response()->json([
                'message' => 'Ce code est déjà utilisé par des courriers.',
            ], 422);
        }

        $mailPriority->update($validated);

        return response()->json($mailPriority);
    }

    public function prioritiesToggle(CorrespondencePriority $mailPriority): JsonResponse
    {
        $mailPriority->is_active = ! $mailPriority->is_active;
        $mailPriority->save();

        return response()->json($mailPriority);
    }

    public function prioritiesDestroy(CorrespondencePriority $mailPriority): JsonResponse
    {
        if (Correspondence::query()->where('priority', $mailPriority->code)->exists()) {
            return response()->json([
                'message' => 'Cette priorité est utilisée par des courriers. Désactivez-la.',
            ], 422);
        }

        $mailPriority->delete();

        return response()->json(['message' => 'Priorité supprimée']);
    }
}
