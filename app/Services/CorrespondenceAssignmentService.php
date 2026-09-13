<?php

namespace App\Services;

use App\Enums\CorrespondenceAssignmentStatus;
use App\Enums\CorrespondenceStatus;
use App\Models\Correspondence;
use App\Models\CorrespondenceAssignment;
use App\Models\Instruction;
use App\Models\User;
use App\Notifications\MailCorrespondenceNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrespondenceAssignmentService
{
    public function __construct(
        private readonly CorrespondenceEventService $eventService,
        private readonly CorrespondenceStateMachine $stateMachine,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Affecte la correspondance à un ou plusieurs utilisateurs/structures.
     *
     * @param  array<array{to_user_id?: int, to_structure_id?: int, action_id?: int, instruction_text?: string, due_date?: string}>  $assignments
     * @return array<CorrespondenceAssignment>
     */
    public function assign(Correspondence $correspondence, User $fromUser, array $assignments): array
    {
        if (empty($assignments)) {
            throw new InvalidArgumentException('Aucune affectation fournie.');
        }

        return DB::transaction(function () use ($correspondence, $fromUser, $assignments) {
            $created = [];

            foreach ($assignments as $assignmentData) {
                $toUserId = $assignmentData['to_user_id'] ?? null;
                $toStructureId = $assignmentData['to_structure_id'] ?? null;

                if (! $toUserId && ! $toStructureId) {
                    throw new InvalidArgumentException('Chaque affectation doit spécifier un utilisateur ou une structure.');
                }

                $assignment = CorrespondenceAssignment::query()->create([
                    'correspondence_id' => $correspondence->id,
                    'from_user_id' => $fromUser->id,
                    'to_user_id' => $toUserId,
                    'to_structure_id' => $toStructureId,
                    'action_id' => $assignmentData['action_id'] ?? null,
                    'instruction_text' => $assignmentData['instruction_text'] ?? null,
                    'due_date' => $assignmentData['due_date'] ?? null,
                    'status' => CorrespondenceAssignmentStatus::Transmis,
                ]);

                // Créer une Instruction si du texte d'instruction est fourni
                if (! empty($assignmentData['instruction_text']) && $toUserId) {
                    $instruction = Instruction::query()->create([
                        'issuer_id' => $fromUser->id,
                        'assignee_id' => $toUserId,
                        'structure_id' => $toStructureId,
                        'title' => 'Instruction pour courrier : '.$correspondence->subject,
                        'body' => $assignmentData['instruction_text'],
                        'priority' => $correspondence->priority?->value ?? 'normale',
                        'status' => 'a_faire',
                        'due_date' => $assignmentData['due_date'] ?? null,
                    ]);

                    $assignment->instruction_id = $instruction->id;
                    $assignment->save();
                }

                $created[] = $assignment;

                $toUser = $toUserId ? User::query()->find($toUserId) : null;

                $this->eventService->logAssignment(
                    $correspondence,
                    $fromUser,
                    $toUser,
                    $toStructureId,
                    $assignmentData['instruction_text'] ?? null
                );

                if ($toUser) {
                    $toUser->notify(new MailCorrespondenceNotification(
                        $correspondence,
                        'assigned',
                        'Un courrier vous a été affecté : '.$correspondence->subject,
                        $fromUser->name,
                    ));
                }
            }

            // Mettre à jour le statut de la correspondance si besoin
            if ($correspondence->status === CorrespondenceStatus::Enregistre || $correspondence->status === CorrespondenceStatus::AAffecter) {
                $oldStatus = $correspondence->status->value;
                $correspondence->status = CorrespondenceStatus::Affecte;
                $correspondence->save();

                $this->eventService->logStatusTransition($correspondence, $oldStatus, CorrespondenceStatus::Affecte->value, $fromUser);
            }

            $this->audit->log('correspondence.assigned', $correspondence, [
                'actor_id' => $fromUser->id,
                'assignments_count' => count($created),
            ]);

            return $created;
        });
    }

    /**
     * Prendre en charge une affectation.
     */
    public function takeCharge(CorrespondenceAssignment $assignment, User $user): CorrespondenceAssignment
    {
        if ($assignment->to_user_id !== $user->id) {
            throw new InvalidArgumentException('Vous ne pouvez prendre en charge que vos propres affectations.');
        }

        if ($assignment->status !== CorrespondenceAssignmentStatus::Transmis && $assignment->status !== CorrespondenceAssignmentStatus::Recu) {
            throw new InvalidArgumentException('Cette affectation a déjà été prise en charge ou est clôturée.');
        }

        DB::transaction(function () use ($assignment, $user) {
            $assignment->status = CorrespondenceAssignmentStatus::PrisEnCharge;
            $assignment->taken_charge_at = now();
            $assignment->save();

            $correspondence = $assignment->correspondence;
            $oldStatus = $correspondence->status->value;

            if ($correspondence->status === CorrespondenceStatus::Affecte) {
                $correspondence->status = CorrespondenceStatus::PrisEnCharge;
                $correspondence->save();

                $this->eventService->logStatusTransition($correspondence, $oldStatus, CorrespondenceStatus::PrisEnCharge->value, $user);
            }

            $this->eventService->logTakeCharge($correspondence, $user);
            $this->audit->log('correspondence.assignment_taken_charge', $correspondence, ['actor_id' => $user->id, 'assignment_id' => $assignment->id]);

            // Notifier l'assigneur (celui qui a créé l'affectation)
            if ($assignment->fromUser && $assignment->fromUser->id !== $user->id) {
                $assignment->fromUser->notify(new MailCorrespondenceNotification(
                    correspondence: $correspondence,
                    event: 'taken_charge',
                    message: 'L\'affectation a été prise en charge.',
                    actorName: $user->name
                ));
            }
        });

        return $assignment->fresh();
    }

    /**
     * Réaffecter (transférer) à un autre utilisateur.
     */
    public function reassign(CorrespondenceAssignment $assignment, User $fromUser, int $toUserId, ?string $observation = null): CorrespondenceAssignment
    {
        if ($assignment->to_user_id !== $fromUser->id && ! $fromUser->can('mail.assign')) {
            throw new InvalidArgumentException('Vous ne pouvez réaffecter que vos propres affectations ou avoir la permission mail.assign.');
        }

        return DB::transaction(function () use ($assignment, $fromUser, $toUserId, $observation) {
            // Marquer l'ancienne affectation comme réorientée
            $assignment->status = CorrespondenceAssignmentStatus::Reoriente;
            $assignment->processed_at = now();
            $assignment->observation = $observation;
            $assignment->save();

            // Créer une nouvelle affectation
            $newAssignment = CorrespondenceAssignment::query()->create([
                'correspondence_id' => $assignment->correspondence_id,
                'from_user_id' => $fromUser->id,
                'to_user_id' => $toUserId,
                'to_structure_id' => null,
                'action_id' => $assignment->action_id,
                'instruction_text' => $assignment->instruction_text,
                'due_date' => $assignment->due_date,
                'status' => CorrespondenceAssignmentStatus::Transmis,
            ]);

            $this->eventService->logEvent(
                $assignment->correspondence,
                'reassigned',
                $fromUser,
                metadata: ['old_to_user_id' => $assignment->to_user_id, 'new_to_user_id' => $toUserId],
                comment: $observation
            );

            $this->audit->log('correspondence.reassigned', $assignment->correspondence, [
                'actor_id' => $fromUser->id,
                'from_assignment_id' => $assignment->id,
                'to_assignment_id' => $newAssignment->id,
            ]);

            return $newAssignment;
        });
    }

    /**
     * Retourner l'affectation (marquer comme traitée + retournée).
     */
    public function returnAssignment(CorrespondenceAssignment $assignment, User $user, ?string $observation = null): CorrespondenceAssignment
    {
        if ($assignment->to_user_id !== $user->id) {
            throw new InvalidArgumentException('Vous ne pouvez retourner que vos propres affectations.');
        }

        DB::transaction(function () use ($assignment, $user, $observation) {
            $assignment->status = CorrespondenceAssignmentStatus::Retourne;
            $assignment->processed_at = now();
            $assignment->observation = $observation;
            $assignment->save();

            $this->eventService->logEvent(
                $assignment->correspondence,
                'assignment_returned',
                $user,
                comment: $observation
            );

            $this->audit->log('correspondence.assignment_returned', $assignment->correspondence, [
                'actor_id' => $user->id,
                'assignment_id' => $assignment->id,
            ]);
        });

        return $assignment->fresh();
    }

    /**
     * Demander un complément d'information.
     */
    public function requestComplement(CorrespondenceAssignment $assignment, User $user, string $observation): CorrespondenceAssignment
    {
        if ($assignment->to_user_id !== $user->id) {
            throw new InvalidArgumentException('Vous ne pouvez demander un complément que sur vos propres affectations.');
        }

        DB::transaction(function () use ($assignment, $user, $observation) {
            $correspondence = $assignment->correspondence;
            $oldStatus = $correspondence->status->value;

            $correspondence->status = CorrespondenceStatus::EnAttenteComplement;
            $correspondence->save();

            $this->eventService->logStatusTransition($correspondence, $oldStatus, CorrespondenceStatus::EnAttenteComplement->value, $user, $observation);
            $this->audit->log('correspondence.complement_requested', $correspondence, ['actor_id' => $user->id, 'assignment_id' => $assignment->id]);
        });

        return $assignment->fresh();
    }
}
