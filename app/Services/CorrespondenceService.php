<?php

namespace App\Services;

use App\Enums\CorrespondenceDirection;
use App\Enums\CorrespondenceStatus;
use App\Enums\DocumentOrigin;
use App\Enums\NumberingSequenceCode;
use App\Models\Correspondence;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrespondenceService
{
    public function __construct(
        private readonly NumberingService $numberingService,
        private readonly CorrespondenceStateMachine $stateMachine,
        private readonly CorrespondenceEventService $eventService,
        private readonly DocumentService $documentService,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Crée un courrier entrant et lui attribue un numéro d'arrivée.
     *
     * @param  array<string, mixed>  $data
     */
    public function createIncoming(User $user, array $data, ?UploadedFile $scanFile = null): Correspondence
    {
        return DB::transaction(function () use ($user, $data, $scanFile) {
            $documentData = is_array($data['document_data'] ?? null) ? $data['document_data'] : [];
            [$data, $parties] = $this->extractPartiesInput($data);
            $data['structure_id'] = $data['structure_id'] ?? $user->structure_id;

            $arrivalNumber = $this->numberingService->nextNumber(
                NumberingSequenceCode::Arrival->value,
                structureId: $data['structure_id'] ?? null
            );

            $correspondence = Correspondence::query()->create([
                ...$data,
                'direction' => CorrespondenceDirection::Entrant,
                'arrival_number' => $arrivalNumber,
                'status' => CorrespondenceStatus::Enregistre,
                'registered_at' => now(),
                'registered_by' => $user->id,
                'is_registered' => true,
            ]);

            $this->seedParties($correspondence, $user, $parties);

            // Attacher le scan si fourni
            if ($scanFile) {
                $this->attachDocument($correspondence, $user, $scanFile, $documentData);
            }

            $this->eventService->logEvent(
                $correspondence,
                'correspondence_created',
                $user,
                metadata: ['direction' => 'entrant', 'arrival_number' => $arrivalNumber]
            );

            $this->audit->log('correspondence.created_incoming', $correspondence, [
                'actor_id' => $user->id,
                'arrival_number' => $arrivalNumber,
            ]);

            return $correspondence->fresh(['document', 'structure', 'registeredBy', 'parties']);
        });
    }

    /**
     * Crée un courrier sortant.
     */
    public function createOutgoing(User $user, array $data): Correspondence
    {
        return DB::transaction(function () use ($user, $data) {
            [$data, $parties] = $this->extractPartiesInput($data);
            $data['structure_id'] = $data['structure_id'] ?? $user->structure_id;

            $correspondence = Correspondence::query()->create([
                ...$data,
                'direction' => CorrespondenceDirection::Sortant,
                'status' => CorrespondenceStatus::ProjetReponse,
                'registered_by' => $user->id,
                'owner_user_id' => $user->id,
            ]);

            $this->seedParties($correspondence, $user, $parties);

            $this->eventService->logEvent(
                $correspondence,
                'correspondence_created',
                $user,
                metadata: ['direction' => 'sortant']
            );

            $this->audit->log('correspondence.created_outgoing', $correspondence, [
                'actor_id' => $user->id,
            ]);

            return $correspondence->fresh(['structure', 'registeredBy', 'parties']);
        });
    }

    /**
     * Crée un courrier interne.
     */
    public function createInternal(User $user, array $data): Correspondence
    {
        return DB::transaction(function () use ($user, $data) {
            [$data, $parties] = $this->extractPartiesInput($data);
            $data['structure_id'] = $data['structure_id'] ?? $user->structure_id;

            $correspondence = Correspondence::query()->create([
                ...$data,
                'direction' => CorrespondenceDirection::Interne,
                'status' => CorrespondenceStatus::Enregistre,
                'registered_at' => now(),
                'registered_by' => $user->id,
            ]);

            $this->seedParties($correspondence, $user, $parties);

            $this->eventService->logEvent(
                $correspondence,
                'correspondence_created',
                $user,
                metadata: ['direction' => 'interne']
            );

            $this->audit->log('correspondence.created_internal', $correspondence, [
                'actor_id' => $user->id,
            ]);

            return $correspondence->fresh(['structure', 'registeredBy', 'parties']);
        });
    }

    /**
     * Met à jour les métadonnées d'une correspondance.
     */
    public function update(Correspondence $correspondence, User $user, array $data): Correspondence
    {
        DB::transaction(function () use ($correspondence, $user, $data) {
            $fillable = [
                'subject', 'summary', 'observations', 'external_reference',
                'correspondence_date', 'due_date', 'medium', 'priority',
                'confidentiality', 'structure_id', 'channel_id', 'category_id', 'qualification_id',
                'piece_count', 'keywords', 'requires_reply',
            ];

            foreach ($fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $correspondence->{$field} = $data[$field];
                }
            }

            $correspondence->save();

            $this->eventService->logEvent(
                $correspondence,
                'correspondence_updated',
                $user,
                metadata: ['updated_fields' => array_keys(array_intersect_key($data, array_flip($fillable)))]
            );

            $this->audit->log('correspondence.updated', $correspondence, ['actor_id' => $user->id]);
        });

        return $correspondence->fresh();
    }

    /**
     * Attache un document (scan) à la correspondance via DocumentService.
     */
    public function attachDocument(
        Correspondence $correspondence,
        User $user,
        UploadedFile $file,
        array $documentData = []
    ): Correspondence {
        if ($correspondence->document_id) {
            throw new InvalidArgumentException('Un document est déjà attaché à cette correspondance.');
        }

        $document = $this->documentService->create(
            author: $user,
            data: [
                'document_type_id' => $documentData['document_type_id']
                    ?? \App\Models\DocumentType::query()->value('id'),
                ...$documentData,
                'origin' => DocumentOrigin::Courrier->value,
                'object' => $correspondence->subject,
                'summary' => $correspondence->summary,
                'structure_id' => $correspondence->structure_id,
                'priority' => $correspondence->priority?->value,
                'confidentiality' => $correspondence->confidentiality?->value,
            ],
            mainFile: $file
        );

        $correspondence->document_id = $document->id;
        $correspondence->save();

        $this->eventService->logEvent(
            $correspondence,
            'document_attached',
            $user,
            metadata: ['document_id' => $document->id]
        );

        return $correspondence->fresh('document');
    }

    /**
     * Liste des correspondances avec filtres.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(User $user, array $filters = [])
    {
        $query = Correspondence::query()
            ->with([
                'structure', 'registeredBy', 'channel', 'category', 'qualification', 'document',
                'assignments.toUser:id,name', 'assignments.toStructure:id,name,code',
            ]);

        // Filtre de base : accès selon permissions
        if (! $user->can('admin.access') && ! $user->can('mail.view_all')) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('registered_by', $user->id)
                    ->orWhere('structure_id', $user->structure_id)
                    ->orWhereHas('assignments', function ($subQ) use ($user) {
                        $subQ->where('to_user_id', $user->id);
                    });
            });
        }

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', $filters['structure_id']);
        }

        if (! empty($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhere('arrival_number', 'like', "%{$search}%")
                    ->orWhere('departure_number', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['unassigned'])) {
            $query->whereDoesntHave('assignments')
                ->whereIn('status', ['enregistre', 'a_qualifier', 'a_affecter', 'recu']);
        }

        if (! empty($filters['overdue'])) {
            $query->whereNotNull('due_date')
                ->where('due_date', '<', today())
                ->whereNotIn('status', ['classe', 'archive', 'annule', 'expedie', 'accuse_recu', 'repondu']);
        }

        if (! empty($filters['mine'])) {
            $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('to_user_id', $user->id)
                    ->whereIn('status', ['transmis', 'recu', 'pris_en_charge', 'consulte']);
            });
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $query->whereBetween('correspondence_date', [$filters['from'], $filters['to']]);
        }

        $query->orderByDesc('received_at')->orderByDesc('id');

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Payload API complet pour une correspondance.
     */
    public function serialize(Correspondence $correspondence): array
    {
        $correspondence->loadMissing([
            'document', 'structure', 'channel', 'category', 'qualification',
            'registeredBy', 'ownerUser', 'parties.correspondent',
            'assignments.toUser', 'assignments.toStructure', 'assignments.action',
            'events.user', 'outgoingLinks.targetCorrespondence', 'incomingLinks.sourceCorrespondence',
            'circulationSheets.document', 'circulationSheets.creator',
            'dispatches.document', 'dispatches.dispatchedBy',
            'acknowledgements.document', 'acknowledgements.registeredBy',
        ]);

        $parties = $correspondence->parties->map(fn ($party) => [
            'id' => $party->id,
            'role' => $party->role instanceof \BackedEnum ? $party->role->value : $party->role,
            'name' => $party->name,
            'function' => $party->function,
            'organization' => $party->organization,
            'correspondent_id' => $party->correspondent_id,
            'correspondent' => $party->correspondent,
            'display_order' => $party->display_order,
        ])->values();

        if ($correspondence->direction === CorrespondenceDirection::Entrant
            && ! $parties->contains(fn ($party) => ($party['role'] ?? null) === 'to')) {
            $parties->push([
                'id' => null,
                'role' => 'to',
                'name' => $correspondence->structure?->name ?: 'DGTCP',
                'function' => null,
                'organization' => null,
                'correspondent_id' => null,
                'correspondent' => null,
                'display_order' => $parties->count(),
            ]);
        }

        if ($correspondence->direction === CorrespondenceDirection::Sortant
            && ! $parties->contains(fn ($party) => ($party['role'] ?? null) === 'from')) {
            $parties->push([
                'id' => null,
                'role' => 'from',
                'name' => $correspondence->structure?->name ?: 'DGTCP',
                'function' => null,
                'organization' => null,
                'correspondent_id' => null,
                'correspondent' => null,
                'display_order' => $parties->count(),
            ]);
        }

        return [
            'id' => $correspondence->id,
            'uuid' => $correspondence->uuid,
            'direction' => $correspondence->direction?->value,
            'medium' => $correspondence->medium?->value,
            'status' => $correspondence->status?->value,
            'arrival_number' => $correspondence->arrival_number,
            'departure_number' => $correspondence->departure_number,
            'external_reference' => $correspondence->external_reference,
            'subject' => $correspondence->subject,
            'summary' => $correspondence->summary,
            'observations' => $correspondence->observations,
            'priority' => $correspondence->priority?->value,
            'confidentiality' => $correspondence->confidentiality?->value,
            'piece_count' => $correspondence->piece_count,
            'keywords' => $correspondence->keywords,
            'requires_reply' => $correspondence->requires_reply,
            'is_registered' => $correspondence->is_registered,
            'received_at' => $correspondence->received_at?->toIso8601String(),
            'correspondence_date' => $correspondence->correspondence_date?->toDateString(),
            'registered_at' => $correspondence->registered_at?->toIso8601String(),
            'due_date' => $correspondence->due_date?->toDateString(),
            'structure_id' => $correspondence->structure_id,
            'channel_id' => $correspondence->channel_id,
            'category_id' => $correspondence->category_id,
            'qualification_id' => $correspondence->qualification_id,
            'created_at' => $correspondence->created_at->toIso8601String(),
            'updated_at' => $correspondence->updated_at->toIso8601String(),
            'document' => $correspondence->document,
            'structure' => $correspondence->structure,
            'channel' => $correspondence->channel,
            'category' => $correspondence->category,
            'qualification' => $correspondence->qualification,
            'registered_by' => $correspondence->registeredBy,
            'owner_user' => $correspondence->ownerUser,
            'parties' => $parties,
            'assignments' => $correspondence->assignments,
            'events' => $correspondence->events,
            'circulation_sheets' => $correspondence->circulationSheets->map(fn ($sheet) => [
                'id' => $sheet->id,
                'number' => $sheet->number,
                'status' => $sheet->status,
                'document_id' => $sheet->document_id,
                'document' => $sheet->document,
                'created_by' => $sheet->creator?->only(['id', 'name']),
                'created_at' => $sheet->created_at?->toIso8601String(),
            ])->values(),
            'dispatches' => $correspondence->dispatches->map(fn ($dispatch) => [
                'id' => $dispatch->id,
                'number' => $dispatch->number,
                'method' => $dispatch->method,
                'tracking_number' => $dispatch->tracking_number,
                'dispatched_at' => $dispatch->dispatched_at?->toIso8601String(),
                'observations' => $dispatch->observations,
                'document_id' => $dispatch->document_id,
                'document' => $dispatch->document,
                'dispatched_by' => $dispatch->dispatchedBy?->only(['id', 'name']),
            ])->values(),
            'acknowledgements' => $correspondence->acknowledgements->map(fn ($ack) => [
                'id' => $ack->id,
                'number' => $ack->number,
                'method' => $ack->method,
                'acknowledged_by_name' => $ack->acknowledged_by_name,
                'acknowledged_at' => $ack->acknowledged_at?->toIso8601String(),
                'observations' => $ack->observations,
                'document_id' => $ack->document_id,
                'document' => $ack->document,
                'registered_by' => $ack->registeredBy?->only(['id', 'name']),
            ])->values(),
            'links' => [
                'outgoing' => $correspondence->outgoingLinks,
                'incoming' => $correspondence->incomingLinks,
            ],
        ];
    }

    /**
     * Synchronise les parties (expéditeurs, destinataires, etc.) d'une correspondance.
     *
     * @param  array<array{role: string, name?: string, organization?: string, correspondent_id?: int, function?: string}>  $parties
     */
    public function syncParties(Correspondence $correspondence, User $user, array $parties): void
    {
        DB::transaction(function () use ($correspondence, $user, $parties) {
            $correspondence->parties()->delete();
            $this->attachParties($correspondence, $parties);

            // Log l'événement
            $this->eventService->logEvent(
                $correspondence,
                'parties_synced',
                $user,
                metadata: ['parties_count' => count($parties)]
            );

            $this->audit->log('correspondence.parties_synced', $correspondence, [
                'actor_id' => $user->id,
                'parties_count' => count($parties),
            ]);
        });
    }

    /**
     * Inverse expéditeur / destinataire d'un courrier source (réponse).
     */
    public function copyInvertedParties(Correspondence $source, Correspondence $target, User $user): void
    {
        $source->load(['parties.correspondent']);

        $inverted = [];
        foreach ($source->parties as $party) {
            $role = $party->role instanceof \BackedEnum ? $party->role->value : $party->role;
            $inverted[] = [
                'role' => match ($role) {
                    'from' => 'to',
                    'to' => 'from',
                    default => $role,
                },
                'correspondent_id' => $party->correspondent_id,
                'name' => $party->name ?? $party->correspondent?->name,
                'function' => $party->function,
                'organization' => $party->organization ?? $party->correspondent?->organization,
            ];
        }

        $target->parties()->delete();
        $this->seedParties($target, $user, $inverted);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function extractPartiesInput(array $data): array
    {
        $parties = is_array($data['parties'] ?? null) ? array_values($data['parties']) : [];
        $roles = collect($parties)->pluck('role');

        $senderName = trim((string) ($data['sender_name'] ?? ''));
        $recipientName = trim((string) ($data['recipient_name'] ?? ''));

        if ($senderName !== '' && ! $roles->contains('from')) {
            $parties[] = ['role' => 'from', 'name' => $senderName];
        }
        if ($recipientName !== '' && ! $roles->contains('to')) {
            $parties[] = ['role' => 'to', 'name' => $recipientName];
        }

        unset($data['parties'], $data['sender_name'], $data['recipient_name'], $data['document_data']);

        return [$data, $parties];
    }

    /**
     * @param  list<array<string, mixed>>  $parties
     */
    private function seedParties(Correspondence $correspondence, User $user, array $parties): void
    {
        $roles = collect($parties)->pluck('role');
        $institution = $this->institutionName($user, $correspondence->structure_id);

        if ($correspondence->direction === CorrespondenceDirection::Entrant && ! $roles->contains('to')) {
            $parties[] = ['role' => 'to', 'name' => $institution];
        }

        if ($correspondence->direction === CorrespondenceDirection::Sortant && ! $roles->contains('from')) {
            $parties[] = ['role' => 'from', 'name' => $institution];
        }

        if ($parties === []) {
            return;
        }

        $this->attachParties($correspondence, $parties);
    }

    /**
     * @param  list<array<string, mixed>>  $parties
     */
    private function attachParties(Correspondence $correspondence, array $parties): void
    {
        $order = 0;
        foreach ($parties as $partyData) {
            if (empty($partyData['role'])) {
                continue;
            }

            $correspondence->parties()->create([
                'role' => $partyData['role'],
                'correspondent_id' => $partyData['correspondent_id'] ?? null,
                'name' => $partyData['name'] ?? null,
                'function' => $partyData['function'] ?? null,
                'organization' => $partyData['organization'] ?? null,
                'display_order' => $order++,
            ]);
        }
    }

    private function institutionName(User $user, mixed $structureId = null): string
    {
        $id = $structureId ?? $user->structure_id;
        if ($id) {
            $name = Structure::query()->whereKey($id)->value('name');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return 'DGTCP';
    }
}
