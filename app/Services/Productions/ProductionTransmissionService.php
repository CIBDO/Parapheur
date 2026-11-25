<?php

namespace App\Services\Productions;

use App\Enums\NumberingSequenceCode;
use App\Enums\ProductionOccurrenceStatus;
use App\Enums\TaskStatus;
use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmission;
use App\Models\ProductionTransmissionFile;
use App\Models\ProductionTransmissionRecipient;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProductionOpinionNotification;
use App\Notifications\ProductionTransmissionNotification;
use App\Services\DelegationResolver;
use App\Services\NumberingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionTransmissionService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly ProductionOccurrenceService $occurrences,
        private readonly DelegationResolver $delegations,
    ) {}

    public function transmit(
        User $actor,
        ProductionOccurrence $occurrence,
        bool $rectificative = false,
        ?int $previousTransmissionId = null,
    ): ProductionOccurrence {
        $this->occurrences->assertCanUpdate($actor, $occurrence);
        $this->assertCanTransmit($occurrence, $rectificative);

        $files = $occurrence->files()->with('documentVersion')->get();
        if ($files->isEmpty()) {
            throw ValidationException::withMessages(['files' => 'Aucun fichier à transmettre. Déposez d’abord le document.']);
        }

        return DB::transaction(function () use ($actor, $occurrence, $rectificative, $previousTransmissionId, $files) {
            $transmission = ProductionTransmission::query()->create([
                'reference' => $this->numbering->nextNumber(NumberingSequenceCode::ProductionTransmission),
                'production_occurrence_id' => $occurrence->id,
                'transmitted_by' => $actor->id,
                'channel' => 'app',
                'is_rectificative' => $rectificative,
                'previous_transmission_id' => $previousTransmissionId
                    ?? $occurrence->transmissions()->latest('id')->value('id'),
                'status' => 'sent',
                'transmitted_at' => now(),
            ]);

            foreach ($files as $file) {
                ProductionTransmissionFile::query()->create([
                    'production_transmission_id' => $transmission->id,
                    'production_file_id' => $file->id,
                    'document_version_id' => $file->document_version_id,
                ]);
                if (! $file->transmitted_at) {
                    $file->transmitted_at = now();
                    $file->status = 'transmitted';
                    $file->save();
                }
            }

            $recipients = $this->resolveRecipients($occurrence);
            if ($recipients === []) {
                throw ValidationException::withMessages([
                    'recipients' => 'Aucun destinataire configuré sur ce modèle d’envoi.',
                ]);
            }

            foreach ($recipients as $recipient) {
                ProductionTransmissionRecipient::query()->create([
                    'production_transmission_id' => $transmission->id,
                    'user_id' => $recipient['user_id'],
                    'structure_id' => $recipient['structure_id'] ?? null,
                    'resolution_source' => $recipient['source'] ?? 'obligation',
                    'delivery_status' => 'pending',
                ]);
            }

            $occurrence->status = ProductionOccurrenceStatus::Transmise;
            $occurrence->transmitted_at = now();
            $occurrence->save();

            $this->syncLinkedTask($occurrence);
            $this->notifyRecipients($transmission, $occurrence);
            $this->occurrences->recordEvent($occurrence, $actor, $rectificative ? 'rectificative_transmitted' : 'transmitted', [
                'transmission_id' => $transmission->id,
                'recipients' => count($recipients),
            ]);

            return $this->occurrences->find($actor, $occurrence);
        });
    }

    /**
     * Boîte « Reçues » : transmissions adressées à l’utilisateur.
     *
     * @param  array<string, mixed>  $filters
     */
    public function inbox(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = ProductionTransmission::query()
            ->with([
                'occurrence.obligation:id,code,label',
                'occurrence.structure:id,code,name',
                'occurrence.responsible:id,name',
                'transmitter:id,name',
                'files.productionFile.document:id,object,title',
                'recipients' => fn ($q) => $q->where('user_id', $actor->id),
            ])
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $actor->id))
            ->orderByDesc('transmitted_at');

        if (! empty($filters['unread'])) {
            $query->whereHas('recipients', fn ($q) => $q
                ->where('user_id', $actor->id)
                ->whereNull('viewed_at'));
        }

        if (! empty($filters['today'])) {
            $query->whereDate('transmitted_at', now()->toDateString());
        }

        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('reference', 'like', $q)
                    ->orWhereHas('occurrence', fn ($oq) => $oq
                        ->where('reference', 'like', $q)
                        ->orWhereHas('obligation', fn ($ob) => $ob
                            ->where('label', 'like', $q)
                            ->orWhere('code', 'like', $q)));
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function recordOpinion(
        User $actor,
        ProductionTransmission $transmission,
        string $opinion,
        ?string $comment = null,
    ): ProductionTransmissionRecipient {
        $recipient = $transmission->recipients()->where('user_id', $actor->id)->first();
        if (! $recipient) {
            abort(403, 'Cette transmission ne vous est pas adressée.');
        }

        if (! in_array($opinion, ['favorable', 'reserve', 'a_revoir'], true)) {
            throw ValidationException::withMessages(['opinion' => 'Avis invalide.']);
        }

        $previousOpinion = $recipient->opinion;
        $hadOpinion = $recipient->opinion_at !== null;

        $recipient->opinion = $opinion;
        $recipient->opinion_comment = $comment;
        $recipient->opinion_at = now();
        if (! $recipient->viewed_at) {
            $recipient->viewed_at = now();
        }
        $recipient->save();

        $transmission->loadMissing('occurrence.obligation.producer', 'occurrence.responsible');
        $occurrence = $transmission->occurrence;
        $notifyUser = $occurrence?->responsible ?? $occurrence?->obligation?->producer;
        // Un mail seulement au premier avis ou si l’avis change (évite le spam au re-POST).
        $shouldMail = ! $hadOpinion || $previousOpinion !== $opinion;
        if ($shouldMail && $notifyUser && (int) $notifyUser->id !== (int) $actor->id) {
            $notifyUser->notify(new ProductionOpinionNotification(
                $occurrence,
                $transmission,
                $opinion,
                $actor->name,
                $comment,
            ));
        }

        return $recipient->fresh(['user:id,name']);
    }

    private function assertCanTransmit(ProductionOccurrence $occurrence, bool $rectificative): void
    {
        if ($rectificative) {
            if ($occurrence->status !== ProductionOccurrenceStatus::Transmise) {
                throw ValidationException::withMessages([
                    'status' => 'Transmission rectificative uniquement après une première transmission.',
                ]);
            }

            return;
        }

        $validationRequired = (bool) ($occurrence->obligation?->validation_required);

        if (! $validationRequired) {
            $allowed = [
                ProductionOccurrenceStatus::AProduire,
                ProductionOccurrenceStatus::Deposee,
                ProductionOccurrenceStatus::EnPreparation,
                ProductionOccurrenceStatus::ACompleter,
                ProductionOccurrenceStatus::Rejetee,
                ProductionOccurrenceStatus::PreteATransmettre,
                ProductionOccurrenceStatus::Validee,
            ];
            if (! in_array($occurrence->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Déposez d’abord le document avant de l’envoyer.',
                ]);
            }

            return;
        }

        $allowed = [
            ProductionOccurrenceStatus::Validee,
            ProductionOccurrenceStatus::PreteATransmettre,
        ];
        if (! in_array($occurrence->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'La production doit être validée avant transmission.',
            ]);
        }
    }

    private function syncLinkedTask(ProductionOccurrence $occurrence): void
    {
        if (! $occurrence->task_id) {
            return;
        }

        $task = Task::query()->find($occurrence->task_id);
        if (! $task || in_array($task->status, [TaskStatus::Validee, TaskStatus::Annulee], true)) {
            return;
        }

        $task->status = TaskStatus::Validee;
        $task->progress = 100;
        $task->completed_at = $task->completed_at ?? now();
        $task->validated_at = now();
        $task->save();
    }

    /**
     * @return list<array{user_id: int|null, structure_id: int|null, source: string}>
     */
    private function resolveRecipients(ProductionOccurrence $occurrence): array
    {
        $rows = [];
        $actors = $occurrence->obligation?->recipients()->with('user')->get() ?? collect();

        foreach ($actors as $actor) {
            if ($actor->user_id) {
                $rows[$actor->user_id] = [
                    'user_id' => $actor->user_id,
                    'structure_id' => $actor->structure_id,
                    'source' => 'user',
                ];
            } elseif ($actor->structure_id) {
                User::query()
                    ->where('structure_id', $actor->structure_id)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->each(function ($userId) use (&$rows, $actor) {
                        $rows[$userId] = [
                            'user_id' => $userId,
                            'structure_id' => $actor->structure_id,
                            'source' => 'structure',
                        ];
                    });
            } elseif ($actor->role_name) {
                $users = User::role($actor->role_name)->where('is_active', true)->pluck('id');
                foreach ($users as $userId) {
                    $rows[$userId] = [
                        'user_id' => $userId,
                        'structure_id' => null,
                        'source' => 'role:'.$actor->role_name,
                    ];
                }
            }
        }

        $expanded = $rows;
        foreach ($rows as $row) {
            if (empty($row['user_id'])) {
                continue;
            }
            $delegator = User::query()->find($row['user_id']);
            if (! $delegator) {
                continue;
            }
            foreach ($this->delegations->activeDelegates($delegator, 'consultation') as $delegate) {
                if (isset($expanded[$delegate->id])) {
                    continue;
                }
                $expanded[$delegate->id] = [
                    'user_id' => $delegate->id,
                    'structure_id' => $delegate->structure_id,
                    'source' => 'delegation:'.$delegator->id,
                ];
            }
        }

        return array_values($expanded);
    }

    private function notifyRecipients(ProductionTransmission $transmission, ProductionOccurrence $occurrence): void
    {
        $transmission->load('recipients.user');
        foreach ($transmission->recipients as $recipient) {
            if (! $recipient->user) {
                continue;
            }
            try {
                $recipient->user->notify(new ProductionTransmissionNotification(
                    $occurrence,
                    $transmission,
                    str_starts_with((string) $recipient->resolution_source, 'delegation:') ? 'delegate' : 'recipient',
                ));
                $recipient->delivery_status = 'notified';
                $recipient->notified_at = now();
                $recipient->save();
            } catch (\Throwable) {
                $recipient->delivery_status = 'failed';
                $recipient->save();
            }
        }
    }
}
