<?php

namespace App\Services;

use App\Enums\CorrespondenceDirection;
use App\Enums\CorrespondenceStatus;
use App\Enums\DocumentLinkRelation;
use App\Enums\DocumentOrigin;
use App\Enums\NumberingSequenceCode;
use App\Models\Correspondence;
use App\Models\CorrespondenceLink;
use App\Models\DocumentLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrespondenceReplyService
{
    public function __construct(
        private readonly CorrespondenceService $correspondenceService,
        private readonly CorrespondenceEventService $eventService,
        private readonly CorrespondenceStateMachine $stateMachine,
        private readonly NumberingService $numberingService,
        private readonly DocumentWorkflowService $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function prepareReply(Correspondence $original, User $user, array $extra = []): Correspondence
    {
        return DB::transaction(function () use ($original, $user, $extra) {
            $reply = $this->correspondenceService->createOutgoing($user, [
                'subject' => $extra['subject'] ?? ('Réponse : '.$original->subject),
                'summary' => $extra['summary'] ?? null,
                'medium' => $extra['medium'] ?? $original->medium?->value ?? 'hybride',
                'priority' => $extra['priority'] ?? $original->priority?->value,
                'confidentiality' => $extra['confidentiality'] ?? $original->confidentiality?->value,
                'structure_id' => $extra['structure_id'] ?? $original->structure_id ?? $user->structure_id,
                'channel_id' => $extra['channel_id'] ?? $original->channel_id,
                'category_id' => $extra['category_id'] ?? $original->category_id,
                'requires_reply' => false,
                'reply_to_correspondence_id' => $original->id,
                'correspondence_date' => now()->toDateString(),
            ]);

            CorrespondenceLink::query()->create([
                'source_correspondence_id' => $reply->id,
                'target_correspondence_id' => $original->id,
                'link_type' => 'reponse',
                'created_by' => $user->id,
            ]);

            if ($original->document_id && $reply->document_id) {
                DocumentLink::query()->firstOrCreate([
                    'source_document_id' => $reply->document_id,
                    'target_document_id' => $original->document_id,
                    'relation_type' => DocumentLinkRelation::ResponseTo->value,
                ], [
                    'created_by' => $user->id,
                    'note' => 'Réponse courrier',
                ]);
            }

            $this->eventService->logEvent($original, 'reply_prepared', $user, metadata: [
                'reply_id' => $reply->id,
            ]);

            $this->audit->log('correspondence.reply_prepared', $original, [
                'actor_id' => $user->id,
                'reply_id' => $reply->id,
            ]);

            $this->correspondenceService->copyInvertedParties($original, $reply, $user);

            // Notifier l'enregistreur du courrier original
            if ($original->registeredBy && $original->registeredBy->id !== $user->id) {
                $original->registeredBy->notify(new \App\Notifications\MailCorrespondenceNotification(
                    correspondence: $original,
                    event: 'reply_prepared',
                    message: 'Un projet de réponse a été créé pour ce courrier.',
                    actorName: $user->name
                ));
            }

            return $reply->fresh(['outgoingLinks', 'incomingLinks', 'parties']);
        });
    }

    public function submitToParapheur(Correspondence $correspondence, User $user, array $workflowData = []): Correspondence
    {
        return DB::transaction(function () use ($correspondence, $user, $workflowData) {
            if ($correspondence->direction !== CorrespondenceDirection::Sortant) {
                throw new InvalidArgumentException('Seuls les courriers sortants peuvent être soumis au parapheur.');
            }

            if (! $correspondence->document_id) {
                $document = $this->workflow->createDraft($user, [
                    'origin' => DocumentOrigin::Courrier->value,
                    'object' => $correspondence->subject,
                    'summary' => $correspondence->summary,
                    'structure_id' => $correspondence->structure_id,
                    'priority' => $correspondence->priority?->value,
                    'confidentiality' => $correspondence->confidentiality?->value,
                ]);
                $correspondence->document_id = $document->id;
                $correspondence->parapheur_document_id = $document->id;
                $correspondence->save();
            } else {
                $correspondence->parapheur_document_id = $correspondence->document_id;
                $correspondence->save();
            }

            $document = $correspondence->document()->firstOrFail();

            if (! empty($workflowData['to_user_id']) || ! empty($workflowData['assignees'])) {
                $this->workflow->submitAndTransmit($document, $user, $workflowData);
            }

            if ($this->stateMachine->canTransition($correspondence->status, CorrespondenceStatus::AViser)) {
                $from = $correspondence->status->value;
                $correspondence->status = CorrespondenceStatus::AViser;
                $correspondence->save();
                $this->eventService->logStatusTransition($correspondence, $from, CorrespondenceStatus::AViser->value, $user, 'Soumis au parapheur');
            }

            return $correspondence->fresh(['document']);
        });
    }

    public function assignDepartureNumber(Correspondence $correspondence, User $user): Correspondence
    {
        return DB::transaction(function () use ($correspondence, $user) {
            if ($correspondence->direction !== CorrespondenceDirection::Sortant) {
                throw new InvalidArgumentException('Le numéro de départ concerne les courriers sortants.');
            }

            if ($correspondence->departure_number) {
                return $correspondence;
            }

            $number = $this->numberingService->nextNumber(
                NumberingSequenceCode::Departure,
                structureId: $correspondence->structure_id
            );

            $correspondence->departure_number = $number;
            $correspondence->is_registered = true;
            $correspondence->registered_at = $correspondence->registered_at ?? now();

            if (in_array($correspondence->status, [CorrespondenceStatus::Valide, CorrespondenceStatus::Signe], true)
                && $this->stateMachine->canTransition($correspondence->status, CorrespondenceStatus::AExpedier)) {
                $from = $correspondence->status->value;
                $correspondence->status = CorrespondenceStatus::AExpedier;
                $this->eventService->logStatusTransition($correspondence, $from, CorrespondenceStatus::AExpedier->value, $user);
            }

            $correspondence->save();

            $this->eventService->logEvent($correspondence, 'departure_number_assigned', $user, metadata: [
                'departure_number' => $number,
            ]);

            return $correspondence;
        });
    }
}
