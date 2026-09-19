<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticketing\TicketingAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingAiController extends Controller
{
    public function __construct(
        private readonly TicketingAiService $ai,
    ) {}

    /**
     * Suggestions heuristiques — jamais appliquées automatiquement.
     */
    public function suggest(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:10000',
        ]);

        $suggestions = $this->ai->suggest(
            $validated['title'],
            $validated['description'] ?? null,
        );

        return response()->json([
            'suggestions' => $suggestions,
            'requires_human_validation' => true,
        ]);
    }
}
