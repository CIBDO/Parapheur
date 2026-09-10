<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\UpdateMeetingRequest;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\MeetingType;
use App\Services\MeetingAccessService;
use App\Services\MeetingDocumentRenderer;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MeetingController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly MeetingAccessService $access,
        private readonly MeetingDocumentRenderer $renderer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->access->visibleQuery($request->user())
            ->with(['chair', 'creator', 'type', 'structure', 'participants.user', 'decisions']);

        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($q) use ($needle) {
                $q->where('reference', 'like', $needle)
                    ->orWhere('title', 'like', $needle)
                    ->orWhere('object', 'like', $needle);
            });
        }
        foreach (['status', 'meeting_type_id', 'structure_id', 'chair_id', 'confidentiality'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('meeting_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('meeting_date', '<=', $request->date('to'));
        }
        if ($request->boolean('mine')) {
            $userId = $request->user()->id;
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('chair_id', $userId)
                    ->orWhere('secretary_id', $userId)
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId));
            });
        }

        $scope = $request->string('scope')->toString();
        if ($scope === 'today') {
            $query->whereDate('meeting_date', now()->toDateString());
        } elseif ($scope === 'week') {
            $query->whereBetween('meeting_date', [now()->toDateString(), now()->endOfWeek()->toDateString()]);
        } elseif ($scope === 'upcoming') {
            $query->whereDate('meeting_date', '>=', now()->toDateString());
        } elseif ($scope === 'preparation') {
            $query->whereIn('status', ['brouillon', 'en_preparation', 'convocation_a_valider']);
        } elseif ($scope === 'in_progress') {
            $query->whereIn('status', ['en_cours', 'suspendue']);
        } elseif ($scope === 'minutes') {
            $query->whereIn('status', ['terminee', 'cr_en_redaction', 'cr_en_validation']);
        }

        $paginator = $query->orderByDesc('meeting_date')->orderBy('meeting_time')->paginate($request->integer('per_page') ?: 20);

        $paginator->getCollection()->transform(fn (Meeting $meeting) => [
            'id' => $meeting->id,
            'reference' => $meeting->reference,
            'title' => $meeting->title,
            'object' => $meeting->displayTitle(),
            'meeting_date' => optional($meeting->meeting_date)->toDateString(),
            'meeting_time' => $meeting->meeting_time,
            'end_time' => $meeting->end_time,
            'location' => $meeting->location,
            'status' => $meeting->status?->value ?? $meeting->status,
            'status_label' => $meeting->status instanceof MeetingStatus ? $meeting->status->label() : $meeting->status,
            'confidentiality' => $meeting->confidentiality?->value ?? $meeting->confidentiality,
            'priority' => $meeting->priority?->value ?? $meeting->priority,
            'chair' => $meeting->chair,
            'creator' => $meeting->creator,
            'type' => $meeting->type,
            'structure' => $meeting->structure,
            'participants_count' => $meeting->participants->count(),
            'decisions_count' => $meeting->decisions->count(),
        ]);

        return response()->json($paginator);
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        abort_unless($this->access->canCreate($request->user()), 403);

        $meeting = $this->meetings->create($request->user(), $request->validated());

        return response()->json($this->meetings->serialize($meeting, $request->user()), 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);

        if ($participant = $meeting->participants()->where('user_id', $request->user()->id)->first()) {
            if (! $participant->read_at) {
                $participant->read_at = now();
                if ($participant->invitation_status === 'convoque') {
                    $participant->invitation_status = 'lu';
                }
                $participant->save();
            }
        }

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);
        abort_unless($this->access->canEditPreparation($request->user(), $meeting), 403);

        $meeting = $this->meetings->update($request->user(), $meeting, $request->validated());

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $meeting);
        $status = $meeting->status instanceof MeetingStatus ? $meeting->status : MeetingStatus::from((string) $meeting->status);
        abort_unless($status->allowsPhysicalDelete(), 422, 'Cette réunion ne peut plus être supprimée. Annulez-la ou archivez-la.');

        $meeting->delete();

        return response()->json(['deleted' => true]);
    }

    public function transition(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);
        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $to = MeetingStatus::from($data['status']);
        if ($to === MeetingStatus::EnCours) {
            abort_unless($this->access->canStart($request->user(), $meeting), 403);
        } else {
            $this->access->authorizeManage($request->user(), $meeting);
        }

        try {
            $meeting = $this->meetings->transition($request->user(), $meeting, $to, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }

    public function postpone(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'location' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $meeting = $this->meetings->postpone(
                $request->user(),
                $meeting,
                $data['meeting_date'],
                $data['meeting_time'] ?? null,
                $data['location'] ?? null,
                $data['reason'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }

    public function cancel(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            $meeting = $this->meetings->transition($request->user(), $meeting, MeetingStatus::Annulee, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->meetings->dashboard($request->user()));
    }

    public function calendar(Request $request): JsonResponse
    {
        $query = $this->access->visibleQuery($request->user())->with(['type', 'chair']);
        $from = $request->date('from') ?: now()->startOfMonth();
        $to = $request->date('to') ?: now()->endOfMonth();
        $query->whereBetween('meeting_date', [$from, $to]);

        foreach (['status', 'meeting_type_id', 'structure_id', 'chair_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return response()->json($query->orderBy('meeting_date')->get()->map(fn (Meeting $meeting) => [
            'id' => $meeting->id,
            'title' => $meeting->displayTitle(),
            'reference' => $meeting->reference,
            'meeting_date' => optional($meeting->meeting_date)->toDateString(),
            'meeting_time' => $meeting->meeting_time,
            'end_time' => $meeting->end_time,
            'status' => $meeting->status?->value ?? $meeting->status,
            'location' => $meeting->location,
            'type' => $meeting->type?->name,
            'chair' => $meeting->chair?->name,
        ]));
    }

    public function types(): JsonResponse
    {
        return response()->json(
            MeetingType::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function audit(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);

        $decisionIds = $meeting->decisions()->pluck('id');
        $logs = AuditLog::query()
            ->with('user')
            ->where(function ($q) use ($meeting, $decisionIds) {
                $q->where(function ($m) use ($meeting) {
                    $m->where('auditable_type', Meeting::class)->where('auditable_id', $meeting->id);
                })->orWhere(function ($d) use ($decisionIds) {
                    $d->where('auditable_type', \App\Models\MeetingDecision::class)
                        ->whereIn('auditable_id', $decisionIds);
                });
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json($logs);
    }

    public function export(Request $request, Meeting $meeting, string $kind)
    {
        $this->access->authorizeView($request->user(), $meeting);
        $html = match ($kind) {
            'convocation' => $this->renderer->convocation($meeting, $meeting->chair),
            'agenda' => $this->renderer->agenda($meeting),
            'attendance' => $this->renderer->attendanceList($meeting),
            'decisions' => $this->renderer->decisionsExtract($meeting),
            default => abort(404),
        };

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$kind.'-'.$meeting->reference.'.html"',
        ]);
    }

    public function exportDecisionsCsv(Request $request)
    {
        abort_unless($request->user()->can('meetings.view_reports') || $this->access->isManager($request->user()), 403);

        $rows = [];
        $query = \App\Models\MeetingDecision::query()->with(['meeting', 'assignee', 'structure']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        foreach ($query->orderByDesc('due_date')->get() as $decision) {
            if (! $this->access->canView($request->user(), $decision->meeting)) {
                continue;
            }
            $rows[] = [
                $decision->reference,
                $decision->meeting?->reference,
                $decision->title,
                $decision->assignee?->name,
                $decision->structure?->name,
                optional($decision->due_date)?->toDateString(),
                $decision->effectiveStatus()->value,
            ];
        }

        return $this->meetings->exportCsv(
            $rows,
            ['Référence', 'Réunion', 'Décision', 'Responsable', 'Structure', 'Échéance', 'Statut'],
            'decisions_reunions_'.now()->format('Ymd_His').'.csv'
        );
    }
}
