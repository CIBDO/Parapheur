<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            MeetingType::query()->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $type = MeetingType::query()->create($this->validated($request));

        return response()->json($type, 201);
    }

    public function update(Request $request, MeetingType $meetingType): JsonResponse
    {
        $meetingType->update($this->validated($request, $meetingType));

        return response()->json($meetingType->fresh());
    }

    public function destroy(MeetingType $meetingType): JsonResponse
    {
        if ($meetingType->meetings()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un type déjà utilisé par des réunions.',
            ], 422);
        }

        $meetingType->delete();

        return response()->json(['message' => 'Type de réunion supprimé']);
    }

    private function validated(Request $request, ?MeetingType $meetingType = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('meeting_types', 'code')->ignore($meetingType?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($meetingType?->is_active ?? true);
        $data['sort_order'] = $data['sort_order'] ?? ($meetingType?->sort_order ?? 0);

        return $data;
    }
}
