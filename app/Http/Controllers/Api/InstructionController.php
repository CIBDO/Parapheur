<?php

namespace App\Http\Controllers\Api;

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

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'assignee_id' => ['required', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'priority' => ['nullable', 'string'],
            'confidentiality' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'create_execution_task' => ['nullable', 'boolean'],
            'source_kind' => ['nullable', 'string'],
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
            'structure:id,code,name',
            'document:id,reference',
            'tasks.assignee:id,name',
            'updates.user:id,name',
            'recipients',
        ]));
    }

    public function updateStatus(Request $request, Instruction $instruction): JsonResponse
    {
        $this->authorize('update', $instruction);

        $data = $request->validate([
            'status' => ['required', Rule::in(['brouillon', 'a_faire', 'en_cours', 'executee', 'cloturee', 'annulee'])],
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
        ]);

        $task = $this->instructions->addExecutionTask($request->user(), $instruction, $data);

        return response()->json($task, 201);
    }
}
