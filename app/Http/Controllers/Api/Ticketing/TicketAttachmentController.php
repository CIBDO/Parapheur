<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\Ticketing\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
    ) {}

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $attachment = $this->tickets->addAttachment(
            $ticket,
            $request->user(),
            $request->file('file'),
        );

        return response()->json($attachment, 201);
    }

    public function download(Ticket $ticket, TicketAttachment $attachment): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $ticket);
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }

    public function destroy(Request $request, Ticket $ticket, TicketAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $ticket);

        try {
            $this->tickets->deleteAttachment($ticket, $attachment, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Pièce jointe supprimée']);
    }
}
