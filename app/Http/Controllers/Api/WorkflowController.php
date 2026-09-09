<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpectedAction;
use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Workflow::query()
                ->with(['steps' => fn ($q) => $q->orderBy('step_order'), 'structure'])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $workflow = DB::transaction(function () use ($data) {
            $workflow = Workflow::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'kind' => 'predefini',
                'is_active' => $data['is_active'] ?? true,
                'structure_id' => $data['structure_id'] ?? null,
            ]);

            $this->syncSteps($workflow, $data['steps'] ?? []);

            return $workflow->load(['steps', 'structure']);
        });

        return response()->json($workflow, 201);
    }

    public function update(Request $request, Workflow $workflow): JsonResponse
    {
        $data = $this->validated($request, $workflow);

        $workflow = DB::transaction(function () use ($workflow, $data) {
            $workflow->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? $workflow->is_active,
                'structure_id' => $data['structure_id'] ?? null,
            ]);

            if (array_key_exists('steps', $data)) {
                $workflow->steps()->delete();
                $this->syncSteps($workflow, $data['steps'] ?? []);
            }

            return $workflow->load(['steps', 'structure']);
        });

        return response()->json($workflow);
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $inUse = $workflow->instances()->where('status', 'en_cours')->exists();
        if ($inUse) {
            return response()->json(['message' => 'Circuit utilisé par des dossiers en cours.'], 422);
        }

        $workflow->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Workflow $workflow = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('workflows', 'code')->ignore($workflow?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'steps' => ['nullable', 'array', 'min:1'],
            'steps.*.name' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.step_order' => ['required_with:steps', 'integer', 'min:1'],
            'steps.*.role_name' => ['nullable', 'string', 'max:100'],
            'steps.*.structure_id' => ['nullable', 'exists:structures,id'],
            'steps.*.expected_action' => ['required_with:steps', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'steps.*.is_optional' => ['nullable', 'boolean'],
        ]);
    }

    private function syncSteps(Workflow $workflow, array $steps): void
    {
        foreach ($steps as $step) {
            WorkflowStep::query()->create([
                'workflow_id' => $workflow->id,
                'step_order' => $step['step_order'],
                'name' => $step['name'],
                'role_name' => $step['role_name'] ?? null,
                'structure_id' => $step['structure_id'] ?? null,
                'expected_action' => $step['expected_action'],
                'is_optional' => $step['is_optional'] ?? false,
            ]);
        }
    }
}
