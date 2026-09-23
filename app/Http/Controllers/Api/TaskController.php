<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskDependency;
use App\Models\TaskDocument;
use App\Services\PrivateDocumentStorage;
use App\Services\Tasks\TaskAssignmentService;
use App\Services\Tasks\TaskDependencyService;
use App\Services\Tasks\TaskDocumentService;
use App\Services\Tasks\TaskService;
use App\Services\Tasks\TaskValidationService;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskWorkflowService $workflow,
        private readonly TaskAssignmentService $assignments,
        private readonly TaskValidationService $validations,
        private readonly TaskDependencyService $dependencies,
        private readonly TaskDocumentService $taskDocuments,
        private readonly PrivateDocumentStorage $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        return response()->json($this->tasks->list($request->user(), $request->all()));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        return response()->json($this->tasks->dashboard($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'validator_id' => ['nullable', 'exists:users,id'],
            'priority' => ['nullable', 'string'],
            'confidentiality' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'instruction_id' => ['nullable', 'exists:instructions,id'],
            'parent_id' => ['nullable', 'exists:tasks,id'],
            'contributor_ids' => ['nullable', 'array'],
            'contributor_ids.*' => ['integer', 'exists:users,id'],
            'as_draft' => ['nullable', 'boolean'],
            'is_personal' => ['nullable', 'boolean'],
            'source_kind' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
        ]);

        $task = $this->tasks->create($request->user(), $data);

        return response()->json($task, 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json($this->tasks->findFor($request->user(), $task));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'confidentiality' => ['nullable', 'string'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'validator_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'contributor_ids' => ['nullable', 'array'],
            'contributor_ids.*' => ['integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'is_personal' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->tasks->update($request->user(), $task, $data));
    }

    public function assign(Request $request, Task $task): JsonResponse
    {
        $this->authorize('assign', $task);
        $data = $request->validate([
            'assignee_id' => ['required', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'motif' => ['nullable', 'string'],
        ]);

        return response()->json($this->assignments->assign(
            $request->user(),
            $task,
            (int) $data['assignee_id'],
            $data['structure_id'] ?? null,
            $data['motif'] ?? null,
        ));
    }

    public function reassign(Request $request, Task $task): JsonResponse
    {
        $this->authorize('assign', $task);
        $data = $request->validate([
            'assignee_id' => ['required', 'exists:users,id'],
            'motif' => ['required', 'string'],
        ]);

        return response()->json($this->assignments->reassign(
            $request->user(),
            $task,
            (int) $data['assignee_id'],
            $data['motif'],
        ));
    }

    public function takeCharge(Request $request, Task $task): JsonResponse
    {
        $this->authorize('takeCharge', $task);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json($this->workflow->takeCharge($request->user(), $task, $data['comment'] ?? null));
    }

    public function start(Request $request, Task $task): JsonResponse
    {
        $this->authorize('takeCharge', $task);

        return response()->json($this->workflow->start($request->user(), $task));
    }

    public function wait(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json($this->workflow->setWaiting($request->user(), $task, $data['comment'] ?? null));
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        $this->authorize('complete', $task);
        $data = $request->validate([
            'summary' => ['nullable', 'string'],
            'result' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        try {
            return response()->json($this->workflow->complete($request->user(), $task, $data));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function validateTask(Request $request, Task $task): JsonResponse
    {
        $this->authorize('validate', $task);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json($this->validations->validate($request->user(), $task, $data['comment'] ?? null));
    }

    public function returnTask(Request $request, Task $task): JsonResponse
    {
        $this->authorize('returnTask', $task);
        $data = $request->validate([
            'motif' => ['required', 'string'],
            'comment' => ['nullable', 'string'],
            'new_due_at' => ['nullable', 'date'],
        ]);

        return response()->json($this->validations->returnForCorrection($request->user(), $task, $data));
    }

    public function cancel(Request $request, Task $task): JsonResponse
    {
        $this->authorize('cancel', $task);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json($this->workflow->cancel($request->user(), $task, $data['reason']));
    }

    public function publish(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        return response()->json($this->workflow->publish($request->user(), $task));
    }

    public function comments(Request $request, Task $task): JsonResponse
    {
        $this->authorize('comment', $task);
        $data = $request->validate(['body' => ['required', 'string']]);

        return response()->json($this->tasks->addComment($request->user(), $task, $data['body']), 201);
    }

    public function subtasks(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'string'],
        ]);

        return response()->json($this->tasks->createSubtask($request->user(), $task, $data), 201);
    }

    public function attachments(Request $request, Task $task): JsonResponse
    {
        $this->authorize('comment', $task);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'kind' => ['nullable', Rule::in(['attachment', 'proof'])],
        ]);

        $attachment = $this->tasks->addAttachment(
            $request->user(),
            $task,
            $data['file'],
            $data['kind'] ?? 'attachment',
        );

        return response()->json($attachment, 201);
    }

    public function downloadAttachment(Request $request, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $task);
        if ((int) $attachment->task_id !== (int) $task->id) {
            abort(404);
        }

        abort_unless($this->storage->exists($attachment->disk, $attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function history(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json(
            $task->histories()->with('user:id,name')->orderByDesc('created_at')->get()
        );
    }

    public function dependencies(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json($this->dependencies->list($request->user(), $task));
    }

    public function addDependency(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'related_task_id' => ['required', 'integer', 'exists:tasks,id'],
            'relation' => ['required', Rule::in(['blocks', 'blocked_by', 'depends_on', 'related_to'])],
        ]);

        try {
            $dep = $this->dependencies->add(
                $request->user(),
                $task,
                (int) $data['related_task_id'],
                $data['relation'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($dep, 201);
    }

    public function removeDependency(Request $request, Task $task, TaskDependency $dependency): JsonResponse
    {
        $this->authorize('update', $task);
        $this->dependencies->remove($request->user(), $task, $dependency);

        return response()->json(['ok' => true]);
    }

    public function documents(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json($this->taskDocuments->list($request->user(), $task));
    }

    public function linkDocument(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'role' => ['nullable', Rule::in(['working', 'proof', 'final', 'ged_link'])],
            'note' => ['nullable', 'string'],
        ]);

        $link = $this->taskDocuments->linkExisting(
            $request->user(),
            $task,
            (int) $data['document_id'],
            $data['role'] ?? 'ged_link',
            $data['note'] ?? null,
        );

        return response()->json($link, 201);
    }

    public function createOfficeDocument(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'format' => ['nullable', Rule::in(['docx', 'xlsx', 'pptx'])],
        ]);

        $link = $this->taskDocuments->createOfficeDocument($request->user(), $task, $data);

        return response()->json($link, 201);
    }

    public function createFromTemplate(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:document_templates,id'],
            'variables' => ['nullable', 'array'],
        ]);

        $link = $this->taskDocuments->createFromTemplate(
            $request->user(),
            $task,
            (int) $data['template_id'],
            $data['variables'] ?? [],
        );

        return response()->json($link, 201);
    }

    public function submitDocumentToGed(Request $request, Task $task, TaskDocument $taskDocument): JsonResponse
    {
        $this->authorize('update', $task);
        $data = $request->validate([
            'classification_node_id' => ['nullable', 'integer', 'exists:classification_nodes,id'],
        ]);

        $link = $this->taskDocuments->submitToGed(
            $request->user(),
            $task,
            $taskDocument,
            isset($data['classification_node_id']) ? (int) $data['classification_node_id'] : null,
        );

        return response()->json($link);
    }

    public function unlinkDocument(Request $request, Task $task, TaskDocument $taskDocument): JsonResponse
    {
        $this->authorize('update', $task);
        $this->taskDocuments->unlink($request->user(), $task, $taskDocument);

        return response()->json(['ok' => true]);
    }
}
