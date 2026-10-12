<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TaskType::query()->orderBy('sort_order')->orderBy('name');
        if (! $request->boolean('all')) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('task.manage') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:task_types,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $type = TaskType::query()->create([
            'code' => strtolower($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json($type, 201);
    }

    public function update(Request $request, TaskType $taskType): JsonResponse
    {
        abort_unless($request->user()->can('task.manage') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('task_types', 'code')->ignore($taskType->id)],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtolower($data['code']);
        }
        $taskType->fill($data)->save();

        return response()->json($taskType);
    }
}
