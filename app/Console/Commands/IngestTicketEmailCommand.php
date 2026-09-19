<?php

namespace App\Console\Commands;

use App\Services\Ticketing\EmailInboundTicketService;
use Illuminate\Console\Command;

class IngestTicketEmailCommand extends Command
{
    protected $signature = 'parapheur:ingest-ticket-email
                            {--from= : Adresse e-mail expéditeur}
                            {--subject= : Objet}
                            {--body= : Corps du message}
                            {--message-id= : Identifiant unique du message}';

    protected $description = 'Ingère un e-mail support en ticket (point d’entrée Phase 2 Email → Ticket)';

    public function handle(EmailInboundTicketService $inbound): int
    {
        $from = (string) $this->option('from');
        $subject = (string) $this->option('subject');
        $body = (string) ($this->option('body') ?: '');

        if ($from === '' || $subject === '') {
            $this->error('Options --from et --subject obligatoires.');

            return self::FAILURE;
        }

        $ticket = $inbound->ingest([
            'from' => $from,
            'subject' => $subject,
            'body' => $body,
            'message_id' => $this->option('message-id') ?: null,
        ]);

        if (! $ticket) {
            $this->warn('Aucun ticket créé (expéditeur inconnu ou données invalides).');
            $this->line('Astuce : l’adresse --from doit correspondre à un utilisateur existant.');

            return self::FAILURE;
        }

        $this->info('Ticket créé/réutilisé : '.$ticket->number.' (#'.$ticket->id.')');

        return self::SUCCESS;
    }
}
