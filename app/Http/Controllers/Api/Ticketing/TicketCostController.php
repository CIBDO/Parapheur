<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCost;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketCostController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $costs = $ticket->costs()->with('user:id,name')->get();

        return response()->json([
            'data' => $costs,
            'total' => $costs->sum('amount'),
        ]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cost_type' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'note' => 'nullable|string|max:2000',
            'cost_date' => 'nullable|date',
            'ticket_worklog_id' => 'nullable|exists:ticket_worklogs,id',
        ]);

        $cost = DB::transaction(function () use ($ticket, $request, $validated) {
            $row = TicketCost::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'cost_type' => $validated['cost_type'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'XOF',
                'note' => $validated['note'] ?? null,
                'cost_date' => $validated['cost_date'] ?? now()->toDateString(),
                'ticket_worklog_id' => $validated['ticket_worklog_id'] ?? null,
            ]);

            $this->audit->log('ticket.cost_added', $ticket, [
                'actor_id' => $request->user()->id,
                'cost_id' => $row->id,
            ]);

            return $row->fresh('user');
        });

        return response()->json($cost, 201);
    }

    public function destroy(Request $request, Ticket $ticket, TicketCost $cost): JsonResponse
    {
        $this->authorize('update', $ticket);
        abort_unless((int) $cost->ticket_id === (int) $ticket->id, 404);

        $id = $cost->id;
        $cost->delete();

        $this->audit->log('ticket.cost_deleted', $ticket, [
            'actor_id' => $request->user()->id,
            'cost_id' => $id,
        ]);

        return response()->json(['message' => 'Coût supprimé']);
    }
}
