<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class MeetingTemplateController extends Controller
{
    public const KINDS = [
        'convocation',
        'agenda',
        'attendance',
        'decisions',
        'cr_simple',
        'cr_detaille',
        'pv',
        'releve_decisions',
    ];

    public function index(): JsonResponse
    {
        return response()->json(
            MeetingTemplate::query()->orderBy('kind')->orderByDesc('is_default')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $template = DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                MeetingTemplate::query()->where('kind', $data['kind'])->update(['is_default' => false]);
            }

            return MeetingTemplate::query()->create($data);
        });

        return response()->json($template, 201);
    }

    public function update(Request $request, MeetingTemplate $meetingTemplate): JsonResponse
    {
        $data = $this->validated($request, $meetingTemplate);
        $template = DB::transaction(function () use ($meetingTemplate, $data) {
            if ($data['is_default']) {
                MeetingTemplate::query()
                    ->where('kind', $data['kind'])
                    ->where('id', '!=', $meetingTemplate->id)
                    ->update(['is_default' => false]);
            }

            $meetingTemplate->update($data);

            return $meetingTemplate->fresh();
        });

        return response()->json($template);
    }

    public function destroy(MeetingTemplate $meetingTemplate): JsonResponse
    {
        $meetingTemplate->delete();

        return response()->json(['message' => 'Modèle supprimé']);
    }

    private function validated(Request $request, ?MeetingTemplate $meetingTemplate = null): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_default'] = array_key_exists('is_default', $data)
            ? (bool) $data['is_default']
            : ($meetingTemplate?->is_default ?? false);
        $data['is_active'] = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($meetingTemplate?->is_active ?? true);

        return $data;
    }
}
