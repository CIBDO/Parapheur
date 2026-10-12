<?php

namespace App\Http\Controllers\Api;

use App\Enums\InstructionKind;
use App\Enums\InstructionStatus;
use App\Http\Controllers\Controller;
use App\Models\Instruction;
use App\Services\Tasks\InstructionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstructionController extends Controller
{
    public function __construct(
        private readonly InstructionService $instructions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Instruction::class);

        return response()->json($this->instructions->list($request->user(), $request->all()));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Instruction::class);

        $kind = $request->input('kind', InstructionKind::Imputation->value);
        $isDirective = $kind === InstructionKind::Directive->value;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'kind' => ['nullable', Rule::in([InstructionKind::Imputation->value, InstructionKind::Directive->value])],
            'assignee_id' => [$isDirective ? 'nullable' : 'required', 'exists:users,id'],
            'coordinator_id' => ['nullable', 'exists:users,id'],
            'parent_id' => ['nullable', 'exists:instructions,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'priority' => ['nullable', 'string'],
            'confidentiality' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'create_execution_task' => ['nullable', 'boolean'],
            'source_kind' => ['nullable', 'string'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'recipient_structure_ids' => ['nullable', 'array'],
            'recipient_structure_ids.*' => ['integer', 'exists:structures,id'],
            'status' => ['nullable', 'string'],
        ]);

        $instruction = $this->instructions->create($request->user(), $data);

        return response()->json($instruction, 201);
    }

    public function show(Request $request, Instruction $instruction): JsonResponse
    {
        $this->authorize('view', $instruction);

        return response()->json($instruction->load([
            'assignee:id,name',
            'issuer:id,name',
            'coordinator:id,name',
            'structure:id,code,name',
            'document:id,reference',
            'parent:id,reference,title',
            'tasks.assignee:id,name',
            'updates.user:id,name',
            'recipients.user:id,name',
            'recipients.structure:id,code,name',
        ]));
    }

    public function updateStatus(Request $request, Instruction $instruction): JsonResponse
    {
        $this->authorize('update', $instruction);

        $allowed = $instruction->isDirective()
            ? InstructionStatus::directiveValues()
            : InstructionStatus::imputationValues();

        $data = $request->validate([
            'status' => ['required', Rule::in($allowed)],
            'body' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->instructions->updateStatus($request->user(), $instruction, $data['status'], $data['body'] ?? null)
        );
    }

    public function addTask(Request $request, Instruction $instruction): JsonResponse
    {
        $this->authorize('assign', $instruction);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['required', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'string'],
            'task_type_id' => ['nullable', 'exists:task_types,id'],
        ]);

        $task = $this->instructions->addExecutionTask($request->user(), $instruction, $data);

        return response()->json($task, 201);
    }
}
