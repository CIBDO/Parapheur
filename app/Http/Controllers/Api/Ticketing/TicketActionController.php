<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticketing\TicketAssignmentService;
use App\Services\Ticketing\TicketEscalationService;
use App\Services\Ticketing\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketActionController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketAssignmentService $assignments,
        private readonly TicketEscalationService $escalations,
    ) {}

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $validated = $request->validate([
            'support_team_id' => 'nullable|exists:support_teams,id',
            'assignee_id' => 'nullable|exists:users,id',
            'comment' => 'nullable|string|max:2000',
        ]);

        try {
            $fresh = $this->assignments->assign(
                $ticket,
                $request->user(),
                $validated['support_team_id'] ?? null,
                $validated['assignee_id'] ?? null,
                $validated['comment'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fresh);
    }

    public function takeCharge(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('takeCharge', $ticket);

        $fresh = $this->assignments->takeCharge($ticket, $request->user());

        return response()->json($fresh);
    }

    public function escalate(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('escalate', $ticket);

        $validated = $request->validate([
            'to_team_id' => 'nullable|exists:support_teams,id',
            'to_user_id' => 'nullable|exists:users,id',
            'reason' => 'nullable|string|max:2000',
        ]);

        try {
            $fresh = $this->escalations->escalate(
                $ticket,
                $request->user(),
                $validated['to_team_id'] ?? null,
                $validated['to_user_id'] ?? null,
                $validated['reason'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fresh);
    }

    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('resolve', $ticket);

        $validated = $request->validate([
            'summary' => 'required|string|max:5000',
            'await_validation' => 'nullable|boolean',
        ]);

        $fresh = $this->tickets->resolve(
            $ticket,
            $request->user(),
            $validated['summary'],
            $validated['await_validation'] ?? true,
        );

        return response()->json($fresh);
    }

    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('reopen', $ticket);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $fresh = $this->tickets->reopen($ticket, $request->user(), $validated['reason']);

        return response()->json($fresh);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('close', $ticket);

        $validated = $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $fresh = $this->tickets->close($ticket, $request->user(), $validated['comment'] ?? null);

        return response()->json($fresh);
    }

    public function cancel(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);
        abort_unless($request->user()->can('ticket.cancel') || $request->user()->can('ticket.admin') || $request->user()->can('admin.access'), 403);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $fresh = $this->tickets->cancel($ticket, $request->user(), $validated['reason']);

        return response()->json($fresh);
    }

    public function wait(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => 'required|in:EN_ATTENTE_DEMANDEUR,EN_ATTENTE_TIERS',
            'comment' => 'nullable|string|max:2000',
        ]);

        try {
            $fresh = $this->tickets->wait(
                $ticket,
                $request->user(),
                TicketStatus::from($validated['status']),
                $validated['comment'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fresh);
    }

    /**
     * Changement de statut (kanban) — passe toujours par la state machine via TicketService::transition.
     */
    public function changeStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => 'required|string',
            'comment' => 'nullable|string|max:2000',
        ]);

        try {
            $to = TicketStatus::from($validated['status']);
        } catch (\ValueError) {
            return response()->json(['message' => 'Statut invalide.'], 422);
        }

        try {
            $fresh = $this->tickets->transition(
                $ticket,
                $request->user(),
                $to,
                $validated['comment'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fresh);
    }

    public function satisfaction(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $validated = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $row = $this->tickets->satisfaction(
            $ticket,
            $request->user(),
            (int) $validated['score'],
            $validated['comment'] ?? null,
        );

        return response()->json($row, 201);
    }
}
