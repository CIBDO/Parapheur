<?php

namespace App\Services\Productions;

use App\Enums\NumberingSequenceCode;
use App\Enums\ProductionOccurrenceStatus;
use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmission;
use App\Models\ProductionTransmissionFile;
use App\Models\ProductionTransmissionRecipient;
use App\Models\User;
use App\Notifications\ProductionTransmissionNotification;
use App\Services\DelegationResolver;
use App\Services\NumberingService;
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

        $allowed = [
            ProductionOccurrenceStatus::Validee,
            ProductionOccurrenceStatus::PreteATransmettre,
            ProductionOccurrenceStatus::Transmise,
        ];

        if (! $rectificative && ! in_array($occurrence->status, $allowed, true)
            && $occurrence->status !== ProductionOccurrenceStatus::Deposee
            && $occurrence->obligation?->validation_required !== false) {
            // Allow transmit when validation not required and files deposited
            if (! ($occurrence->obligation?->validation_required === false
                && in_array($occurrence->status, [
                    ProductionOccurrenceStatus::Deposee,
                    ProductionOccurrenceStatus::PreteATransmettre,
                ], true))) {
                throw ValidationException::withMessages([
                    'status' => 'La production doit être validée avant transmission.',
                ]);
            }
        }

        if ($rectificative && $occurrence->status !== ProductionOccurrenceStatus::Transmise) {
            throw ValidationException::withMessages([
                'status' => 'Transmission rectificative uniquement après une première transmission.',
            ]);
        }

        $files = $occurrence->files()->with('documentVersion')->get();
        if ($files->isEmpty()) {
            throw ValidationException::withMessages(['files' => 'Aucun fichier à transmettre.']);
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

            $this->notifyRecipients($transmission, $occurrence);
            $this->occurrences->recordEvent($occurrence, $actor, $rectificative ? 'rectificative_transmitted' : 'transmitted', [
                'transmission_id' => $transmission->id,
                'recipients' => count($recipients),
            ]);

            return $this->occurrences->find($actor, $occurrence);
        });
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
                $recipient->user->notify(new ProductionTransmissionNotification($occurrence, $transmission));
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
