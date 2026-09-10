<?php

namespace App\Services;

use App\Enums\AppointmentFollowupKind;
use App\Enums\AppointmentMeetingMode;
use App\Enums\AppointmentOriginType;
use App\Enums\AppointmentStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\MeetingStatus;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\AppointmentFollowup;
use App\Models\AppointmentNote;
use App\Models\AppointmentParticipant;
use App\Models\AppointmentType;
use App\Models\CalendarUnavailability;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Instruction;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AppointmentService
{
    public function __construct(
        private AppointmentAccessService $access,
        private AppointmentStateMachine $stateMachine,
        private CalendarConflictService $conflicts,
        private AuditLogger $audit,
        private DocumentAccessService $documentAccess,
        private DocumentWorkflowService $documents,
        private MeetingService $meetings,
    ) {}

    public function create(User $actor, array $data, bool $asRequest = false): Appointment
    {
        abort_unless($this->access->canCreate($actor), 403);

        return DB::transaction(function () use ($actor, $data, $asRequest) {
            $type = isset($data['appointment_type_id'])
                ? AppointmentType::query()->find($data['appointment_type_id'])
                : AppointmentType::query()->where('code', 'RENDEZ_VOUS_INTERNE')->first();

            $duration = (int) ($data['duration_minutes'] ?? $type?->default_duration_minutes ?? 30);
            $startAt = ! empty($data['start_at']) ? Carbon::parse($data['start_at']) : null;
            $endAt = ! empty($data['end_at'])
                ? Carbon::parse($data['end_at'])
                : ($startAt ? $startAt->copy()->addMinutes($duration) : null);

            $status = $asRequest
                ? AppointmentStatus::DemandeRecue
                : AppointmentStatus::tryFrom($data['status'] ?? '') ?? AppointmentStatus::AExaminer;

            if (! empty($data['direct_schedule']) && $startAt && $endAt) {
                $status = AppointmentStatus::Confirme;
            }

            $appointment = Appointment::query()->create([
                'reference' => $this->nextReference(),
                'appointment_type_id' => $type?->id,
                'subject' => $data['subject'],
                'reason' => $data['reason'] ?? null,
                'description' => $data['description'] ?? null,
                'requested_date' => $data['requested_date'] ?? null,
                'requested_start_time' => $data['requested_start_time'] ?? null,
                'requested_end_time' => $data['requested_end_time'] ?? null,
                'proposed_availabilities' => $data['proposed_availabilities'] ?? null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'location' => $data['location'] ?? null,
                'meeting_mode' => $data['meeting_mode'] ?? AppointmentMeetingMode::Presentiel->value,
                'visio_url' => $data['visio_url'] ?? null,
                'external_address' => $data['external_address'] ?? null,
                'external_host_organization' => $data['external_host_organization'] ?? null,
                'external_contact' => $data['external_contact'] ?? null,
                'logistics_info' => $data['logistics_info'] ?? null,
                'origin_type' => $data['origin_type'] ?? ($asRequest ? AppointmentOriginType::Agent->value : AppointmentOriginType::Secretariat->value),
                'origin_reference' => $data['origin_reference'] ?? null,
                'intake_channel' => $data['intake_channel'] ?? ($asRequest ? 'en_ligne' : 'saisie_secretariat'),
                'requester_type' => $data['requester_type'] ?? null,
                'requester_user_id' => $data['requester_user_id'] ?? ($asRequest ? $actor->id : null),
                'requester_name' => $data['requester_name'] ?? ($asRequest ? $actor->name : null),
                'requester_organization' => $data['requester_organization'] ?? null,
                'requester_position' => $data['requester_position'] ?? ($asRequest ? $actor->position_title : null),
                'requester_email' => $data['requester_email'] ?? ($asRequest ? $actor->email : null),
                'requester_phone' => $data['requester_phone'] ?? ($asRequest ? $actor->phone : null),
                'director_id' => $data['director_id'] ?? User::role('Directeur Général')->value('id'),
                'secretariat_id' => $data['secretariat_id'] ?? $actor->id,
                'structure_id' => $data['structure_id'] ?? $actor->structure_id,
                'priority' => $data['priority'] ?? DocumentPriority::Normale->value,
                'confidentiality' => $data['confidentiality'] ?? DocumentConfidentiality::Normal->value,
                'status' => $status,
                'blocks_calendar' => $data['blocks_calendar'] ?? ($type?->blocks_calendar ?? true),
                'context_note' => $data['context_note'] ?? null,
                'points_to_discuss' => $data['points_to_discuss'] ?? null,
                'linked_document_id' => $data['linked_document_id'] ?? null,
                'parent_appointment_id' => $data['parent_appointment_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($startAt && $endAt && $appointment->blocks_calendar && $status->blocksCalendarByDefault()) {
                $this->assertNoConflict($appointment, $startAt, $endAt, (bool) ($data['force'] ?? false));
            }

            $this->syncParticipants($appointment, $data['participants'] ?? [], $data['participant_ids'] ?? []);
            if (! empty($data['document_ids'])) {
                foreach ($data['document_ids'] as $documentId) {
                    $this->attachExistingDocument($actor, $appointment, (int) $documentId);
                }
            }

            $this->audit->log('appointment.created', $appointment, ['status' => $status->value]);
            $this->notifyStakeholders($appointment, 'created', 'Une demande de rendez-vous a été enregistrée : '.$appointment->displaySubject(), $actor);

            return $appointment->fresh($this->defaultRelations());
        });
    }

    public function update(User $actor, Appointment $appointment, array $data): Appointment
    {
        $this->access->authorizeManage($actor, $appointment);

        return DB::transaction(function () use ($actor, $appointment, $data) {
            $fillable = collect($data)->only([
                'appointment_type_id', 'subject', 'reason', 'description',
                'requested_date', 'requested_start_time', 'requested_end_time', 'proposed_availabilities',
                'location', 'meeting_mode', 'visio_url', 'external_address', 'external_host_organization',
                'external_contact', 'logistics_info', 'origin_type', 'origin_reference', 'intake_channel',
                'requester_type', 'requester_user_id', 'requester_name', 'requester_organization',
                'requester_position', 'requester_email', 'requester_phone',
                'director_id', 'secretariat_id', 'structure_id',
                'priority', 'confidentiality', 'blocks_calendar',
                'context_note', 'points_to_discuss', 'decision_note', 'internal_note', 'result_summary',
                'linked_document_id',
            ])->all();

            if (isset($data['start_at']) || isset($data['end_at']) || isset($data['duration_minutes'])) {
                $startAt = Carbon::parse($data['start_at'] ?? $appointment->start_at);
                $endAt = isset($data['end_at'])
                    ? Carbon::parse($data['end_at'])
                    : $startAt->copy()->addMinutes((int) ($data['duration_minutes'] ?? $appointment->durationMinutes() ?? 30));
                $fillable['start_at'] = $startAt;
                $fillable['end_at'] = $endAt;

                if ($appointment->blocks_calendar || ($data['blocks_calendar'] ?? true)) {
                    $this->assertNoConflict($appointment, $startAt, $endAt, (bool) ($data['force'] ?? false), $appointment->id);
                }
            }

            $fillable['updated_by'] = $actor->id;
            $appointment->update($fillable);

            if (array_key_exists('participants', $data) || array_key_exists('participant_ids', $data)) {
                $appointment->participants()->delete();
                $this->syncParticipants($appointment, $data['participants'] ?? [], $data['participant_ids'] ?? []);
            }

            $this->audit->log('appointment.updated', $appointment);
            $this->notifyStakeholders($appointment, 'updated', 'Le rendez-vous « '.$appointment->displaySubject().' » a été modifié.', $actor);

            return $appointment->fresh($this->defaultRelations());
        });
    }

    public function proposeSlot(User $actor, Appointment $appointment, array $data): Appointment
    {
        abort_unless($this->access->canManageRequests($actor) || $this->access->canManage($actor, $appointment), 403);

        $startAt = Carbon::parse($data['start_at']);
        $endAt = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : $startAt->copy()->addMinutes((int) ($data['duration_minutes'] ?? $appointment->durationMinutes() ?? 30));

        $conflictPayload = $this->conflictPayload($appointment, $startAt, $endAt);
        if ($conflictPayload['conflicts'] && empty($data['force'])) {
            throw ValidationException::withMessages([
                'start_at' => ['Conflit d’agenda détecté.'],
            ])->errorBag([
                'conflicts' => $conflictPayload['conflicts'],
                'suggestions' => $conflictPayload['suggestions'],
                'outside_working_hours' => $conflictPayload['outside_working_hours'],
            ]);
        }

        $appointment->update([
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => $data['location'] ?? $appointment->location,
            'meeting_mode' => $data['meeting_mode'] ?? $appointment->meeting_mode,
            'updated_by' => $actor->id,
        ]);

        $target = ! empty($data['submit_to_dg'])
            ? AppointmentStatus::AValider
            : AppointmentStatus::CreneauPropose;

        $appointment = $this->transition($actor, $appointment, $target);
        $this->audit->log('appointment.slot_proposed', $appointment, [
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $endAt->toIso8601String(),
        ]);
        $this->notifyStakeholders($appointment, 'slot_proposed', 'Un créneau a été proposé pour « '.$appointment->displaySubject().' ».', $actor);

        return $appointment->fresh($this->defaultRelations());
    }

    public function validateAppointment(User $actor, Appointment $appointment, array $data = []): Appointment
    {
        $this->access->authorizeValidate($actor, $appointment);

        if ($appointment->start_at && $appointment->end_at) {
            $this->assertNoConflict(
                $appointment,
                $appointment->start_at,
                $appointment->end_at,
                (bool) ($data['force'] ?? false),
                $appointment->id
            );
        }

        $appointment->update([
            'validated_by' => $actor->id,
            'validated_at' => now(),
            'decision_note' => $data['decision_note'] ?? $appointment->decision_note,
            'updated_by' => $actor->id,
        ]);

        $appointment = $this->transition($actor, $appointment, AppointmentStatus::Valide);
        if (($data['confirm'] ?? true) !== false) {
            $appointment = $this->confirm($actor, $appointment);
        }

        $this->audit->log('appointment.validated', $appointment);

        return $appointment->fresh($this->defaultRelations());
    }

    public function confirm(User $actor, Appointment $appointment): Appointment
    {
        abort_unless(
            $this->access->canManageRequests($actor)
            || $this->access->canValidate($actor, $appointment)
            || $actor->can('appointments.confirm'),
            403
        );

        $appointment->update([
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'updated_by' => $actor->id,
        ]);

        if ($appointment->status !== AppointmentStatus::Confirme) {
            $appointment = $this->transition($actor, $appointment, AppointmentStatus::Confirme);
        }

        $this->audit->log('appointment.confirmed', $appointment);
        $this->notifyStakeholders(
            $appointment,
            'confirmed',
            sprintf(
                'Votre rendez-vous avec la Direction Générale est confirmé. Date : %s. Lieu : %s. Objet : %s.',
                optional($appointment->start_at)?->format('d/m/Y H:i') ?? '—',
                $appointment->location ?: '—',
                $appointment->displaySubject()
            ),
            $actor
        );

        return $appointment->fresh($this->defaultRelations());
    }

    public function reject(User $actor, Appointment $appointment, array $data): Appointment
    {
        abort_unless($this->access->canManageRequests($actor) || $this->access->canValidate($actor, $appointment), 403);

        $appointment->update([
            'rejection_reason' => $data['reason'],
            'rejection_communicable' => (bool) ($data['communicable'] ?? false),
            'decision_note' => $data['decision_note'] ?? $appointment->decision_note,
            'updated_by' => $actor->id,
        ]);

        $appointment = $this->transition($actor, $appointment, AppointmentStatus::Refuse);
        $this->audit->log('appointment.rejected', $appointment, ['reason' => $data['reason']]);
        $this->notifyStakeholders($appointment, 'rejected', 'La demande de rendez-vous a été refusée.', $actor);

        return $appointment->fresh($this->defaultRelations());
    }

    public function reschedule(User $actor, Appointment $appointment, array $data): Appointment
    {
        abort_unless(
            $this->access->canManageRequests($actor)
            || $this->access->canValidate($actor, $appointment)
            || $actor->can('appointments.reschedule'),
            403
        );

        $startAt = Carbon::parse($data['start_at']);
        $endAt = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : $startAt->copy()->addMinutes((int) ($data['duration_minutes'] ?? $appointment->durationMinutes() ?? 30));

        $this->assertNoConflict($appointment, $startAt, $endAt, (bool) ($data['force'] ?? false), $appointment->id);

        $appointment->update([
            'previous_start_at' => $appointment->start_at,
            'previous_end_at' => $appointment->end_at,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'reschedule_reason' => $data['reason'] ?? null,
            'location' => $data['location'] ?? $appointment->location,
            'updated_by' => $actor->id,
        ]);

        $appointment = $this->transition($actor, $appointment, AppointmentStatus::Reporte);
        if (! empty($data['reconfirm'])) {
            $appointment = $this->transition($actor, $appointment, AppointmentStatus::Confirme);
        }

        $this->audit->log('appointment.rescheduled', $appointment);
        $this->notifyStakeholders($appointment, 'rescheduled', 'Le rendez-vous a été reporté.', $actor);

        return $appointment->fresh($this->defaultRelations());
    }

    public function cancel(User $actor, Appointment $appointment, array $data): Appointment
    {
        abort_unless(
            $this->access->canManage($actor, $appointment)
            || $actor->can('appointments.cancel')
            || $this->access->canValidate($actor, $appointment),
            403
        );

        $appointment->update([
            'cancellation_reason' => $data['reason'],
            'updated_by' => $actor->id,
        ]);

        $appointment = $this->transition($actor, $appointment, AppointmentStatus::Annule);
        $this->audit->log('appointment.cancelled', $appointment, ['reason' => $data['reason']]);
        $this->notifyStakeholders($appointment, 'cancelled', 'Le rendez-vous a été annulé.', $actor);

        return $appointment->fresh($this->defaultRelations());
    }

    public function hold(User $actor, Appointment $appointment, array $data = []): Appointment
    {
        abort_unless($this->access->canManageRequests($actor), 403);
        $appointment->update([
            'complement_request' => $data['complement_request'] ?? $appointment->complement_request,
            'updated_by' => $actor->id,
        ]);
        $appointment = $this->transition($actor, $appointment, AppointmentStatus::EnAttente);
        $this->audit->log('appointment.held', $appointment);

        return $appointment->fresh($this->defaultRelations());
    }

    public function redirect(User $actor, Appointment $appointment, array $data): Appointment
    {
        abort_unless($this->access->canManageRequests($actor) || $this->access->canValidate($actor, $appointment), 403);

        $appointment->update([
            'redirected_to_user_id' => $data['redirected_to_user_id'] ?? null,
            'redirected_to_structure_id' => $data['redirected_to_structure_id'] ?? null,
            'decision_note' => $data['reason'] ?? $appointment->decision_note,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log('appointment.redirected', $appointment, $data);
        $this->notifyStakeholders($appointment, 'redirected', 'La demande de rendez-vous a été réorientée.', $actor);

        return $appointment->fresh($this->defaultRelations());
    }

    public function start(User $actor, Appointment $appointment): Appointment
    {
        $this->access->authorizeManage($actor, $appointment);
        $appointment->update(['started_at' => now(), 'updated_by' => $actor->id]);
        $appointment = $this->transition($actor, $appointment, AppointmentStatus::EnCours);
        $this->audit->log('appointment.started', $appointment);

        return $appointment->fresh($this->defaultRelations());
    }

    public function finish(User $actor, Appointment $appointment, array $data = []): Appointment
    {
        $this->access->authorizeManage($actor, $appointment);
        $appointment->update([
            'finished_at' => now(),
            'result_summary' => $data['result_summary'] ?? $appointment->result_summary,
            'updated_by' => $actor->id,
        ]);
        $target = ! empty($data['has_followup']) ? AppointmentStatus::SuiteADonner : AppointmentStatus::Termine;
        $appointment = $this->transition($actor, $appointment, $target);
        $this->audit->log('appointment.finished', $appointment);

        return $appointment->fresh($this->defaultRelations());
    }

    public function close(User $actor, Appointment $appointment): Appointment
    {
        $this->access->authorizeManage($actor, $appointment);
        $appointment->update(['closed_at' => now(), 'updated_by' => $actor->id]);
        $appointment = $this->transition($actor, $appointment, AppointmentStatus::Cloture);
        $this->audit->log('appointment.closed', $appointment);

        return $appointment->fresh($this->defaultRelations());
    }

    public function archive(User $actor, Appointment $appointment): Appointment
    {
        abort_unless($actor->can('appointments.archive') || $this->access->isManager($actor), 403);
        $appointment->update(['archived_at' => now(), 'updated_by' => $actor->id]);
        $appointment = $this->transition($actor, $appointment, AppointmentStatus::Archive);
        $this->audit->log('appointment.archived', $appointment);

        return $appointment->fresh($this->defaultRelations());
    }

    public function transition(User $actor, Appointment $appointment, AppointmentStatus $to, array $data = []): Appointment
    {
        $from = $appointment->status instanceof AppointmentStatus
            ? $appointment->status
            : AppointmentStatus::from((string) $appointment->status);

        $this->stateMachine->assertCanTransition($from, $to);
        $appointment->update(array_merge($data, [
            'status' => $to,
            'updated_by' => $actor->id,
        ]));

        $this->audit->log('appointment.transition', $appointment, [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        return $appointment->fresh($this->defaultRelations());
    }

    public function attachExistingDocument(User $actor, Appointment $appointment, int $documentId, array $meta = []): AppointmentDocument
    {
        abort_unless($this->access->canManage($actor, $appointment) || $actor->can('appointments.manage_documents'), 403);
        $document = Document::query()->findOrFail($documentId);
        abort_unless($this->documentAccess->canAccess($actor, $document), 403, 'Document inaccessible.');

        $link = AppointmentDocument::query()->updateOrCreate(
            ['appointment_id' => $appointment->id, 'document_id' => $document->id],
            [
                'kind' => $meta['kind'] ?? 'piece_jointe',
                'label' => $meta['label'] ?? null,
                'sort_order' => $meta['sort_order'] ?? ((int) $appointment->documentLinks()->max('sort_order') + 1),
                'attached_by' => $actor->id,
            ]
        );

        $this->audit->log('appointment.document_attached', $appointment, ['document_id' => $document->id]);
        $this->notifyStakeholders($appointment, 'document_added', 'Un document a été ajouté au dossier du rendez-vous.', $actor);

        return $link->load('document.type');
    }

    public function uploadDocument(User $actor, Appointment $appointment, UploadedFile $file, array $meta = []): AppointmentDocument
    {
        $type = DocumentType::query()->where('code', $meta['document_type_code'] ?? 'NOTE')->first()
            ?? DocumentType::query()->firstOrFail();

        $document = $this->documents->createDraft($actor, [
            'object' => $meta['object'] ?? ($appointment->displaySubject().' — '.$file->getClientOriginalName()),
            'document_type_id' => $type->id,
            'structure_id' => $appointment->structure_id ?? $actor->structure_id,
            'confidentiality' => $appointment->confidentiality?->value ?? 'normal',
            'priority' => $appointment->priority?->value ?? 'normale',
            'keywords' => ['rdv', $appointment->reference],
        ], $file);

        return $this->attachExistingDocument($actor, $appointment, $document->id, $meta);
    }

    public function addNote(User $actor, Appointment $appointment, array $data): AppointmentNote
    {
        abort_unless($this->access->canManageNotes($actor, $appointment), 403);

        $note = $appointment->notes()->create([
            'author_id' => $actor->id,
            'visibility' => $data['visibility'] ?? 'institutionnelle',
            'body' => $data['body'],
        ]);

        $this->audit->log('appointment.note_added', $appointment, ['note_id' => $note->id]);

        return $note->load('author');
    }

    public function addFollowup(User $actor, Appointment $appointment, array $data): AppointmentFollowup
    {
        abort_unless($this->access->canManage($actor, $appointment) || $actor->can('appointments.manage_followups'), 403);

        return DB::transaction(function () use ($actor, $appointment, $data) {
            $kind = AppointmentFollowupKind::from($data['kind']);
            $followup = $appointment->followups()->create([
                'kind' => $kind,
                'title' => $data['title'] ?? $kind->label(),
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? 'normale',
                'due_date' => $data['due_date'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'structure_id' => $data['structure_id'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($kind === AppointmentFollowupKind::Instruction) {
                $instruction = Instruction::query()->create([
                    'document_id' => $appointment->linked_document_id,
                    'issuer_id' => $actor->id,
                    'assignee_id' => $data['assignee_id'] ?? null,
                    'structure_id' => $data['structure_id'] ?? $appointment->structure_id,
                    'title' => $data['title'] ?? ('Suite RDV '.$appointment->reference),
                    'body' => $data['description'] ?? $appointment->result_summary ?? $appointment->subject,
                    'priority' => $data['priority'] ?? $appointment->priority?->value ?? 'normale',
                    'status' => 'a_faire',
                    'due_date' => $data['due_date'] ?? null,
                ]);
                $followup->update(['instruction_id' => $instruction->id]);
                $this->audit->log('appointment.instruction_created', $appointment, ['instruction_id' => $instruction->id]);
            }

            if ($kind === AppointmentFollowupKind::NouveauRdv) {
                $child = $this->create($actor, array_merge($data['appointment'] ?? [], [
                    'subject' => $data['title'] ?? ('Suivi — '.$appointment->subject),
                    'parent_appointment_id' => $appointment->id,
                    'requester_user_id' => $appointment->requester_user_id,
                    'requester_name' => $appointment->requester_name,
                    'requester_organization' => $appointment->requester_organization,
                    'director_id' => $appointment->director_id,
                    'confidentiality' => $appointment->confidentiality?->value,
                    'priority' => $appointment->priority?->value,
                ]));
                $followup->update(['followup_appointment_id' => $child->id]);
            }

            if ($appointment->status === AppointmentStatus::Termine) {
                $this->transition($actor, $appointment, AppointmentStatus::SuiteADonner);
            }

            $this->audit->log('appointment.followup_created', $appointment, ['followup_id' => $followup->id]);
            $this->notifyStakeholders($appointment, 'followup_created', 'Une suite à donner a été enregistrée.', $actor);

            return $followup->fresh(['instruction', 'assignee', 'followupAppointment', 'followupMeeting']);
        });
    }

    public function convertToMeeting(User $actor, Appointment $appointment, array $data = []): Meeting
    {
        abort_unless($this->access->canManage($actor, $appointment) || $actor->can('meetings.create'), 403);

        $participants = $appointment->participants->map(function (AppointmentParticipant $p) {
            if ($p->user_id) {
                return ['participation_type' => 'interne', 'user_id' => $p->user_id, 'role' => $p->role];
            }

            return [
                'participation_type' => 'externe',
                'external_name' => $p->displayName(),
                'external_function' => $p->position,
                'external_structure' => $p->organization,
                'email' => $p->email,
                'phone' => $p->phone,
                'role' => $p->role,
            ];
        })->all();

        $meeting = $this->meetings->create($actor, [
            'object' => $data['object'] ?? $appointment->subject,
            'description' => $data['description'] ?? ($appointment->context_note ?: $appointment->description),
            'meeting_date' => optional($appointment->start_at)?->toDateString() ?? now()->addDays(7)->toDateString(),
            'meeting_time' => optional($appointment->start_at)?->format('H:i') ?? '10:00',
            'end_time' => optional($appointment->end_at)?->format('H:i'),
            'location' => $appointment->location,
            'visio_url' => $appointment->visio_url,
            'chair_id' => $appointment->director_id,
            'secretary_id' => $appointment->secretariat_id,
            'structure_id' => $appointment->structure_id,
            'confidentiality' => $appointment->confidentiality?->value,
            'priority' => $appointment->priority?->value,
            'participants' => $participants,
            'document_ids' => $appointment->documentLinks()->pluck('document_id')->all(),
            'observations' => 'Issu du rendez-vous '.$appointment->reference,
        ]);

        $appointment->update([
            'converted_meeting_id' => $meeting->id,
            'updated_by' => $actor->id,
        ]);

        $appointment->followups()->create([
            'kind' => AppointmentFollowupKind::Reunion,
            'title' => 'Transformation en réunion',
            'description' => 'Réunion créée : '.$meeting->reference,
            'followup_meeting_id' => $meeting->id,
            'created_by' => $actor->id,
        ]);

        $this->audit->log('appointment.converted_to_meeting', $appointment, ['meeting_id' => $meeting->id]);

        return $meeting;
    }

    public function preparationSheet(User $actor, Appointment $appointment): array
    {
        $this->access->authorizeView($actor, $appointment);
        if (! $this->access->canSeeDetails($actor, $appointment)) {
            $this->audit->log('appointment.sensitive_view_denied', $appointment);

            return ['masked' => true, 'label' => 'Créneau indisponible'];
        }

        $this->audit->log('appointment.preparation_viewed', $appointment);

        $history = Appointment::query()
            ->where('id', '!=', $appointment->id)
            ->where(function ($q) use ($appointment) {
                if ($appointment->requester_organization) {
                    $q->orWhere('requester_organization', $appointment->requester_organization);
                }
                if ($appointment->requester_user_id) {
                    $q->orWhere('requester_user_id', $appointment->requester_user_id);
                }
                if ($appointment->requester_email) {
                    $q->orWhere('requester_email', $appointment->requester_email);
                }
            })
            ->orderByDesc('start_at')
            ->limit(10)
            ->get(['id', 'reference', 'subject', 'start_at', 'status']);

        $openInstructions = Instruction::query()
            ->where('status', '!=', 'cloturee')
            ->where(function ($q) use ($appointment) {
                $q->where('issuer_id', $appointment->director_id)
                    ->orWhere('assignee_id', $appointment->director_id);
            })
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'status', 'due_date', 'priority']);

        return [
            'appointment' => $this->serialize($appointment, $actor),
            'context' => $appointment->context_note,
            'points_to_discuss' => $appointment->points_to_discuss,
            'documents' => $appointment->documentLinks()->with('document.type')->get(),
            'previous_appointments' => $history,
            'open_instructions' => $openInstructions,
        ];
    }

    public function dashboard(User $user): array
    {
        $base = $this->access->visibleQuery($user);
        $today = now()->toDateString();

        $todayCount = (clone $base)->whereDate('start_at', $today)
            ->whereNotIn('status', [AppointmentStatus::Annule->value, AppointmentStatus::Refuse->value, AppointmentStatus::Archive->value])
            ->count();

        $next = (clone $base)->where('start_at', '>=', now())
            ->whereIn('status', [
                AppointmentStatus::Confirme->value,
                AppointmentStatus::Valide->value,
                AppointmentStatus::Pret->value,
            ])
            ->orderBy('start_at')
            ->first();

        $toValidate = (clone $base)->where('status', AppointmentStatus::AValider->value)->count();
        $toExamine = (clone $base)->whereIn('status', [
            AppointmentStatus::DemandeRecue->value,
            AppointmentStatus::AExaminer->value,
        ])->count();
        $pending = (clone $base)->where('status', AppointmentStatus::EnAttente->value)->count();
        $toConfirm = (clone $base)->where('status', AppointmentStatus::Valide->value)->count();
        $rescheduled = (clone $base)->where('status', AppointmentStatus::Reporte->value)->count();
        $audiences = (clone $base)->whereDate('start_at', $today)
            ->whereHas('type', fn ($q) => $q->where('code', 'AUDIENCE'))
            ->count();

        $meetingsToday = Meeting::query()
            ->whereDate('meeting_date', $today)
            ->where('chair_id', $user->id)
            ->orWhere(function ($q) use ($user, $today) {
                $q->whereDate('meeting_date', $today)
                    ->where(function ($inner) use ($user) {
                        $inner->where('secretary_id', $user->id)->orWhere('organizer_id', $user->id);
                    });
            })
            ->count();

        $todayList = (clone $base)->with(['type', 'requesterUser'])
            ->whereDate('start_at', $today)
            ->whereNotIn('status', [AppointmentStatus::Annule->value, AppointmentStatus::Refuse->value])
            ->orderBy('start_at')
            ->get()
            ->map(fn (Appointment $a) => $this->serialize($a, $user, true));

        return [
            'today' => [
                'appointments' => $todayCount,
                'audiences' => $audiences,
                'meetings' => $meetingsToday,
            ],
            'requests' => [
                'to_examine' => $toExamine,
                'to_validate' => $toValidate,
                'pending' => $pending,
            ],
            'followup' => [
                'to_confirm' => $toConfirm,
                'rescheduled' => $rescheduled,
            ],
            'dg' => [
                'today' => $todayCount,
                'next_appointment_at' => optional($next?->start_at)?->format('H:i'),
                'to_validate' => $toValidate,
            ],
            'today_list' => $todayList,
            'my_requests' => [
                'total' => Appointment::query()->where('requester_user_id', $user->id)->count(),
                'confirmed' => Appointment::query()->where('requester_user_id', $user->id)->where('status', AppointmentStatus::Confirme->value)->count(),
                'pending' => Appointment::query()->where('requester_user_id', $user->id)->whereIn('status', [
                    AppointmentStatus::DemandeRecue->value,
                    AppointmentStatus::AExaminer->value,
                    AppointmentStatus::EnAttente->value,
                    AppointmentStatus::AValider->value,
                ])->count(),
                'rejected' => Appointment::query()->where('requester_user_id', $user->id)->where('status', AppointmentStatus::Refuse->value)->count(),
            ],
        ];
    }

    public function serialize(Appointment $appointment, ?User $viewer = null, bool $summary = false): array
    {
        $appointment->loadMissing($this->defaultRelations());

        $masked = $viewer && ! $this->access->canSeeDetails($viewer, $appointment);
        if ($masked) {
            if ($viewer) {
                $this->audit->log('appointment.sensitive_masked', $appointment);
            }

            return [
                'id' => $appointment->id,
                'reference' => $appointment->reference,
                'subject' => 'Créneau indisponible',
                'status' => $appointment->status?->value ?? $appointment->status,
                'status_label' => $appointment->status?->label(),
                'start_at' => optional($appointment->start_at)?->toIso8601String(),
                'end_at' => optional($appointment->end_at)?->toIso8601String(),
                'masked' => true,
                'blocks_calendar' => true,
                'allowed_transitions' => [],
            ];
        }

        $payload = [
            'id' => $appointment->id,
            'reference' => $appointment->reference,
            'subject' => $appointment->subject,
            'reason' => $appointment->reason,
            'description' => $appointment->description,
            'type' => $appointment->type,
            'requested_date' => optional($appointment->requested_date)?->toDateString(),
            'requested_start_time' => $appointment->requested_start_time,
            'requested_end_time' => $appointment->requested_end_time,
            'proposed_availabilities' => $appointment->proposed_availabilities,
            'start_at' => optional($appointment->start_at)?->toIso8601String(),
            'end_at' => optional($appointment->end_at)?->toIso8601String(),
            'previous_start_at' => optional($appointment->previous_start_at)?->toIso8601String(),
            'previous_end_at' => optional($appointment->previous_end_at)?->toIso8601String(),
            'duration_minutes' => $appointment->durationMinutes(),
            'location' => $appointment->location,
            'meeting_mode' => $appointment->meeting_mode?->value ?? $appointment->meeting_mode,
            'meeting_mode_label' => $appointment->meeting_mode?->label(),
            'visio_url' => $appointment->visio_url,
            'external_address' => $appointment->external_address,
            'external_host_organization' => $appointment->external_host_organization,
            'external_contact' => $appointment->external_contact,
            'logistics_info' => $appointment->logistics_info,
            'origin_type' => $appointment->origin_type?->value ?? $appointment->origin_type,
            'origin_type_label' => $appointment->origin_type?->label(),
            'intake_channel' => $appointment->intake_channel,
            'requester_name' => $appointment->requesterDisplayName(),
            'requester_organization' => $appointment->requester_organization,
            'requester_position' => $appointment->requester_position,
            'requester_email' => $appointment->requester_email,
            'requester_phone' => $appointment->requester_phone,
            'requester_user' => $appointment->requesterUser,
            'director' => $appointment->director,
            'secretariat' => $appointment->secretariat,
            'structure' => $appointment->structure,
            'priority' => $appointment->priority?->value ?? $appointment->priority,
            'confidentiality' => $appointment->confidentiality?->value ?? $appointment->confidentiality,
            'status' => $appointment->status?->value ?? $appointment->status,
            'status_label' => $appointment->status?->label(),
            'blocks_calendar' => $appointment->blocks_calendar,
            'context_note' => $appointment->context_note,
            'points_to_discuss' => $appointment->points_to_discuss,
            'decision_note' => $appointment->decision_note,
            'result_summary' => $appointment->result_summary,
            'rejection_reason' => $appointment->rejection_communicable || ($viewer && $this->access->isManager($viewer))
                ? $appointment->rejection_reason
                : null,
            'cancellation_reason' => $appointment->cancellation_reason,
            'reschedule_reason' => $appointment->reschedule_reason,
            'complement_request' => $appointment->complement_request,
            'validated_at' => optional($appointment->validated_at)?->toIso8601String(),
            'confirmed_at' => optional($appointment->confirmed_at)?->toIso8601String(),
            'parent_appointment_id' => $appointment->parent_appointment_id,
            'converted_meeting_id' => $appointment->converted_meeting_id,
            'linked_document_id' => $appointment->linked_document_id,
            'masked' => false,
            'allowed_transitions' => $this->stateMachine->allowedFrom(
                $appointment->status instanceof AppointmentStatus
                    ? $appointment->status
                    : AppointmentStatus::from((string) $appointment->status)
            ),
            'outside_working_hours' => $appointment->start_at && $appointment->end_at
                ? $this->conflicts->isOutsideWorkingHours($appointment->start_at, $appointment->end_at)
                : false,
        ];

        if (! $summary) {
            $payload['participants'] = $appointment->participants->map(fn (AppointmentParticipant $p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'user' => $p->user,
                'participation_type' => $p->participation_type,
                'role' => $p->role,
                'display_name' => $p->displayName(),
                'organization' => $p->organization,
                'position' => $p->position,
                'email' => $p->email,
                'phone' => $p->phone,
                'expected_presence' => $p->expected_presence,
                'actual_presence' => $p->actual_presence,
            ]);
            $payload['documents'] = $appointment->documentLinks->map(fn (AppointmentDocument $d) => [
                'id' => $d->id,
                'kind' => $d->kind,
                'label' => $d->label,
                'document' => $d->document,
            ]);
            $payload['notes'] = $appointment->notes
                ->filter(fn (AppointmentNote $n) => ! $viewer || $this->access->canViewNote($viewer, $n))
                ->values()
                ->map(fn (AppointmentNote $n) => [
                    'id' => $n->id,
                    'visibility' => $n->visibility,
                    'body' => $n->body,
                    'author' => $n->author,
                    'created_at' => optional($n->created_at)?->toIso8601String(),
                ]);
            $payload['followups'] = $appointment->followups;
            $payload['followups_appointments'] = $appointment->followupsAppointments;
        }

        return $payload;
    }

    private function assertNoConflict(
        Appointment $appointment,
        Carbon $startAt,
        Carbon $endAt,
        bool $force = false,
        ?int $ignoreId = null,
    ): void {
        $payload = $this->conflictPayload($appointment, $startAt, $endAt, $ignoreId);
        if ($payload['conflicts'] && ! $force) {
            throw ValidationException::withMessages([
                'start_at' => [$payload['conflicts'][0]['message'] ?? 'Conflit d’agenda détecté.'],
            ])->errorBag([
                'conflicts' => $payload['conflicts'],
                'suggestions' => $payload['suggestions'],
                'outside_working_hours' => $payload['outside_working_hours'],
            ]);
        }
    }

    private function conflictPayload(Appointment $appointment, Carbon $startAt, Carbon $endAt, ?int $ignoreId = null): array
    {
        $directorId = (int) ($appointment->director_id ?: 0);
        $conflicts = $directorId
            ? $this->conflicts->detectConflicts($directorId, $startAt, $endAt, $ignoreId ?? $appointment->id)
            : [];

        return [
            'conflicts' => $conflicts,
            'suggestions' => $directorId
                ? $this->conflicts->suggestAlternatives($directorId, $startAt, $endAt, $ignoreId ?? $appointment->id)
                : ['before' => null, 'after' => null],
            'outside_working_hours' => $this->conflicts->isOutsideWorkingHours($startAt, $endAt),
        ];
    }

    private function syncParticipants(Appointment $appointment, array $participants, array $participantIds): void
    {
        foreach ($participantIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }
            $appointment->participants()->create([
                'user_id' => $user->id,
                'participation_type' => 'interne',
                'role' => 'participant',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'position' => $user->position_title,
                'organization' => $user->structure?->name,
            ]);
        }

        foreach ($participants as $row) {
            $appointment->participants()->create([
                'user_id' => $row['user_id'] ?? null,
                'participation_type' => $row['participation_type'] ?? (! empty($row['user_id']) ? 'interne' : 'externe'),
                'role' => $row['role'] ?? 'participant',
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'organization' => $row['organization'] ?? null,
                'position' => $row['position'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'expected_presence' => $row['expected_presence'] ?? true,
            ]);
        }
    }

    private function notifyStakeholders(Appointment $appointment, string $event, string $message, ?User $actor = null): void
    {
        $ids = collect([
            $appointment->director_id,
            $appointment->secretariat_id,
            $appointment->requester_user_id,
            $appointment->created_by,
        ])->filter()->unique()->values();

        $users = User::query()->whereIn('id', $ids)->get();
        foreach ($users as $user) {
            if ($actor && (int) $user->id === (int) $actor->id) {
                continue;
            }
            $user->notify(new AppointmentNotification($appointment, $event, $message, $actor?->name));
        }

        foreach ($appointment->participants()->whereNotNull('user_id')->with('user')->get() as $participant) {
            if (! $participant->user) {
                continue;
            }
            if ($actor && (int) $participant->user_id === (int) $actor->id) {
                continue;
            }
            $participant->user->notify(new AppointmentNotification($appointment, $event, $message, $actor?->name));
        }
    }

    private function nextReference(): string
    {
        $year = now()->format('Y');
        $count = Appointment::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('RDV-%s-%04d', $year, $count);
    }

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return [
            'type', 'director', 'secretariat', 'requesterUser', 'structure',
            'participants.user', 'documentLinks.document.type', 'notes.author',
            'followups.instruction', 'followups.assignee', 'followups.followupAppointment',
            'followupsAppointments', 'convertedMeeting', 'linkedDocument',
        ];
    }
}
