<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\AppointmentNote;
use App\Models\AppointmentParticipant;
use App\Models\AppointmentType;
use App\Models\AuditLog;
use App\Models\CalendarUnavailability;
use App\Services\AppointmentAccessService;
use App\Services\AppointmentService;
use App\Services\CalendarAggregationService;
use App\Services\CalendarConflictService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
        private AppointmentAccessService $access,
        private CalendarAggregationService $calendar,
        private CalendarConflictService $conflicts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->access->visibleQuery($request->user())->with(['type', 'director', 'requesterUser']);

        foreach (['status', 'appointment_type_id', 'director_id', 'structure_id', 'meeting_mode', 'confidentiality', 'priority'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('subject', 'like', $q)
                    ->orWhere('reference', 'like', $q)
                    ->orWhere('requester_name', 'like', $q)
                    ->orWhere('requester_organization', 'like', $q);
            });
        }

        if ($request->boolean('mine')) {
            $query->where(function ($builder) use ($request) {
                $builder->where('requester_user_id', $request->user()->id)
                    ->orWhere('created_by', $request->user()->id);
            });
        }

        if ($request->boolean('today')) {
            $query->whereDate('start_at', now()->toDateString());
        }

        if ($request->boolean('to_validate')) {
            $query->where('status', AppointmentStatus::AValider->value);
        }

        $paginator = $query->orderByDesc('updated_at')->paginate((int) $request->input('per_page', 20));
        $paginator->getCollection()->transform(
            fn (Appointment $appointment) => $this->appointments->serialize($appointment, $request->user(), true)
        );

        return response()->json($paginator);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        try {
            $appointment = $this->appointments->create(
                $request->user(),
                $request->validated(),
                $request->boolean('as_request')
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'conflicts' => $e->errorBag['conflicts'] ?? null,
                'suggestions' => $e->errorBag['suggestions'] ?? null,
                'outside_working_hours' => $e->errorBag['outside_working_hours'] ?? null,
            ], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()), 201);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->access->authorizeView($request->user(), $appointment);
        if (in_array($appointment->confidentiality?->value, ['confidentiel', 'tres_confidentiel'], true)) {
            app(\App\Services\AuditLogger::class)->log('appointment.sensitive_viewed', $appointment);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->appointments->update($request->user(), $appointment, $request->validated());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'errors' => $e->errors(),
                'conflicts' => $e->errorBag['conflicts'] ?? null,
                'suggestions' => $e->errorBag['suggestions'] ?? null,
            ], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $appointment);
        abort_unless($appointment->status?->allowsPhysicalDelete() || $request->user()->can('appointments.delete_draft'), 403);
        $appointment->delete();

        return response()->json(['message' => 'Brouillon supprimé.']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->appointments->dashboard($request->user()));
    }

    public function calendar(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('appointments.view_calendar')
            || $request->user()->can('appointments.view')
            || $this->access->isManager($request->user())
            || $this->access->isDg($request->user()),
            403
        );

        $from = $request->date('from') ?: now()->startOfMonth();
        $to = $request->date('to') ?: now()->endOfMonth();

        return response()->json($this->calendar->events(
            $request->user(),
            Carbon::parse($from),
            Carbon::parse($to),
            $request->only([
                'director_id', 'status', 'appointment_type_id', 'meeting_mode',
                'confidentiality', 'structure_id', 'q', 'sources',
                'include_appointments', 'include_meetings', 'include_unavailabilities',
            ])
        ));
    }

    public function types(): JsonResponse
    {
        return response()->json(
            AppointmentType::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function checkConflicts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'director_id' => ['required', 'exists:users,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'ignore_appointment_id' => ['nullable', 'exists:appointments,id'],
        ]);

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        $conflicts = $this->conflicts->detectConflicts(
            (int) $data['director_id'],
            $start,
            $end,
            $data['ignore_appointment_id'] ?? null
        );

        return response()->json([
            'conflicts' => $conflicts,
            'suggestions' => $this->conflicts->suggestAlternatives(
                (int) $data['director_id'],
                $start,
                $end,
                $data['ignore_appointment_id'] ?? null
            ),
            'outside_working_hours' => $this->conflicts->isOutsideWorkingHours($start, $end),
        ]);
    }

    public function proposeSlot(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_mode' => ['nullable', 'string'],
            'submit_to_dg' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ]);

        try {
            $appointment = $this->appointments->proposeSlot($request->user(), $appointment, $data);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'conflicts' => $e->errorBag['conflicts'] ?? null,
                'suggestions' => $e->errorBag['suggestions'] ?? null,
                'outside_working_hours' => $e->errorBag['outside_working_hours'] ?? null,
            ], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function validateAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'decision_note' => ['nullable', 'string'],
            'confirm' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ]);

        try {
            $appointment = $this->appointments->validateAppointment($request->user(), $appointment, $data);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'conflicts' => $e->errorBag['conflicts'] ?? null,
                'suggestions' => $e->errorBag['suggestions'] ?? null,
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function reject(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
            'communicable' => ['nullable', 'boolean'],
            'decision_note' => ['nullable', 'string'],
        ]);

        try {
            $appointment = $this->appointments->reject($request->user(), $appointment, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->appointments->confirm($request->user(), $appointment);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function reschedule(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'duration_minutes' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'reconfirm' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ]);

        try {
            $appointment = $this->appointments->reschedule($request->user(), $appointment, $data);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'conflicts' => $e->errorBag['conflicts'] ?? null,
                'suggestions' => $e->errorBag['suggestions'] ?? null,
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3']]);

        try {
            $appointment = $this->appointments->cancel($request->user(), $appointment, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function hold(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate(['complement_request' => ['nullable', 'string']]);

        try {
            $appointment = $this->appointments->hold($request->user(), $appointment, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function redirect(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'redirected_to_user_id' => ['nullable', 'exists:users,id'],
            'redirected_to_structure_id' => ['nullable', 'exists:structures,id'],
            'reason' => ['nullable', 'string'],
        ]);

        $appointment = $this->appointments->redirect($request->user(), $appointment, $data);

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function start(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->appointments->start($request->user(), $appointment);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function finish(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'result_summary' => ['nullable', 'string'],
            'has_followup' => ['nullable', 'boolean'],
        ]);

        try {
            $appointment = $this->appointments->finish($request->user(), $appointment, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function close(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->appointments->close($request->user(), $appointment);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function archive(Request $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->appointments->archive($request->user(), $appointment);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->appointments->serialize($appointment, $request->user()));
    }

    public function storeParticipant(Request $request, Appointment $appointment): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $appointment);
        abort_unless($request->user()->can('appointments.manage_participants') || $this->access->isManager($request->user()), 403);

        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'participation_type' => ['nullable', 'in:interne,externe,accompagnant,assistant,representant'],
            'role' => ['nullable', 'string', 'max:50'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'organization' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'expected_presence' => ['nullable', 'boolean'],
        ]);

        $participant = $appointment->participants()->create($data);

        return response()->json($participant->load('user'), 201);
    }

    public function destroyParticipant(Request $request, Appointment $appointment, AppointmentParticipant $participant): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $appointment);
        abort_unless((int) $participant->appointment_id === (int) $appointment->id, 404);
        $participant->delete();

        return response()->json(['message' => 'Participant retiré.']);
    }

    public function storeDocument(Request $request, Appointment $appointment): JsonResponse
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => ['required', 'file', 'max:20480']]);
            $link = $this->appointments->uploadDocument($request->user(), $appointment, $request->file('file'), $request->all());
        } else {
            $data = $request->validate([
                'document_id' => ['required', 'exists:documents,id'],
                'kind' => ['nullable', 'string'],
                'label' => ['nullable', 'string'],
            ]);
            $link = $this->appointments->attachExistingDocument($request->user(), $appointment, (int) $data['document_id'], $data);
        }

        return response()->json($link, 201);
    }

    public function destroyDocument(Request $request, Appointment $appointment, AppointmentDocument $appointmentDocument): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $appointment);
        abort_unless((int) $appointmentDocument->appointment_id === (int) $appointment->id, 404);
        $appointmentDocument->delete();

        return response()->json(['message' => 'Document détaché.']);
    }

    public function storeNote(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'visibility' => ['nullable', 'in:privee,institutionnelle'],
        ]);

        $note = $this->appointments->addNote($request->user(), $appointment, $data);

        return response()->json($note, 201);
    }

    public function destroyNote(Request $request, Appointment $appointment, AppointmentNote $note): JsonResponse
    {
        abort_unless((int) $note->appointment_id === (int) $appointment->id, 404);
        abort_unless((int) $note->author_id === (int) $request->user()->id || $this->access->isManager($request->user()), 403);
        $note->delete();

        return response()->json(['message' => 'Note supprimée.']);
    }

    public function storeFollowup(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'appointment' => ['nullable', 'array'],
        ]);

        $followup = $this->appointments->addFollowup($request->user(), $appointment, $data);

        return response()->json($followup, 201);
    }

    public function convertToMeeting(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'object' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $meeting = $this->appointments->convertToMeeting($request->user(), $appointment, $data);

        return response()->json([
            'meeting' => ['id' => $meeting->id, 'reference' => $meeting->reference],
            'appointment' => $this->appointments->serialize($appointment->fresh(), $request->user()),
        ], 201);
    }

    public function preparation(Request $request, Appointment $appointment): JsonResponse
    {
        return response()->json($this->appointments->preparationSheet($request->user(), $appointment));
    }

    public function audit(Request $request, Appointment $appointment): JsonResponse
    {
        $this->access->authorizeView($request->user(), $appointment);

        return response()->json(
            AuditLog::query()
                ->with('user')
                ->where('auditable_type', Appointment::class)
                ->where('auditable_id', $appointment->id)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
        );
    }

    public function unavailabilities(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('appointments.manage_unavailability')
            || $request->user()->can('appointments.view_calendar')
            || $this->access->isManager($request->user()),
            403
        );

        $query = CalendarUnavailability::query()->with('user')->where('is_active', true);
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->where('start_at', '<=', $request->date('to'))
                ->where('end_at', '>=', $request->date('from'));
        }

        return response()->json($query->orderBy('start_at')->paginate(50));
    }

    public function storeUnavailability(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('appointments.manage_unavailability') || $this->access->isManager($request->user()), 403);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'kind' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'confidentiality' => ['nullable', 'string'],
            'blocks_calendar' => ['nullable', 'boolean'],
        ]);

        $slot = CalendarUnavailability::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($slot->load('user'), 201);
    }

    public function destroyUnavailability(Request $request, CalendarUnavailability $unavailability): JsonResponse
    {
        abort_unless($request->user()->can('appointments.manage_unavailability') || $this->access->isManager($request->user()), 403);
        $unavailability->delete();

        return response()->json(['message' => 'Indisponibilité supprimée.']);
    }
}
