<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Enums\ExpectedAction;
use App\Enums\MeetingDecisionStatus;
use App\Enums\MeetingDocumentKind;
use App\Enums\MeetingStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Instruction;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingDecision;
use App\Models\MeetingDocument;
use App\Models\MeetingMinute;
use App\Models\MeetingNote;
use App\Models\MeetingParticipant;
use App\Models\MeetingRecurrence;
use App\Models\User;
use App\Notifications\MeetingNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MeetingService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MeetingStateMachine $states,
        private readonly MeetingAccessService $access,
        private readonly MeetingDocumentRenderer $renderer,
        private readonly PrivateDocumentStorage $storage,
        private readonly DocumentWorkflowService $documents,
        private readonly DocumentAccessService $documentAccess,
    ) {}

    public function create(User $actor, array $data): Meeting
    {
        return DB::transaction(function () use ($actor, $data) {
            $title = $data['title'] ?? $data['object'] ?? 'Réunion';
            $meeting = Meeting::query()->create([
                'reference' => $this->generateReference(),
                'title' => $title,
                'object' => $data['object'] ?? $title,
                'description' => $data['description'] ?? $data['notes'] ?? null,
                'meeting_type_id' => $data['meeting_type_id'] ?? null,
                'meeting_date' => $data['meeting_date'],
                'meeting_time' => $data['meeting_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'location' => $data['location'] ?? null,
                'visio_url' => $data['visio_url'] ?? null,
                'chair_id' => $data['chair_id'] ?? null,
                'structure_id' => $data['structure_id'] ?? $actor->structure_id,
                'secretary_id' => $data['secretary_id'] ?? null,
                'organizer_id' => $data['organizer_id'] ?? $actor->id,
                'created_by' => $actor->id,
                'agenda' => $data['agenda'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => MeetingStatus::Brouillon,
                'confidentiality' => $data['confidentiality'] ?? DocumentConfidentiality::Normal->value,
                'priority' => $data['priority'] ?? 'normale',
                'is_recurring' => (bool) ($data['is_recurring'] ?? false),
                'parent_meeting_id' => $data['parent_meeting_id'] ?? null,
                'observations' => $data['observations'] ?? null,
            ]);

            if (! empty($data['recurrence']) && is_array($data['recurrence'])) {
                $recurrence = MeetingRecurrence::query()->create([
                    'frequency' => $data['recurrence']['frequency'] ?? 'weekly',
                    'interval' => max(1, (int) ($data['recurrence']['interval'] ?? 1)),
                    'weekday' => isset($data['recurrence']['weekday']) ? (int) $data['recurrence']['weekday'] : null,
                    'starts_on' => $data['recurrence']['starts_on'] ?? $meeting->meeting_date,
                    'ends_on' => $data['recurrence']['ends_on'] ?? null,
                    'occurrences_limit' => isset($data['recurrence']['occurrences_limit'])
                        ? (int) $data['recurrence']['occurrences_limit']
                        : null,
                ]);
                $meeting->update([
                    'recurrence_id' => $recurrence->id,
                    'is_recurring' => true,
                ]);
                $meeting->setRelation('recurrence', $recurrence);
            }

            $this->syncParticipants($meeting, $data['participants'] ?? $data['participant_ids'] ?? []);
            $this->syncDocuments($meeting, $data['documents'] ?? $data['document_ids'] ?? [], $actor);
            $this->syncAgenda($meeting, $data['agenda_items'] ?? [], $data['agenda'] ?? null);

            if (! empty($data['parent_meeting_id'])) {
                $this->attachPreviousDecisionsFollowUp($meeting, (int) $data['parent_meeting_id']);
            } elseif ($meeting->is_recurring) {
                $previous = Meeting::query()
                    ->where('recurrence_id', $meeting->recurrence_id)
                    ->where('id', '!=', $meeting->id)
                    ->orderByDesc('meeting_date')
                    ->first();
                if ($previous) {
                    $this->attachPreviousDecisionsFollowUp($meeting, $previous->id);
                }
            }

            if (! empty($data['recurrence']) && is_array($data['recurrence'])) {
                $this->generateOccurrenceSeries($actor, $meeting->fresh(['recurrence', 'participants', 'agendaItems']));
            }

            $this->audit->log('meeting.created', $meeting, ['reference' => $meeting->reference]);

            return $this->loadMeeting($meeting);
        });
    }

    public function update(User $actor, Meeting $meeting, array $data): Meeting
    {
        $this->assertLock($meeting, $data['lock_version'] ?? null);

        return DB::transaction(function () use ($actor, $meeting, $data) {
            $changes = [];
            foreach (['title', 'object', 'description', 'meeting_type_id', 'meeting_date', 'meeting_time', 'end_time', 'location', 'visio_url', 'chair_id', 'structure_id', 'secretary_id', 'organizer_id', 'agenda', 'notes', 'confidentiality', 'priority', 'observations'] as $field) {
                if (array_key_exists($field, $data) && (string) $meeting->{$field} !== (string) ($data[$field] ?? '')) {
                    $changes[$field] = ['from' => $meeting->{$field}, 'to' => $data[$field]];
                }
            }

            $payload = collect($data)->only([
                'title', 'object', 'description', 'meeting_type_id', 'meeting_date', 'meeting_time',
                'end_time', 'location', 'visio_url', 'chair_id', 'structure_id', 'secretary_id',
                'organizer_id', 'agenda', 'notes', 'confidentiality', 'priority', 'observations',
            ])->all();

            if (isset($payload['title']) && empty($payload['object'])) {
                $payload['object'] = $payload['title'];
            }

            $meeting->fill($payload);
            $meeting->lock_version = ((int) $meeting->lock_version) + 1;
            $meeting->save();

            if (array_key_exists('participants', $data) || array_key_exists('participant_ids', $data)) {
                $this->syncParticipants($meeting, $data['participants'] ?? $data['participant_ids'] ?? []);
                $this->audit->log('meeting.participants_updated', $meeting);
            }

            if (array_key_exists('documents', $data) || array_key_exists('document_ids', $data)) {
                $this->syncDocuments($meeting, $data['documents'] ?? $data['document_ids'] ?? [], $actor);
            }

            if (array_key_exists('agenda_items', $data)) {
                $this->syncAgenda($meeting, $data['agenda_items'], null);
            }

            $this->notifyOnScheduleChange($meeting, $actor, $changes);
            $this->audit->log('meeting.updated', $meeting, $changes);

            return $this->loadMeeting($meeting);
        });
    }

    public function transition(User $actor, Meeting $meeting, MeetingStatus $to, array $meta = []): Meeting
    {
        $from = $meeting->status instanceof MeetingStatus
            ? $meeting->status
            : MeetingStatus::from((string) $meeting->status);

        $this->states->assertCanTransition($from, $to);

        return DB::transaction(function () use ($actor, $meeting, $from, $to, $meta) {
            $meeting->status = $to;
            $meeting->lock_version = ((int) $meeting->lock_version) + 1;

            if ($to === MeetingStatus::EnCours) {
                $meeting->started_at = $meeting->started_at ?: now();
                if (! $meeting->current_agenda_item_id) {
                    $meeting->current_agenda_item_id = $meeting->agendaItems()->orderBy('sort_order')->value('id');
                }
            }

            if ($to === MeetingStatus::Terminee) {
                $meeting->ended_at = now();
            }

            if ($to === MeetingStatus::Annulee) {
                $meeting->cancellation_reason = $meta['reason'] ?? $meeting->cancellation_reason;
            }

            if ($to === MeetingStatus::Archivee) {
                $meeting->locked_at = now();
            }

            $meeting->save();
            $this->audit->log('meeting.status_changed', $meeting, [
                'from' => $from->value,
                'to' => $to->value,
                'reason' => $meta['reason'] ?? null,
            ]);

            if (in_array($to, [MeetingStatus::Annulee, MeetingStatus::EnCours, MeetingStatus::Terminee], true)) {
                $event = match ($to) {
                    MeetingStatus::Annulee => 'cancelled',
                    MeetingStatus::EnCours => 'started',
                    MeetingStatus::Terminee => 'finished',
                    default => 'updated',
                };
                $this->notifyParticipants($meeting, $event, sprintf(
                    'La réunion « %s » est maintenant : %s.',
                    $meeting->displayTitle(),
                    $to->label()
                ), $actor);
            }

            if (in_array($to, [MeetingStatus::Terminee, MeetingStatus::Cloturee], true) && $meeting->is_recurring) {
                $this->spawnNextOccurrence($actor, $meeting->fresh(['recurrence', 'participants', 'agendaItems']));
            }

            return $this->loadMeeting($meeting);
        });
    }

    public function postpone(User $actor, Meeting $meeting, string $date, ?string $time = null, ?string $location = null, ?string $reason = null): Meeting
    {
        return DB::transaction(function () use ($actor, $meeting, $date, $time, $location, $reason) {
            $meeting->previous_meeting_date = $meeting->meeting_date;
            $meeting->previous_meeting_time = $meeting->meeting_time;
            $meeting->previous_location = $meeting->location;
            $meeting->meeting_date = $date;
            if ($time) {
                $meeting->meeting_time = $time;
            }
            if ($location) {
                $meeting->location = $location;
            }
            $meeting->observations = trim(($meeting->observations ? $meeting->observations."\n" : '').($reason ? 'Report : '.$reason : ''));
            $meeting->lock_version = ((int) $meeting->lock_version) + 1;
            $meeting->save();

            $current = $meeting->status instanceof MeetingStatus
                ? $meeting->status
                : MeetingStatus::from((string) $meeting->status);
            if ($current !== MeetingStatus::Reportee) {
                $this->states->assertCanTransition($current, MeetingStatus::Reportee);
                $meeting->status = MeetingStatus::Reportee;
                $meeting->save();
            }

            $this->audit->log('meeting.postponed', $meeting, [
                'from' => $meeting->previous_meeting_date,
                'to' => $date,
                'reason' => $reason,
            ]);
            $this->notifyParticipants($meeting, 'postponed', 'La réunion a été reportée au '.optional($meeting->meeting_date)->format('d/m/Y').'.', $actor);

            return $this->loadMeeting($meeting);
        });
    }

    public function addAgendaItem(Meeting $meeting, array $data): MeetingAgendaItem
    {
        $max = (int) $meeting->agendaItems()->max('sort_order');

        $item = $meeting->agendaItems()->create([
            'item_number' => $data['item_number'] ?? ($meeting->agendaItems()->count() + 1),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'presenter_id' => $data['presenter_id'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($max + 1),
            'confidentiality' => $data['confidentiality'] ?? null,
            'status' => $data['status'] ?? 'prevu',
            'preparatory_notes' => $data['preparatory_notes'] ?? null,
            'is_follow_up' => (bool) ($data['is_follow_up'] ?? false),
        ]);

        $this->audit->log('meeting.agenda_added', $item);

        return $item->load('presenter');
    }

    public function reorderAgenda(Meeting $meeting, array $orderedIds): void
    {
        DB::transaction(function () use ($meeting, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                MeetingAgendaItem::query()
                    ->where('meeting_id', $meeting->id)
                    ->where('id', $id)
                    ->update([
                        'sort_order' => $index + 1,
                        'item_number' => $index + 1,
                    ]);
            }
            $meeting->increment('lock_version');
            $this->audit->log('meeting.agenda_reordered', $meeting);
        });
    }

    public function addParticipant(Meeting $meeting, array $data): MeetingParticipant
    {
        $participant = $meeting->participants()->create([
            'user_id' => $data['user_id'] ?? null,
            'role' => $data['role'] ?? 'participant',
            'participation_type' => $data['participation_type'] ?? (($data['user_id'] ?? null) ? 'interne' : 'externe'),
            'is_required' => array_key_exists('is_required', $data) ? (bool) $data['is_required'] : true,
            'invitation_status' => 'invite',
            'external_name' => $data['external_name'] ?? null,
            'external_function' => $data['external_function'] ?? null,
            'external_structure' => $data['external_structure'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'observations' => $data['observations'] ?? null,
        ]);

        $this->audit->log('meeting.participant_added', $meeting, ['participant_id' => $participant->id]);

        return $participant->load(['user', 'representative']);
    }

    public function confirmParticipation(User $actor, MeetingParticipant $participant, string $status, array $extra = []): MeetingParticipant
    {
        $allowed = ['confirme', 'refuse', 'absent', 'excuse', 'represente'];
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Statut de confirmation invalide.');
        }

        $participant->confirmation_status = $status;
        $participant->invitation_status = $status === 'confirme' ? 'confirme' : $status;
        $participant->confirmed_at = now();
        $participant->representative_id = $extra['representative_id'] ?? $participant->representative_id;
        $participant->representative_name = $extra['representative_name'] ?? $participant->representative_name;
        $participant->observations = $extra['observations'] ?? $participant->observations;
        $participant->save();

        $this->audit->log('meeting.participant_confirmed', $participant->meeting, [
            'user_id' => $actor->id,
            'status' => $status,
        ]);

        $meeting = $participant->meeting;
        if ($meeting && in_array($meeting->status, [MeetingStatus::Convoquee, MeetingStatus::Planifiee], true)) {
            $meeting->status = MeetingStatus::ConfirmationEnCours;
            $meeting->save();
        }

        return $participant->fresh(['user', 'representative']);
    }

    public function recordAttendance(Meeting $meeting, MeetingParticipant $participant, array $data): MeetingParticipant
    {
        $participant->attendance_status = $data['attendance_status'];
        $participant->arrived_at = $data['arrived_at'] ?? $participant->arrived_at;
        $participant->left_at = $data['left_at'] ?? $participant->left_at;
        $participant->representative_id = $data['representative_id'] ?? $participant->representative_id;
        $participant->representative_name = $data['representative_name'] ?? $participant->representative_name;
        $participant->observations = $data['observations'] ?? $participant->observations;
        $participant->save();

        $this->audit->log('meeting.attendance', $meeting, [
            'participant_id' => $participant->id,
            'status' => $participant->attendance_status,
        ]);

        return $participant->fresh(['user', 'representative']);
    }

    public function attachExistingDocument(User $actor, Meeting $meeting, int $documentId, array $meta = []): MeetingDocument
    {
        $document = Document::query()->findOrFail($documentId);
        abort_unless($this->documentAccess->canAccess($actor, $document), 403, 'Document inaccessible.');

        $link = MeetingDocument::query()->updateOrCreate(
            ['meeting_id' => $meeting->id, 'document_id' => $document->id],
            [
                'agenda_item_id' => $meta['agenda_item_id'] ?? null,
                'kind' => $meta['kind'] ?? MeetingDocumentKind::DocumentDeTravail->value,
                'sort_order' => $meta['sort_order'] ?? ((int) $meeting->documentLinks()->max('sort_order') + 1),
                'agenda_label' => $meta['agenda_label'] ?? null,
            ]
        );

        $this->audit->log('meeting.document_attached', $meeting, ['document_id' => $document->id]);
        $this->notifyParticipants($meeting, 'document_added', 'Un document préparatoire a été ajouté à la réunion « '.$meeting->displayTitle().' ».', $actor);

        return $link->load(['document.type', 'agendaItem']);
    }

    public function uploadDocument(User $actor, Meeting $meeting, UploadedFile $file, array $meta = []): MeetingDocument
    {
        $this->assertUpload($file);

        $type = DocumentType::query()->where('code', $meta['document_type_code'] ?? 'DOSSIER_REUNION')->first()
            ?? DocumentType::query()->firstOrFail();

        $document = $this->documents->createDraft($actor, [
            'object' => $meta['object'] ?? ($meeting->displayTitle().' — '.$file->getClientOriginalName()),
            'document_type_id' => $type->id,
            'structure_id' => $meeting->structure_id ?? $actor->structure_id,
            'confidentiality' => $meeting->confidentiality?->value ?? 'normal',
            'priority' => $meeting->priority?->value ?? 'normale',
            'keywords' => ['reunion', $meeting->reference],
        ], $file);

        return $this->attachExistingDocument($actor, $meeting, $document->id, $meta);
    }

    public function addDecision(User $actor, Meeting $meeting, array $data): MeetingDecision
    {
        return DB::transaction(function () use ($actor, $meeting, $data) {
            $count = $meeting->decisions()->count() + 1;
            $decision = $meeting->decisions()->create([
                'reference' => $data['reference'] ?? sprintf('%s-D%02d', $meeting->reference, $count),
                'agenda_item_id' => $data['agenda_item_id'] ?? $meeting->current_agenda_item_id,
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'structure_id' => $data['structure_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'priority' => $data['priority'] ?? 'normale',
                'status' => MeetingDecisionStatus::AFaire,
                'observations' => $data['observations'] ?? null,
            ]);

            if (! empty($data['create_instruction']) && ! empty($data['assignee_id'])) {
                $this->createInstructionFromDecision($actor, $decision, $data);
            }

            $this->audit->log('meeting.decision_created', $decision);

            if ($decision->assignee) {
                $decision->assignee->notify(new MeetingNotification(
                    $meeting,
                    'decision_assigned',
                    'Une décision de réunion vous a été affectée : '.$decision->title,
                    $actor->name
                ));
            }

            return $decision->load(['assignee', 'structure', 'agendaItem', 'instruction']);
        });
    }

    public function createInstructionFromDecision(User $actor, MeetingDecision $decision, array $data = []): Instruction
    {
        $instruction = Instruction::query()->create([
            'meeting_decision_id' => $decision->id,
            'issuer_id' => $actor->id,
            'assignee_id' => $data['assignee_id'] ?? $decision->assignee_id,
            'structure_id' => $data['structure_id'] ?? $decision->structure_id,
            'title' => $data['title'] ?? $decision->title,
            'body' => $data['body'] ?? $decision->body ?? $decision->title,
            'priority' => $data['priority'] ?? $decision->priority?->value ?? 'normale',
            'status' => 'a_faire',
            'due_date' => $data['due_date'] ?? $decision->due_date,
        ]);

        $this->audit->log('instruction.created', $instruction, ['from' => 'meeting_decision']);

        return $instruction;
    }

    public function generateConvocation(User $actor, Meeting $meeting): array
    {
        $html = $this->renderer->convocation($meeting, $meeting->chair);

        $type = DocumentType::query()->where('code', 'CONVOCATION')->first()
            ?? DocumentType::query()->where('code', 'LETTRE')->first()
            ?? DocumentType::query()->firstOrFail();

        $document = $this->storeGeneratedDocument(
            $actor,
            $meeting,
            $type,
            'Convocation — '.$meeting->displayTitle(),
            $html,
            'convocation-'.$meeting->reference.'.html',
            MeetingDocumentKind::Convocation
        );

        $meeting->convocation_document_id = $document->id;
        $meeting->save();

        $this->audit->log('meeting.convocation_generated', $meeting, ['document_id' => $document->id]);

        return ['html' => $html, 'document' => $document, 'meeting' => $this->loadMeeting($meeting)];
    }

    public function submitConvocation(User $actor, Meeting $meeting, ?int $validatorId = null): Meeting
    {
        if (! $meeting->convocation_document_id) {
            $this->generateConvocation($actor, $meeting);
            $meeting->refresh();
        }

        $document = $meeting->convocationDocument;
        $to = $validatorId ? User::query()->findOrFail($validatorId) : $meeting->chair;

        if ($document && $to) {
            $this->documents->submitAndTransmit(
                $document,
                $actor,
                $to,
                ExpectedAction::Visa,
                'Validation de la convocation de la réunion '.$meeting->reference
            );
        }

        return $this->transition($actor, $meeting, MeetingStatus::ConvocationAValider);
    }

    public function validateAndSendInvitations(User $actor, Meeting $meeting): Meeting
    {
        $from = $meeting->status instanceof MeetingStatus
            ? $meeting->status
            : MeetingStatus::from((string) $meeting->status);

        if ($from === MeetingStatus::ConvocationAValider) {
            $this->transition($actor, $meeting, MeetingStatus::Convoquee);
        } elseif (in_array($from, [MeetingStatus::Brouillon, MeetingStatus::EnPreparation, MeetingStatus::Planifiee], true)) {
            if (! $meeting->convocation_document_id) {
                $this->generateConvocation($actor, $meeting);
            }
            $meeting->status = MeetingStatus::Convoquee;
            $meeting->lock_version = ((int) $meeting->lock_version) + 1;
            $meeting->save();
        }

        $meeting->participants()->each(function (MeetingParticipant $participant) use ($meeting, $actor) {
            $user = $participant->user;
            if ($user) {
                $user->notify(new MeetingNotification(
                    $meeting,
                    'invitation',
                    'Vous êtes convoqué(e) à la réunion « '.$meeting->displayTitle().' ».',
                    $actor->name
                ));
            } elseif ($participant->email) {
                \Illuminate\Support\Facades\Notification::route('mail', $participant->email)
                    ->notify(new MeetingNotification(
                        $meeting,
                        'invitation',
                        'Vous êtes convoqué(e) à la réunion « '.$meeting->displayTitle().' ».',
                        $actor->name
                    ));
            }
            $participant->invitation_status = 'convoque';
            $participant->notified_at = now();
            $participant->save();
        });

        $this->audit->log('meeting.invitations_sent', $meeting, [
            'count' => $meeting->participants()->count(),
        ]);

        return $this->loadMeeting($meeting);
    }

    public function generateMinutes(User $actor, Meeting $meeting, string $kind = 'cr_detaille'): MeetingMinute
    {
        $version = ((int) $meeting->minutes()->where('kind', $kind)->max('version_number')) + 1;
        $body = $this->renderer->buildMinutesBody($meeting, $kind);

        $minute = $meeting->minutes()->create([
            'kind' => $kind,
            'version_number' => $version,
            'body' => $body,
            'payload' => ['kind' => $kind],
            'status' => 'brouillon',
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);

        if ($meeting->status === MeetingStatus::Terminee) {
            $meeting->status = MeetingStatus::CrEnRedaction;
            $meeting->save();
        }

        $this->audit->log('meeting.minutes_generated', $minute);

        return $minute->load(['generator']);
    }

    public function submitMinutes(User $actor, Meeting $meeting, MeetingMinute $minute): MeetingMinute
    {
        $type = DocumentType::query()->where('code', 'CR')->first() ?? DocumentType::query()->firstOrFail();
        $document = $this->storeGeneratedDocument(
            $actor,
            $meeting,
            $type,
            ($minute->kind === 'pv' ? 'Procès-verbal' : 'Compte rendu').' — '.$meeting->displayTitle(),
            $minute->body ?: $this->renderer->buildMinutesBody($meeting, $minute->kind),
            $minute->kind.'-'.$meeting->reference.'-v'.$minute->version_number.'.html',
            $minute->kind === 'pv' ? MeetingDocumentKind::Pv : MeetingDocumentKind::CompteRendu
        );

        $minute->document_id = $document->id;
        $minute->status = 'soumis';
        $minute->submitted_at = now();
        $minute->save();

        $meeting->minutes_document_id = $document->id;
        $meeting->save();

        if ($meeting->chair) {
            $this->documents->submitAndTransmit(
                $document,
                $actor,
                $meeting->chair,
                ExpectedAction::Validation,
                'Validation du compte rendu de la réunion '.$meeting->reference
            );
        }

        $this->transition($actor, $meeting, MeetingStatus::CrEnValidation);
        $this->audit->log('meeting.minutes_submitted', $minute);

        return $minute->fresh(['document', 'generator']);
    }

    public function validateMinutes(User $actor, Meeting $meeting, MeetingMinute $minute): MeetingMinute
    {
        $minute->status = 'valide';
        $minute->validated_by = $actor->id;
        $minute->validated_at = now();
        $minute->save();

        $this->transition($actor, $meeting, MeetingStatus::CrValide);
        $this->audit->log('meeting.minutes_validated', $minute);

        return $minute->fresh(['validator', 'document']);
    }

    public function diffuseMinutes(User $actor, Meeting $meeting, MeetingMinute $minute): MeetingMinute
    {
        $minute->status = 'diffuse';
        $minute->diffused_at = now();
        $minute->save();

        $this->notifyParticipants(
            $meeting,
            'minutes_available',
            'Le compte rendu de la réunion « '.$meeting->displayTitle().' » est disponible.',
            $actor
        );
        $this->audit->log('meeting.minutes_diffused', $minute);

        return $minute->fresh(['document']);
    }

    public function spawnNextOccurrence(User $actor, Meeting $source): ?Meeting
    {
        if (! $source->recurrence) {
            $source->loadMissing('recurrence');
        }
        if (! $source->recurrence) {
            return null;
        }

        $existingCount = Meeting::query()->where('recurrence_id', $source->recurrence_id)->count();
        if ($source->recurrence->occurrences_limit && $existingCount >= (int) $source->recurrence->occurrences_limit) {
            return null;
        }

        // Ne pas dupliquer si une occurrence future existe déjà
        $hasFuture = Meeting::query()
            ->where('recurrence_id', $source->recurrence_id)
            ->whereDate('meeting_date', '>', optional($source->meeting_date)->toDateString())
            ->exists();
        if ($hasFuture) {
            return null;
        }

        $nextDate = $this->nextDate($source);
        if (! $nextDate) {
            return null;
        }

        if ($source->recurrence->ends_on && $nextDate->gt($source->recurrence->ends_on)) {
            return null;
        }

        $source->loadMissing(['participants', 'agendaItems']);

        $payload = [
            'title' => $source->title,
            'object' => $source->object,
            'description' => $source->description,
            'meeting_type_id' => $source->meeting_type_id,
            'meeting_date' => $nextDate->toDateString(),
            'meeting_time' => $source->meeting_time,
            'end_time' => $source->end_time,
            'location' => $source->location,
            'visio_url' => $source->visio_url,
            'chair_id' => $source->chair_id,
            'structure_id' => $source->structure_id,
            'secretary_id' => $source->secretary_id,
            'organizer_id' => $source->organizer_id,
            'confidentiality' => $source->confidentiality?->value ?? $source->confidentiality,
            'priority' => $source->priority?->value ?? $source->priority,
            'parent_meeting_id' => $source->id,
            'participants' => $source->participants->map(fn (MeetingParticipant $p) => [
                'user_id' => $p->user_id,
                'role' => $p->role,
                'participation_type' => $p->participation_type,
                'is_required' => $p->is_required,
                'external_name' => $p->external_name,
                'external_function' => $p->external_function,
                'external_structure' => $p->external_structure,
                'email' => $p->email,
                'phone' => $p->phone,
            ])->all(),
            'agenda_items' => $source->agendaItems->where('is_follow_up', false)->map(fn (MeetingAgendaItem $item) => [
                'title' => $item->title,
                'description' => $item->description,
                'presenter_id' => $item->presenter_id,
                'duration_minutes' => $item->duration_minutes,
            ])->values()->all(),
        ];

        $next = $this->create($actor, $payload);
        $next->update(['recurrence_id' => $source->recurrence_id, 'is_recurring' => true]);

        return $this->loadMeeting($next);
    }

    /**
     * Pré-génère les occurrences suivantes si ends_on ou occurrences_limit est défini (max 26).
     *
     * @return list<Meeting>
     */
    public function generateOccurrenceSeries(User $actor, Meeting $first): array
    {
        $first->loadMissing('recurrence');
        if (! $first->recurrence) {
            return [];
        }

        $hasBound = $first->recurrence->ends_on || $first->recurrence->occurrences_limit;
        if (! $hasBound) {
            return [];
        }

        $created = [];
        $current = $first;
        $max = 26;

        while (count($created) < $max) {
            // Contourne le garde « future déjà existante » en passant la dernière occurrence
            $nextDate = $this->nextDate($current);
            if (! $nextDate) {
                break;
            }
            if ($current->recurrence->ends_on && $nextDate->gt($current->recurrence->ends_on)) {
                break;
            }
            $count = Meeting::query()->where('recurrence_id', $first->recurrence_id)->count();
            if ($first->recurrence->occurrences_limit && $count >= (int) $first->recurrence->occurrences_limit) {
                break;
            }

            $payload = [
                'title' => $first->title,
                'object' => $first->object,
                'description' => $first->description,
                'meeting_type_id' => $first->meeting_type_id,
                'meeting_date' => $nextDate->toDateString(),
                'meeting_time' => $first->meeting_time,
                'end_time' => $first->end_time,
                'location' => $first->location,
                'visio_url' => $first->visio_url,
                'chair_id' => $first->chair_id,
                'structure_id' => $first->structure_id,
                'secretary_id' => $first->secretary_id,
                'organizer_id' => $first->organizer_id,
                'confidentiality' => $first->confidentiality?->value ?? $first->confidentiality,
                'priority' => $first->priority?->value ?? $first->priority,
                'parent_meeting_id' => $current->id,
                'participants' => $first->participants->map(fn (MeetingParticipant $p) => [
                    'user_id' => $p->user_id,
                    'role' => $p->role,
                    'participation_type' => $p->participation_type,
                    'is_required' => $p->is_required,
                    'external_name' => $p->external_name,
                    'external_function' => $p->external_function,
                    'external_structure' => $p->external_structure,
                    'email' => $p->email,
                    'phone' => $p->phone,
                ])->all(),
                'agenda_items' => $first->agendaItems->where('is_follow_up', false)->map(fn (MeetingAgendaItem $item) => [
                    'title' => $item->title,
                    'description' => $item->description,
                    'presenter_id' => $item->presenter_id,
                    'duration_minutes' => $item->duration_minutes,
                ])->values()->all(),
            ];

            $next = $this->create($actor, $payload);
            $next->update(['recurrence_id' => $first->recurrence_id, 'is_recurring' => true]);
            $next = $this->loadMeeting($next);
            $created[] = $next;
            $current = $next;
        }

        return $created;
    }

    public function dashboard(User $user): array
    {
        $base = $this->access->visibleQuery($user);
        $today = now()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        $decisions = MeetingDecision::query()
            ->whereHas('meeting', fn ($q) => $q->whereIn('id', (clone $base)->select('id')));

        return [
            'today' => (clone $base)->whereDate('meeting_date', $today)->count(),
            'this_week' => (clone $base)->whereBetween('meeting_date', [$today, $weekEnd])->count(),
            'upcoming' => (clone $base)->whereDate('meeting_date', '>=', $today)->whereNotIn('status', ['annulee', 'cloturee', 'archivee'])->count(),
            'in_preparation' => (clone $base)->whereIn('status', ['brouillon', 'en_preparation', 'convocation_a_valider'])->count(),
            'in_progress' => (clone $base)->whereIn('status', ['en_cours', 'suspendue'])->count(),
            'finished' => (clone $base)->whereIn('status', ['terminee', 'cr_en_redaction', 'cr_en_validation', 'cr_valide', 'cloturee'])->count(),
            'minutes_to_draft' => (clone $base)->whereIn('status', ['terminee', 'cr_en_redaction'])->count(),
            'minutes_to_validate' => (clone $base)->where('status', 'cr_en_validation')->count(),
            'decisions_open' => (clone $decisions)->whereIn('status', ['a_faire', 'planifiee', 'en_cours', 'en_attente', 'bloquee', 'partiellement_executee', 'en_retard'])->count(),
            'decisions_late' => (clone $decisions)->whereIn('status', ['a_faire', 'planifiee', 'en_cours', 'en_attente', 'bloquee', 'partiellement_executee', 'en_retard'])->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
        ];
    }

    public function serialize(Meeting $meeting, ?User $viewer = null): array
    {
        $meeting = $this->loadMeeting($meeting);
        $notes = $meeting->sessionNotes ?? collect();
        if ($viewer) {
            $notes = $notes->filter(fn (MeetingNote $note) => $this->access->canViewNote($viewer, $note))->values();
        } else {
            $notes = $notes->where('visibility', 'officielle')->values();
        }

        $confirmed = $meeting->participants->whereIn('confirmation_status', ['confirme', 'represente'])->count();
        $total = max($meeting->participants->count(), 1);

        $previousOpen = [];
        if ($meeting->parent_meeting_id) {
            $previousOpen = MeetingDecision::query()
                ->with(['assignee', 'instruction'])
                ->where('meeting_id', $meeting->parent_meeting_id)
                ->get()
                ->filter(fn (MeetingDecision $d) => $d->effectiveStatus()->isOpen())
                ->values();
        }

        return [
            'id' => $meeting->id,
            'reference' => $meeting->reference,
            'title' => $meeting->title,
            'object' => $meeting->displayTitle(),
            'description' => $meeting->description,
            'meeting_date' => optional($meeting->meeting_date)->toDateString(),
            'meeting_time' => $meeting->meeting_time,
            'end_time' => $meeting->end_time,
            'location' => $meeting->location,
            'visio_url' => $meeting->visio_url,
            'status' => $meeting->status?->value ?? $meeting->status,
            'status_label' => $meeting->status instanceof MeetingStatus ? $meeting->status->label() : $meeting->status,
            'allowed_transitions' => $this->states->allowedFrom(
                $meeting->status instanceof MeetingStatus ? $meeting->status : MeetingStatus::from((string) $meeting->status)
            ),
            'confidentiality' => $meeting->confidentiality?->value ?? $meeting->confidentiality,
            'priority' => $meeting->priority?->value ?? $meeting->priority,
            'is_recurring' => $meeting->is_recurring,
            'observations' => $meeting->observations,
            'cancellation_reason' => $meeting->cancellation_reason,
            'lock_version' => $meeting->lock_version,
            'started_at' => optional($meeting->started_at)?->toIso8601String(),
            'ended_at' => optional($meeting->ended_at)?->toIso8601String(),
            'current_agenda_item_id' => $meeting->current_agenda_item_id,
            'type' => $meeting->type,
            'structure' => $meeting->structure,
            'chair' => $meeting->chair,
            'secretary' => $meeting->secretary,
            'organizer' => $meeting->organizer,
            'creator' => $meeting->creator,
            'parent_meeting_id' => $meeting->parent_meeting_id,
            'recurrence' => $meeting->recurrence,
            'participants' => $meeting->participants,
            'agenda_items' => $meeting->agendaItems,
            'documents' => $meeting->documentLinks->map(function (MeetingDocument $link) {
                $document = $link->document;
                $version = $document?->latestVersion;

                return [
                    'id' => $link->id,
                    'kind' => $link->kind,
                    'sort_order' => $link->sort_order,
                    'agenda_item_id' => $link->agenda_item_id,
                    'agenda_label' => $link->agenda_label,
                    'document' => $document ? [
                        'id' => $document->id,
                        'uuid' => $document->uuid,
                        'object' => $document->object,
                        'reference' => $document->reference,
                        'status' => $document->status,
                        'type' => $document->type,
                        'latest_version' => $version ? [
                            'id' => $version->id,
                            'version_number' => $version->version_number,
                            'mime_type' => $version->mime_type,
                            'original_name' => $version->original_name,
                        ] : null,
                    ] : null,
                ];
            }),
            'decisions' => $meeting->decisions->map(fn (MeetingDecision $d) => [
                ...$d->toArray(),
                'status' => $d->effectiveStatus()->value,
                'status_label' => $d->effectiveStatus()->label(),
                'is_overdue' => $d->isOverdue(),
            ]),
            'recommendations' => $meeting->recommendations,
            'notes' => $notes,
            'minutes' => $meeting->minutes,
            'convocation_document' => $meeting->convocationDocument,
            'minutes_document' => $meeting->minutesDocument,
            'previous_open_decisions' => $previousOpen,
            'stats' => [
                'participants' => $meeting->participants->count(),
                'confirmation_rate' => round(($confirmed / $total) * 100, 1),
                'documents' => $meeting->documentLinks->count(),
                'decisions' => $meeting->decisions->count(),
                'actions' => $meeting->decisions->sum(fn (MeetingDecision $d) => $d->instructions->count()),
                'minutes_status' => $meeting->minutes->first()?->status,
            ],
        ];
    }

    public function loadMeeting(Meeting $meeting): Meeting
    {
        return $meeting->fresh([
            'type', 'structure', 'chair', 'secretary', 'organizer', 'creator', 'recurrence',
            'currentAgendaItem', 'convocationDocument.type', 'minutesDocument.type',
            'participants.user.structure', 'participants.representative',
            'agendaItems.presenter',
            'documentLinks.document.type',
            'documentLinks.document.latestVersion',
            'decisions.assignee', 'decisions.structure', 'decisions.instruction', 'decisions.instructions',
            'recommendations.structure', 'recommendations.creator',
            'sessionNotes.author', 'sessionNotes.agendaItem',
            'minutes.generator', 'minutes.validator', 'minutes.document',
        ]) ?? $meeting;
    }

    public function generateReference(): string
    {
        $year = now()->format('Y');
        $count = Meeting::query()->withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('REU-%s-%04d', $year, $count);
    }

    public function exportCsv(iterable $rows, array $headers, string $filename)
    {
        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<mixed>  $participants
     */
    private function syncParticipants(Meeting $meeting, array $participants): void
    {
        $ids = [];
        foreach ($participants as $item) {
            if (is_numeric($item)) {
                $participant = $meeting->participants()->updateOrCreate(
                    ['user_id' => (int) $item],
                    ['role' => 'participant', 'participation_type' => 'interne', 'invitation_status' => 'invite']
                );
                $ids[] = $participant->id;
                continue;
            }
            if (! is_array($item)) {
                continue;
            }

            $type = $item['participation_type'] ?? (($item['user_id'] ?? null) ? 'interne' : 'externe');

            if ($type === 'interne' && ! empty($item['user_id'])) {
                $participant = $meeting->participants()->updateOrCreate(
                    ['user_id' => (int) $item['user_id']],
                    [
                        'role' => $item['role'] ?? 'participant',
                        'participation_type' => 'interne',
                        'is_required' => array_key_exists('is_required', $item) ? (bool) $item['is_required'] : true,
                        'invitation_status' => $item['invitation_status'] ?? 'invite',
                        'email' => $item['email'] ?? null,
                        'phone' => $item['phone'] ?? null,
                    ]
                );
                $ids[] = $participant->id;
                continue;
            }

            if ($type === 'externe' && ! empty($item['email'])) {
                $participant = $meeting->participants()
                    ->whereNull('user_id')
                    ->where('email', $item['email'])
                    ->first();

                $payload = [
                    'user_id' => null,
                    'role' => $item['role'] ?? 'participant',
                    'participation_type' => 'externe',
                    'is_required' => array_key_exists('is_required', $item) ? (bool) $item['is_required'] : true,
                    'external_name' => $item['external_name'] ?? null,
                    'external_function' => $item['external_function'] ?? null,
                    'external_structure' => $item['external_structure'] ?? null,
                    'email' => $item['email'],
                    'phone' => $item['phone'] ?? null,
                    'invitation_status' => $item['invitation_status'] ?? 'invite',
                ];

                if ($participant) {
                    $participant->update($payload);
                } else {
                    $participant = $meeting->participants()->create($payload);
                }
                $ids[] = $participant->id;
            }
        }

        if ($participants !== []) {
            $meeting->participants()->whereNotIn('id', $ids)->delete();
        }
    }

    /**
     * @param  list<mixed>  $documents
     */
    private function syncDocuments(Meeting $meeting, array $documents, User $actor): void
    {
        $keep = [];
        foreach ($documents as $index => $item) {
            $documentId = is_numeric($item) ? (int) $item : (int) ($item['document_id'] ?? $item['id'] ?? 0);
            if (! $documentId) {
                continue;
            }
        $document = Document::query()->find($documentId);
        if (! $document || ! $this->documentAccess->canAccess($actor, $document)) {
            continue;
        }
            $link = MeetingDocument::query()->updateOrCreate(
                ['meeting_id' => $meeting->id, 'document_id' => $documentId],
                [
                    'sort_order' => is_array($item) ? ($item['sort_order'] ?? $index + 1) : $index + 1,
                    'kind' => is_array($item) ? ($item['kind'] ?? MeetingDocumentKind::DocumentDeTravail->value) : MeetingDocumentKind::DocumentDeTravail->value,
                    'agenda_item_id' => is_array($item) ? ($item['agenda_item_id'] ?? null) : null,
                    'agenda_label' => is_array($item) ? ($item['agenda_label'] ?? null) : null,
                ]
            );
            $keep[] = $link->id;
        }

        $meeting->documentLinks()->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function syncAgenda(Meeting $meeting, array $items, ?string $legacyAgenda): void
    {
        if ($items === [] && $legacyAgenda) {
            foreach (preg_split('/\r\n|\r|\n/', $legacyAgenda) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $line = preg_replace('/^\d+[\.\)]\s*/', '', $line) ?? $line;
                $items[] = ['title' => $line];
            }
        }

        if ($items === []) {
            return;
        }

        $keep = [];
        foreach ($items as $index => $item) {
            if (is_string($item)) {
                $item = ['title' => $item];
            }
            $attributes = [
                'item_number' => $item['item_number'] ?? $index + 1,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'presenter_id' => $item['presenter_id'] ?? null,
                'duration_minutes' => $item['duration_minutes'] ?? null,
                'sort_order' => $item['sort_order'] ?? $index + 1,
                'confidentiality' => $item['confidentiality'] ?? null,
                'status' => $item['status'] ?? 'prevu',
                'preparatory_notes' => $item['preparatory_notes'] ?? null,
                'is_follow_up' => (bool) ($item['is_follow_up'] ?? false),
            ];

            if (! empty($item['id'])) {
                $row = $meeting->agendaItems()->whereKey($item['id'])->first();
                if ($row) {
                    $row->update($attributes);
                } else {
                    $row = $meeting->agendaItems()->create($attributes);
                }
            } else {
                $row = $meeting->agendaItems()->create($attributes);
            }
            $keep[] = $row->id;
        }
        $meeting->agendaItems()->whereNotIn('id', $keep)->delete();
    }

    private function attachPreviousDecisionsFollowUp(Meeting $meeting, int $parentId): void
    {
        $parent = Meeting::query()->with('decisions')->find($parentId);
        if (! $parent) {
            return;
        }

        $open = $parent->decisions->filter(fn (MeetingDecision $d) => $d->effectiveStatus()->isOpen());
        $done = $parent->decisions->count() - $open->count();
        $late = $open->filter(fn (MeetingDecision $d) => $d->isOverdue())->count();

        $exists = $meeting->agendaItems()->where('is_follow_up', true)->exists();
        if ($exists) {
            return;
        }

        $this->addAgendaItem($meeting, [
            'title' => 'Suivi des décisions de la réunion précédente',
            'description' => sprintf(
                '%d décision(s) : %d exécutée(s), %d en cours, %d en retard.',
                $parent->decisions->count(),
                $done,
                $open->count() - $late,
                $late
            ),
            'sort_order' => 0,
            'item_number' => 1,
            'is_follow_up' => true,
        ]);

        $meeting->agendaItems()->where('is_follow_up', false)->increment('item_number');
        $meeting->parent_meeting_id = $parentId;
        $meeting->save();
    }

    private function storeGeneratedDocument(
        User $actor,
        Meeting $meeting,
        DocumentType $type,
        string $object,
        string $html,
        string $filename,
        MeetingDocumentKind $kind
    ): Document {
        $document = $this->documents->createDraft($actor, [
            'object' => $object,
            'document_type_id' => $type->id,
            'structure_id' => $meeting->structure_id ?? $actor->structure_id,
            'confidentiality' => $meeting->confidentiality?->value ?? 'normal',
            'priority' => $meeting->priority?->value ?? 'normale',
            'keywords' => ['reunion', $kind->value, $meeting->reference],
        ]);

        $stored = $this->storage->storeContent($html, $filename, 'text/html', 'documents/'.$document->uuid);
        $document->versions()->where('is_main', true)->update(['is_main' => false]);
        DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'checksum' => $stored['checksum'],
            'is_main' => true,
            'uploaded_by' => $actor->id,
            'change_note' => 'Génération automatique depuis le module Réunions',
        ]);
        $document->current_version = 1;
        $document->save();

        MeetingDocument::query()->updateOrCreate(
            ['meeting_id' => $meeting->id, 'document_id' => $document->id],
            ['kind' => $kind->value, 'sort_order' => 0]
        );

        return $document->fresh(['type', 'latestVersion']);
    }

    private function notifyParticipants(Meeting $meeting, string $event, string $message, ?User $actor = null): void
    {
        $meeting->loadMissing('participants.user');
        foreach ($meeting->participants as $participant) {
            if ($participant->user) {
                $participant->user->notify(new MeetingNotification($meeting, $event, $message, $actor?->name));
            }
        }
    }

    private function notifyOnScheduleChange(Meeting $meeting, User $actor, array $changes): void
    {
        if (isset($changes['location'])) {
            $this->notifyParticipants($meeting, 'place_changed', 'Le lieu de la réunion a été modifié : '.$meeting->location, $actor);
        }
        if (isset($changes['meeting_date']) || isset($changes['meeting_time'])) {
            $this->notifyParticipants($meeting, 'time_changed', 'L’horaire de la réunion a été modifié.', $actor);
        }
    }

    private function assertLock(Meeting $meeting, mixed $version): void
    {
        if ($version === null || $version === '') {
            return;
        }
        if ((int) $version !== (int) $meeting->lock_version) {
            abort(409, 'La réunion a été modifiée par un autre utilisateur. Veuillez recharger.');
        }
    }

    private function assertUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('meetings.max_upload_kb', 20480);
        abort_if($file->getSize() > $maxKb * 1024, 422, 'Fichier trop volumineux.');

        $ext = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($ext, config('meetings.allowed_extensions', []), true), 422, 'Extension non autorisée.');
    }

    private function nextDate(Meeting $source): ?\Carbon\Carbon
    {
        $recurrence = $source->recurrence;
        if (! $recurrence || ! $source->meeting_date) {
            return null;
        }
        $date = $source->meeting_date->copy();
        $interval = max(1, (int) $recurrence->interval);

        $next = match ($recurrence->frequency) {
            'daily' => $date->addDays($interval),
            'weekly' => $date->addWeeks($interval),
            'monthly' => $date->addMonths($interval),
            default => $date->addWeeks($interval),
        };

        // weekday ISO : 1 = lundi … 7 = dimanche
        if ($recurrence->frequency === 'weekly' && $recurrence->weekday) {
            $target = (int) $recurrence->weekday;
            if ($target >= 1 && $target <= 7) {
                $guard = 0;
                while ((int) $next->dayOfWeekIso !== $target && $guard < 7) {
                    $next->addDay();
                    $guard++;
                }
            }
        }

        return $next;
    }
}
