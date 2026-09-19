<?php

namespace App\Services\Ticketing;

use App\Models\Ticket;
use App\Models\TicketChannel;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Préparation Email → Ticket (Phase 2).
 * Ne consomme pas encore une boîte IMAP ; fournit le point d'entrée pour un job futur.
 */
class EmailInboundTicketService
{
    public function __construct(
        private readonly TicketService $tickets,
    ) {}

    /**
     * @param  array{from: string, subject: string, body: string, message_id?: string}  $mail
     */
    public function ingest(array $mail, ?User $fallbackRequester = null): ?Ticket
    {
        $from = strtolower(trim($mail['from'] ?? ''));
        $subject = trim($mail['subject'] ?? 'Demande e-mail');
        $body = trim($mail['body'] ?? '');

        if ($from === '' || $subject === '') {
            return null;
        }

        $requester = User::query()->where('email', $from)->first() ?? $fallbackRequester;
        if (! $requester) {
            Log::info('ticketing.email_inbound.unknown_sender', ['from' => $from]);

            return null;
        }

        // Anti-doublon simple sur message_id stocké en source
        $messageId = $mail['message_id'] ?? null;
        if ($messageId) {
            $existing = Ticket::query()
                ->where('source', 'email:'.$messageId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $channelId = TicketChannel::query()->where('code', 'EMAIL')->value('id');

        return $this->tickets->create($requester, [
            'title' => Str::limit($subject, 240),
            'description' => $body,
            'channel_id' => $channelId,
            'source' => $messageId ? 'email:'.$messageId : 'email',
            'requester_id' => $requester->id,
            'structure_id' => $requester->structure_id,
        ]);
    }
}
