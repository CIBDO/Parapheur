<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\CirculationSheet;
use App\Models\Correspondence;
use App\Models\CorrespondenceReminder;
use App\Models\TransmissionSlip;
use App\Services\MailVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailDashboardController extends Controller
{
    public function __construct(
        private readonly MailVisibilityScope $visibility,
    ) {}

    public function orderOffice(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = $this->visibility->apply(Correspondence::query(), $user);

        return response()->json([
            'received_today' => (clone $base)->where('direction', 'entrant')->whereDate('received_at', today())->count(),
            'to_qualify' => (clone $base)->whereIn('status', ['recu', 'a_qualifier'])->count(),
            'to_assign' => (clone $base)->whereIn('status', ['enregistre', 'a_affecter'])->count(),
            'unassigned' => (clone $base)->whereDoesntHave('assignments')->whereIn('status', ['enregistre', 'a_affecter', 'a_qualifier'])->count(),
            'in_processing' => (clone $base)->whereIn('status', ['affecte', 'pris_en_charge', 'en_traitement'])->count(),
            'to_dispatch' => (clone $base)->where('status', 'a_expedier')->count(),
            'dispatched_today' => (clone $base)->where('direction', 'sortant')->where('status', 'expedie')->whereDate('updated_at', today())->count(),
            'overdue' => (clone $base)->where('due_date', '<', today())->whereNotIn('status', ['classe', 'archive', 'annule', 'expedie', 'accuse_recu'])->count(),
            'my_pending' => (clone $base)->whereHas('assignments', function ($q) use ($user) {
                $q->where('to_user_id', $user->id)->whereIn('status', ['transmis', 'recu']);
            })->count(),
            'slips_in_progress' => $this->visibility->applyToSlips(TransmissionSlip::query(), $user)->whereIn('status', ['brouillon', 'valide', 'genere', 'imprime', 'transmis'])->count(),
            'circulation_sheets_open' => CirculationSheet::query()->where('status', 'en_cours')->whereHas('correspondence', function ($q) use ($user) {
                $this->visibility->apply($q, $user);
            })->count(),
            'reminders_due_today' => CorrespondenceReminder::query()->whereDate('reminder_date', '<=', today())->where('is_sent', false)->whereHas('correspondence', function ($q) use ($user) {
                $this->visibility->apply($q, $user);
            })->count(),
        ]);
    }

    public function dg(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = $this->visibility->apply(Correspondence::query(), $user);

        return response()->json([
            'for_me' => (clone $base)->whereHas('assignments', fn ($q) => $q->where('to_user_id', $user->id))->count(),
            'urgent' => (clone $base)->whereIn('priority', $this->urgentPriorityCodes())->whereNotIn('status', ['archive', 'annule', 'classe'])->count(),
            'to_validate' => (clone $base)->whereIn('status', ['a_viser', 'a_valider', 'a_signer'])->count(),
            'overdue' => (clone $base)->where('due_date', '<', today())->whereNotIn('status', ['classe', 'archive', 'annule'])->count(),
            'without_reply' => (clone $base)->where('direction', 'entrant')->where('requires_reply', true)->whereDoesntHave('incomingLinks')->count(),
            'to_sign' => (clone $base)->where('status', 'a_signer')->count(),
            'recent_dispatched' => (clone $base)->where('status', 'expedie')->where('updated_at', '>=', now()->subDays(7))->count(),
            'confidential' => (clone $base)->where('confidentiality', 'confidentiel')->whereNotIn('status', ['archive', 'annule'])->count(),
        ]);
    }

    public function direction(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = $this->visibility->apply(Correspondence::query(), $user);

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
            'my_assignments' => (clone $base)->whereHas('assignments', fn ($q) => $q->where('to_user_id', $user->id)->whereIn('status', ['transmis', 'recu', 'pris_en_charge']))->count(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function urgentPriorityCodes(): array
    {
        $codes = \App\Models\CorrespondencePriority::query()
            ->where('is_urgent', true)
            ->pluck('code')
            ->all();

        return $codes !== [] ? $codes : ['urgente', 'tres_urgente'];
    }
}
