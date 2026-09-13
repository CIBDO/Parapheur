<?php

namespace App\Services;

use App\Enums\CorrespondenceDirection;
use App\Enums\CorrespondenceStatus;
use App\Enums\DocumentAttachmentKind;
use App\Enums\NumberingSequenceCode;
use App\Models\Correspondence;
use App\Models\CorrespondenceAcknowledgement;
use App\Models\CorrespondenceDispatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CorrespondenceDispatchService
{
    public function __construct(
        private readonly CorrespondenceEventService $eventService,
        private readonly CorrespondenceStateMachine $stateMachine,
        private readonly DocumentService $documentService,
        private readonly AuditLogger $audit,
    ) {}

    public function recordDispatch(Correspondence $correspondence, User $user, array $data): CorrespondenceDispatch
    {
        return DB::transaction(function () use ($correspondence, $user, $data) {
            // Sortant : numéro de départ requis avant (ou lors de) l'expédition
            if ($correspondence->direction === CorrespondenceDirection::Sortant
                && ! $correspondence->departure_number) {
                $number = app(NumberingService::class)->nextNumber(
                    NumberingSequenceCode::Departure,
                    structureId: $correspondence->structure_id
                );
                $correspondence->departure_number = $number;
                $correspondence->is_registered = true;
                $correspondence->registered_at = $correspondence->registered_at ?? now();
                $correspondence->save();
                $this->eventService->logEvent($correspondence, 'departure_number_assigned', $user, metadata: [
                    'departure_number' => $number,
                    'via' => 'dispatch',
                ]);
            }

            $dispatch = CorrespondenceDispatch::query()->create([
                'correspondence_id' => $correspondence->id,
                'dispatched_at' => $data['dispatched_at'] ?? now(),
                'method' => $data['method'] ?? 'courrier',
                'tracking_number' => $data['tracking_number'] ?? null,
                'dispatched_by' => $user->id,
                'observations' => $data['observations'] ?? null,
            ]);

            if ($correspondence->status !== CorrespondenceStatus::Expedie
                && $this->stateMachine->canTransition($correspondence->status, CorrespondenceStatus::Expedie)) {
                $from = $correspondence->status->value;
                $correspondence->status = CorrespondenceStatus::Expedie;
                $correspondence->save();
                $this->eventService->logStatusTransition($correspondence, $from, CorrespondenceStatus::Expedie->value, $user);
            }

            $this->eventService->logEvent($correspondence, 'dispatched', $user, metadata: [
                'dispatch_id' => $dispatch->id,
                'method' => $dispatch->method,
            ]);

            $this->audit->log('correspondence.dispatched', $correspondence, [
                'actor_id' => $user->id,
                'dispatch_id' => $dispatch->id,
            ]);

            // Notifier l'enregistreur du courrier
            if ($correspondence->registeredBy && $correspondence->registeredBy->id !== $user->id) {
                $correspondence->registeredBy->notify(new \App\Notifications\MailCorrespondenceNotification(
                    correspondence: $correspondence,
                    event: 'dispatched',
                    message: 'Le courrier a été expédié.',
                    actorName: $user->name
                ));
            }

            return $dispatch;
        });
    }

    public function recordAcknowledgement(
        Correspondence $correspondence,
        User $user,
        array $data = [],
        ?UploadedFile $proof = null,
    ): CorrespondenceAcknowledgement {
        return DB::transaction(function () use ($correspondence, $user, $data, $proof) {
            $ack = CorrespondenceAcknowledgement::query()->create([
                'correspondence_id' => $correspondence->id,
                'acknowledged_at' => $data['acknowledged_at'] ?? now(),
                'acknowledged_by_name' => $data['acknowledged_by_name'] ?? null,
                'method' => $data['method'] ?? 'signature',
                'observations' => $data['observations'] ?? null,
                'registered_by' => $user->id,
            ]);

            if ($proof && $correspondence->document_id) {
                $this->documentService->addAttachment(
                    $correspondence->document,
                    $user,
                    $proof,
                    DocumentAttachmentKind::Complement->value
                );
            }

            if ($correspondence->status !== CorrespondenceStatus::AccuseRecu
                && $this->stateMachine->canTransition($correspondence->status, CorrespondenceStatus::AccuseRecu)) {
                $from = $correspondence->status->value;
                $correspondence->status = CorrespondenceStatus::AccuseRecu;
                $correspondence->save();
                $this->eventService->logStatusTransition($correspondence, $from, CorrespondenceStatus::AccuseRecu->value, $user);
            }

            $this->eventService->logEvent($correspondence, 'acknowledgement_received', $user, metadata: [
                'acknowledgement_id' => $ack->id,
            ]);

            return $ack;
        });
    }

    public function archive(Correspondence $correspondence, User $user): Correspondence
    {
        return DB::transaction(function () use ($correspondence, $user) {
            $this->stateMachine->assertCanTransition($correspondence->status, CorrespondenceStatus::Archive);
            $from = $correspondence->status->value;
            $correspondence->status = CorrespondenceStatus::Archive;
            $correspondence->save();

            $this->eventService->logStatusTransition($correspondence, $from, CorrespondenceStatus::Archive->value, $user);
            $this->audit->log('correspondence.archived', $correspondence, ['actor_id' => $user->id]);

            return $correspondence;
        });
    }
}
