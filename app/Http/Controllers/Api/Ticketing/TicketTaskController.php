<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Services\Ticketing\TicketAccessService;
use App\Services\Ticketing\TicketTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketTaskController extends Controller
{
    public function __construct(
        private readonly TicketTaskService $tasks,
        private readonly TicketAccessService $access,
    ) {}

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $includePrivate = $this->access->canInternalNote($request->user(), $ticket);

        return response()->json(['data' => $this->tasks->list($ticket, $includePrivate)]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'content' => 'nullable|string',
            'assignee_id' => 'nullable|exists:users,id',
            'instruction_id' => 'nullable|exists:instructions,id',
            'status' => 'nullable|in:todo,doing,done',
            'category' => 'nullable|string|max:100',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_private' => 'nullable|boolean',
            'planned_start_at' => 'nullable|date',
            'planned_end_at' => 'nullable|date',
        ]);

        $task = $this->tasks->create($ticket, $request->user(), $validated);

        return response()->json($task, 201);
    }

    public function update(Request $request, Ticket $ticket, TicketTask $task): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'content' => 'nullable|string',
            'assignee_id' => 'nullable|exists:users,id',
            'instruction_id' => 'nullable|exists:instructions,id',
            'status' => 'nullable|in:todo,doing,done',
            'category' => 'nullable|string|max:100',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_private' => 'nullable|boolean',
            'planned_start_at' => 'nullable|date',
            'planned_end_at' => 'nullable|date',
        ]);

        try {
            $fresh = $this->tasks->update($ticket, $task, $request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fresh);
    }

    public function destroy(Request $request, Ticket $ticket, TicketTask $task): JsonResponse
    {
        $this->authorize('update', $ticket);

        try {
            $this->tasks->delete($ticket, $task, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Tâche supprimée']);
    }
}
