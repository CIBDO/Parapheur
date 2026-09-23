<?php

namespace App\Support;

/**
 * Présentation institutionnelle unifiée des notifications E-Tresor / DGTCP.
 */
final class NotificationPresentation
{
    public const FOOTER = 'Message automatique du Bureau Numérique — Direction Générale du Trésor et de la Comptabilité Publique (DGTCP). Merci de ne pas répondre directement à cet e-mail.';

    public static function greeting(string $name): string
    {
        $name = trim($name);

        return $name !== '' ? 'Bonjour '.$name.',' : 'Bonjour,';
    }

    public static function mailSubject(string $domain, string $title): string
    {
        return '[E-Tresor · '.$domain.'] '.$title;
    }

    /**
     * Icône Tabler selon le module / l’événement stocké en base.
     *
     * @param  array<string, mixed>  $data
     */
    public static function icon(array $data): string
    {
        $event = (string) ($data['event'] ?? '');
        $url = (string) ($data['url'] ?? '');

        return match (true) {
            str_contains($url, '/espace/collaboratifs') || isset($data['workspace_id']) => 'tabler-users-group',
            str_contains($url, '/taches') || isset($data['task_id']) => match ($event) {
                'assigned', 'reassigned' => 'tabler-user-check',
                'taken_charge' => 'tabler-hand-click',
                'completed', 'validated' => 'tabler-circle-check',
                'returned', 'cancelled' => 'tabler-arrow-back-up',
                'comment' => 'tabler-message',
                'reminder', 'validation_requested' => 'tabler-bell-ringing',
                default => 'tabler-checkbox',
            },
            str_contains($url, '/ticketing') || isset($data['ticket_id']) => match ($event) {
                'created' => 'tabler-ticket',
                'assigned', 'transferred' => 'tabler-user-check',
                'taken_charge' => 'tabler-hand-click',
                'comment_requester', 'comment_agent', 'requester_replied' => 'tabler-message',
                'waiting_requester' => 'tabler-clock-pause',
                'escalated' => 'tabler-arrow-up-right',
                'sla_warning', 'ola_warning' => 'tabler-alert-triangle',
                'sla_breach', 'ola_breach' => 'tabler-alert-octagon',
                'resolved', 'solution_accepted' => 'tabler-circle-check',
                'reopened' => 'tabler-rotate-clockwise',
                'closed' => 'tabler-lock',
                'approval_accepted', 'approval_refused' => 'tabler-file-check',
                default => 'tabler-headset',
            },
            str_contains($url, '/courrier') || isset($data['correspondence_id']) => match ($event) {
                'assigned', 'reassigned' => 'tabler-mail-forward',
                'taken_charge' => 'tabler-hand-click',
                'reply_prepared' => 'tabler-file-pencil',
                'dispatched' => 'tabler-send',
                'reminder' => 'tabler-clock-exclamation',
                'complement_requested' => 'tabler-message-question',
                default => 'tabler-mail',
            },
            str_contains($url, '/parapheur/reunions') || isset($data['meeting_id']) => match ($event) {
                'invitation' => 'tabler-mail-opened',
                'place_changed', 'time_changed', 'postponed' => 'tabler-calendar-event',
                'cancelled' => 'tabler-calendar-off',
                'minutes_available' => 'tabler-file-text',
                'decision_assigned', 'decision_late' => 'tabler-gavel',
                'confirmation_reminder', 'meeting_reminder' => 'tabler-bell-ringing',
                default => 'tabler-users-group',
            },
            str_contains($url, '/parapheur/agenda') || isset($data['appointment_id']) => match ($event) {
                'confirmed', 'validated' => 'tabler-calendar-check',
                'rejected', 'cancelled' => 'tabler-calendar-off',
                'reminder' => 'tabler-bell-ringing',
                'rescheduled', 'slot_proposed' => 'tabler-calendar-time',
                default => 'tabler-calendar-event',
            },
            str_contains($url, '/parapheur') || isset($data['document_id']) => match ($event) {
                'returned' => 'tabler-arrow-back-up',
                'validated', 'vised' => 'tabler-stamp',
                'rejected' => 'tabler-x',
                'instruction' => 'tabler-list-check',
                default => 'tabler-file-text',
            },
            default => 'tabler-bell',
        };
    }

    /**
     * Couleur Vuetify (tonal avatar) selon criticité / module.
     *
     * @param  array<string, mixed>  $data
     */
    public static function color(array $data, bool $isSeen): ?string
    {
        if ($isSeen) {
            return null;
        }

        $event = (string) ($data['event'] ?? '');

        return match ($event) {
            'sla_breach', 'ola_breach', 'rejected', 'approval_refused', 'cancelled', 'decision_late' => 'error',
            'sla_warning', 'ola_warning', 'waiting_requester', 'returned', 'escalated', 'reminder', 'confirmation_reminder', 'meeting_reminder', 'complement_requested', 'rescheduled', 'postponed' => 'warning',
            'resolved', 'closed', 'validated', 'vised', 'approval_accepted', 'solution_accepted', 'confirmed', 'dispatched', 'finished', 'minutes_available' => 'success',
            default => 'primary',
        };
    }

    /**
     * Libellé de domaine pour affichage cloche.
     *
     * @param  array<string, mixed>  $data
     */
    public static function domainLabel(array $data): string
    {
        $url = (string) ($data['url'] ?? '');

        return match (true) {
            str_contains($url, '/espace/collaboratifs') || isset($data['workspace_id']) => 'Espace collaboratif',
            str_contains($url, '/ticketing') || isset($data['ticket_id']) => 'Centre de services',
            str_contains($url, '/courrier') || isset($data['correspondence_id']) => 'Courrier',
            str_contains($url, '/parapheur/reunions') || isset($data['meeting_id']) => 'Réunions',
            str_contains($url, '/parapheur/agenda') || isset($data['appointment_id']) => 'Agenda',
            str_contains($url, '/parapheur') || isset($data['document_id']) => 'Parapheur',
            default => 'E-Tresor',
        };
    }
}
