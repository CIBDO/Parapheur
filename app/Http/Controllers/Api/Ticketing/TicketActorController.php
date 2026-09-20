<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticketing\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketActorController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $actors = $ticket->actors()->with('user:id,name,email')->get();

        return response()->json(['data' => $actors]);
    }

    public function syncObservers(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'observer_ids' => 'nullable|array',
            'observer_ids.*' => 'integer|exists:users,id',
        ]);

        $fresh = $this->tickets->syncObservers($ticket, $validated['observer_ids'] ?? []);

        return response()->json(['data' => $fresh->actors]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:observer,supplier',
        ]);

        $this->tickets->syncActor($ticket, (int) $validated['user_id'], $validated['role']);

        return response()->json([
            'data' => $ticket->actors()->with('user:id,name,email')->get(),
        ], 201);
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:observer,supplier',
        ]);

        $this->tickets->removeActor($ticket, (int) $validated['user_id'], $validated['role']);

        return response()->json(['message' => 'Acteur retiré']);
    }
}
