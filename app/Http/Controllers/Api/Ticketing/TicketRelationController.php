<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketRelation;
use App\Services\Ticketing\TicketRelationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketRelationController extends Controller
{
    public function __construct(
        private readonly TicketRelationService $relations,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return response()->json([
            'data' => $this->relations->listFor($ticket),
        ]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'related_ticket_id' => 'required|exists:tickets,id',
            'relation_type' => 'nullable|string|max:40|in:related,duplicate,parent,child,blocks,blocked_by',
        ]);

        $related = Ticket::query()->findOrFail($validated['related_ticket_id']);
        $this->authorize('view', $related);

        try {
            $relation = $this->relations->link(
                $ticket,
                $related,
                $validated['relation_type'] ?? 'related',
            );
        }
        catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($relation, 201);
    }

    public function destroy(Ticket $ticket, TicketRelation $relation): JsonResponse
    {
        $this->authorize('update', $ticket);

        try {
            $this->relations->unlink($ticket, $relation);
        }
        catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Relation supprimée']);
    }
}
