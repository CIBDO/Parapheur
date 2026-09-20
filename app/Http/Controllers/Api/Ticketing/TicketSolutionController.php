<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketSolution;
use App\Services\Ticketing\TicketSolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketSolutionController extends Controller
{
    public function __construct(
        private readonly TicketSolutionService $solutions,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return response()->json(['data' => $this->solutions->list($ticket)]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('resolve', $ticket);

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
            'solution_type' => 'nullable|in:solution,workaround',
            'await_validation' => 'nullable|boolean',
        ]);

        $solution = $this->solutions->propose(
            $ticket,
            $request->user(),
            $validated['content'],
            $validated['solution_type'] ?? 'solution',
            $validated['await_validation'] ?? true,
        );

        return response()->json($solution, 201);
    }

    public function accept(Request $request, Ticket $ticket, TicketSolution $solution): JsonResponse
    {
        $this->authorize('close', $ticket);

        try {
            $fresh = $this->solutions->accept($ticket, $solution, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fresh);
    }

    public function refuse(Request $request, Ticket $ticket, TicketSolution $solution): JsonResponse
    {
        $this->authorize('reopen', $ticket);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        try {
            $fresh = $this->solutions->refuse($ticket, $solution, $request->user(), $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fresh);
    }
}
