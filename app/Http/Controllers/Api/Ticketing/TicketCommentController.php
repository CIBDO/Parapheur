<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticketing\TicketAccessService;
use App\Services\Ticketing\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketAccessService $access,
    ) {}

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $query = $ticket->comments()->with('user:id,name')->orderBy('created_at');

        if (! $this->access->canInternalNote($request->user(), $ticket)) {
            $query->where('is_internal', false);
        }

        return response()->json($query->get());
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('comment', $ticket);

        $validated = $request->validate([
            'body' => 'required|string|max:10000',
            'is_internal' => 'nullable|boolean',
        ]);

        $isInternal = (bool) ($validated['is_internal'] ?? false);

        if ($isInternal && ! $this->access->canInternalNote($request->user(), $ticket)) {
            abort(403, 'Notes internes non autorisées.');
        }

        $comment = $this->tickets->addComment(
            $ticket,
            $request->user(),
            $validated['body'],
            $isInternal,
        );

        return response()->json($comment, 201);
    }
}
