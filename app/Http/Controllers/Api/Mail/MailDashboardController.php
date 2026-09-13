<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailDashboardController extends Controller
{
    public function orderOffice(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'received_today' => Correspondence::query()->where('direction', 'entrant')->whereDate('received_at', today())->count(),
            'to_qualify' => Correspondence::query()->whereIn('status', ['recu', 'a_qualifier'])->count(),
            'to_assign' => Correspondence::query()->whereIn('status', ['enregistre', 'a_affecter'])->count(),
            'unassigned' => Correspondence::query()->whereDoesntHave('assignments')->whereIn('status', ['enregistre', 'a_affecter', 'a_qualifier'])->count(),
            'in_processing' => Correspondence::query()->whereIn('status', ['affecte', 'pris_en_charge', 'en_traitement'])->count(),
            'to_dispatch' => Correspondence::query()->where('status', 'a_expedier')->count(),
            'dispatched_today' => Correspondence::query()->where('direction', 'sortant')->where('status', 'expedie')->whereDate('updated_at', today())->count(),
            'overdue' => Correspondence::query()->where('due_date', '<', today())->whereNotIn('status', ['classe', 'archive', 'annule', 'expedie', 'accuse_recu'])->count(),
            'my_pending' => Correspondence::query()->whereHas('assignments', function ($q) use ($user) {
                $q->where('to_user_id', $user->id)->whereIn('status', ['transmis', 'recu']);
            })->count(),
            'slips_in_progress' => \App\Models\TransmissionSlip::query()->whereIn('status', ['brouillon', 'valide', 'genere', 'imprime', 'transmis'])->count(),
            'circulation_sheets_open' => \App\Models\CirculationSheet::query()->where('status', 'en_cours')->count(),
            'reminders_due_today' => \App\Models\CorrespondenceReminder::query()->whereDate('reminder_date', '<=', today())->where('is_sent', false)->count(),
        ]);
    }

    public function dg(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'for_me' => Correspondence::query()->whereHas('assignments', fn ($q) => $q->where('to_user_id', $user->id))->count(),
            'urgent' => Correspondence::query()->whereIn('priority', ['urgente', 'tres_urgente'])->whereNotIn('status', ['archive', 'annule', 'classe'])->count(),
            'to_validate' => Correspondence::query()->whereIn('status', ['a_viser', 'a_valider', 'a_signer'])->count(),
            'overdue' => Correspondence::query()->where('due_date', '<', today())->whereNotIn('status', ['classe', 'archive', 'annule'])->count(),
            'without_reply' => Correspondence::query()->where('direction', 'entrant')->where('requires_reply', true)->whereDoesntHave('incomingLinks')->count(),
            'to_sign' => Correspondence::query()->where('status', 'a_signer')->count(),
            'recent_dispatched' => Correspondence::query()->where('status', 'expedie')->where('updated_at', '>=', now()->subDays(7))->count(),
            'confidential' => Correspondence::query()->where('confidentiality', 'confidentiel')->whereNotIn('status', ['archive', 'annule'])->count(),
        ]);
    }

    public function direction(Request $request): JsonResponse
    {
        $structureId = $request->user()->structure_id;

        $base = Correspondence::query()->when($structureId, fn ($q) => $q->where('structure_id', $structureId));

        return response()->json([
            'received' => (clone $base)->where('direction', 'entrant')->count(),
            'not_taken' => (clone $base)->whereHas('assignments', fn ($q) => $q->whereIn('status', ['transmis', 'recu']))->count(),
            'in_processing' => (clone $base)->whereIn('status', ['pris_en_charge', 'en_traitement'])->count(),
            'due_soon' => (clone $base)->whereBetween('due_date', [today(), today()->addDays(3)])->count(),
            'overdue' => (clone $base)->where('due_date', '<', today())->whereNotIn('status', ['classe', 'archive', 'annule'])->count(),
            'reply_projects' => (clone $base)->where('status', 'projet_reponse')->count(),
            'to_validate' => (clone $base)->whereIn('status', ['a_viser', 'a_valider'])->count(),
            'processed' => (clone $base)->whereIn('status', ['repondu', 'expedie', 'classe'])->count(),
            'internal' => (clone $base)->where('direction', 'interne')->count(),
            'outgoing' => (clone $base)->where('direction', 'sortant')->count(),
            'my_assignments' => (clone $base)->whereHas('assignments', fn ($q) => $q->where('to_user_id', $request->user()->id)->whereIn('status', ['transmis', 'recu', 'pris_en_charge']))->count(),
        ]);
    }
}
