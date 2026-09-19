<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticketing\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketWorklogController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $worklogs = $ticket->worklogs()
            ->with('user:id,name')
            ->orderByDesc('worked_at')
            ->get();

        return response()->json($worklogs);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'minutes' => 'required|integer|min:1|max:1440',
            'note' => 'nullable|string|max:2000',
            'worked_at' => 'nullable|date',
        ]);

        try {
            $worklog = $this->tickets->addWorklog(
                $ticket,
                $request->user(),
                (int) $validated['minutes'],
                $validated['note'] ?? null,
                $validated['worked_at'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($worklog, 201);
    }
}
