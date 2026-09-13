<?php

namespace App\Services;

use App\Models\Correspondence;
use App\Models\CorrespondenceEvent;
use App\Models\User;

class CorrespondenceEventService
{
    /**
     * Journalise un événement dans l'historique (append-only).
     */
    public function logEvent(
        Correspondence $correspondence,
        string $eventType,
        ?User $user = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?array $metadata = null,
        ?string $comment = null
    ): CorrespondenceEvent {
        return CorrespondenceEvent::query()->create([
            'correspondence_id' => $correspondence->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'metadata' => $metadata,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }

    /**
     * Journalise une transition de statut.
     */
    public function logStatusTransition(
        Correspondence $correspondence,
        string $oldStatus,
        string $newStatus,
        ?User $user = null,
        ?string $comment = null
    ): CorrespondenceEvent {
        return $this->logEvent(
            correspondence: $correspondence,
            eventType: 'status_changed',
            user: $user,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            comment: $comment
        );
    }

    /**
     * Journalise une affectation.
     */
    public function logAssignment(
        Correspondence $correspondence,
        ?User $fromUser,
        ?User $toUser,
        ?int $toStructureId,
        ?string $comment = null
    ): CorrespondenceEvent {
        return $this->logEvent(
            correspondence: $correspondence,
            eventType: 'assigned',
            user: $fromUser,
            metadata: [
                'to_user_id' => $toUser?->id,
                'to_structure_id' => $toStructureId,
            ],
            comment: $comment
        );
    }

    /**
     * Journalise une prise en charge.
     */
    public function logTakeCharge(Correspondence $correspondence, User $user): CorrespondenceEvent
    {
        return $this->logEvent(
            correspondence: $correspondence,
            eventType: 'taken_charge',
            user: $user
        );
    }
}
