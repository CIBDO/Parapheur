<?php

namespace App\Services\Productions;

use App\Enums\ProductionOccurrenceStatus;
use App\Models\ProductionOccurrence;
use App\Models\ProductionValidation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionValidationService
{
    public function __construct(
        private readonly ProductionOccurrenceService $occurrences,
    ) {}

    private function transmissions(): ProductionTransmissionService
    {
        return app(ProductionTransmissionService::class);
    }

    public function submit(User $actor, ProductionOccurrence $occurrence): ProductionOccurrence
    {
        $this->occurrences->assertCanUpdate($actor, $occurrence);
        $obligation = $occurrence->obligation;

        $fileCount = $occurrence->files()->count();
        $min = (int) ($obligation?->min_files ?? 1);
        if ($fileCount < $min) {
            throw ValidationException::withMessages([
                'files' => "Au moins {$min} fichier(s) requis.",
            ]);
        }

        return DB::transaction(function () use ($actor, $occurrence, $obligation) {
            if (! $obligation?->validation_required) {
                $occurrence->status = ProductionOccurrenceStatus::PreteATransmettre;
                $occurrence->submitted_at = now();
                $occurrence->validated_at = now();
                $occurrence->save();
                $this->occurrences->recordEvent($occurrence, $actor, 'submitted_without_validation');

                if ($obligation->auto_transmit) {
                    return $this->transmissions()->transmit($actor, $occurrence);
                }

                return $this->occurrences->find($actor, $occurrence);
            }

            $occurrence->status = ProductionOccurrenceStatus::Soumise;
            $occurrence->submitted_at = now();
            $occurrence->save();

            $validators = $obligation->validators()->get();
            if ($validators->isEmpty() && $obligation->producer_id) {
                ProductionValidation::query()->create([
                    'production_occurrence_id' => $occurrence->id,
                    'step_order' => 1,
                    'validator_id' => $obligation->deputy_id ?: $obligation->producer_id,
                    'decision' => 'pending',
                ]);
            } else {
                foreach ($validators as $index => $validator) {
                    ProductionValidation::query()->create([
                        'production_occurrence_id' => $occurrence->id,
                        'step_order' => $validator->step_order ?: ($index + 1),
                        'validator_id' => $validator->user_id,
                        'decision' => 'pending',
                    ]);
                }
            }

            $occurrence->status = ProductionOccurrenceStatus::EnValidation;
            $occurrence->save();
            $this->occurrences->recordEvent($occurrence, $actor, 'submitted_for_validation');

            return $this->occurrences->find($actor, $occurrence);
        });
    }

    public function decide(
        User $actor,
        ProductionOccurrence $occurrence,
        string $decision,
        ?string $motif = null,
        ?string $comment = null,
    ): ProductionOccurrence {
        if (! in_array($decision, ['approved', 'rejected', 'correction'], true)) {
            throw ValidationException::withMessages(['decision' => 'Décision invalide.']);
        }

        $pending = $occurrence->validations()
            ->where('decision', 'pending')
            ->orderBy('step_order')
            ->get();

        $mine = $pending->first(fn ($v) => (int) $v->validator_id === (int) $actor->id);
        if (! $mine && ! $actor->can('production.manage') && ! $actor->can('admin.access')) {
            abort(403, 'Vous n\'êtes pas validateur de cette production.');
        }

        if (! $mine) {
            $mine = $pending->first();
        }

        if (! $mine) {
            throw ValidationException::withMessages(['decision' => 'Aucune validation en attente.']);
        }

        return DB::transaction(function () use ($actor, $occurrence, $decision, $motif, $comment, $mine) {
            $mine->decision = $decision;
            $mine->motif = $motif;
            $mine->comment = $comment;
            $mine->decided_at = now();
            $mine->validator_id = $mine->validator_id ?: $actor->id;
            $mine->save();

            if ($decision === 'rejected' || $decision === 'correction') {
                $occurrence->status = $decision === 'rejected'
                    ? ProductionOccurrenceStatus::Rejetee
                    : ProductionOccurrenceStatus::ACompleter;
                $occurrence->save();
                $this->occurrences->recordEvent($occurrence, $actor, 'validation_'.$decision, [
                    'motif' => $motif,
                ]);

                return $this->occurrences->find($actor, $occurrence);
            }

            $mode = $occurrence->obligation?->validation_mode ?? 'sequential';
            $stillPending = $occurrence->validations()->where('decision', 'pending')->exists();

            if ($mode === 'parallel') {
                $allApproved = ! $occurrence->validations()->where('decision', '!=', 'approved')->exists();
                if ($allApproved) {
                    return $this->markValidated($actor, $occurrence);
                }
            } else {
                if (! $stillPending) {
                    return $this->markValidated($actor, $occurrence);
                }
            }

            $this->occurrences->recordEvent($occurrence, $actor, 'validation_step_approved');

            return $this->occurrences->find($actor, $occurrence);
        });
    }

    private function markValidated(User $actor, ProductionOccurrence $occurrence): ProductionOccurrence
    {
        $occurrence->status = ProductionOccurrenceStatus::Validee;
        $occurrence->validated_at = now();
        $occurrence->save();
        $this->occurrences->recordEvent($occurrence, $actor, 'validated');

        if ($occurrence->obligation?->auto_transmit) {
            $occurrence->status = ProductionOccurrenceStatus::PreteATransmettre;
            $occurrence->save();

            return $this->transmissions()->transmit($actor, $occurrence);
        }

        $occurrence->status = ProductionOccurrenceStatus::PreteATransmettre;
        $occurrence->save();

        return $this->occurrences->find($actor, $occurrence);
    }
}
