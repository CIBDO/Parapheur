<?php

namespace App\Services\Productions;

use App\Enums\NumberingSequenceCode;
use App\Enums\ProductionOccurrenceStatus;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\DocumentView;
use App\Models\ProductionEvent;
use App\Models\ProductionGenerationRun;
use App\Models\ProductionObligation;
use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmissionRecipient;
use App\Models\TaskType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NumberingService;
use App\Services\Tasks\TaskService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProductionOccurrenceService
{
    public function __construct(
        private readonly RecurrenceService $recurrence,
        private readonly NumberingService $numbering,
        private readonly TaskService $tasks,
        private readonly AuditLogger $audit,
        private readonly ProductionFolderService $folders,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = ProductionOccurrence::query()
            ->with([
                'obligation:id,code,label,structure_id,validation_required,auto_transmit',
                'responsible:id,name',
                'structure:id,code,name',
                'task:id,reference,status,due_at',
            ]);

        if (! $actor->can('production.manage') && ! $actor->can('production.view_all') && ! $actor->can('admin.access')) {
            $query->where(function ($q) use ($actor) {
                $q->where('responsible_id', $actor->id)
                    ->orWhere('structure_id', $actor->structure_id)
                    ->orWhereHas('obligation.actors', fn ($a) => $a->where('user_id', $actor->id));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['obligation_id'])) {
            $query->where('production_obligation_id', $filters['obligation_id']);
        }
        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', $filters['structure_id']);
        }
        if (! empty($filters['mine'])) {
            $query->where('responsible_id', $actor->id);
        }
        if (! empty($filters['overdue'])) {
            $query->whereNotNull('deadline_at')
                ->where('deadline_at', '<', now())
                ->whereNotIn('status', [
                    ProductionOccurrenceStatus::Transmise->value,
                    ProductionOccurrenceStatus::Cloturee->value,
                    ProductionOccurrenceStatus::Annulee->value,
                ]);
        }
        if (! empty($filters['to_validate'])) {
            $query->whereIn('status', [
                ProductionOccurrenceStatus::Soumise->value,
                ProductionOccurrenceStatus::EnValidation->value,
            ]);
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('reference', 'like', $q)
                    ->orWhereHas('obligation', fn ($o) => $o->where('label', 'like', $q)->orWhere('code', 'like', $q));
            });
        }

        return $query->orderByDesc('deadline_at')->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function recordConsultation(User $actor, ProductionOccurrence $occurrence): void
    {
        $recipients = ProductionTransmissionRecipient::query()
            ->where('user_id', $actor->id)
            ->whereNull('viewed_at')
            ->whereHas('transmission', fn ($query) => $query->where('production_occurrence_id', $occurrence->id))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            $recipient->viewed_at = now();
            $recipient->delivery_status = 'viewed';
            $recipient->save();
        }

        $occurrence->loadMissing('files');
        foreach ($occurrence->files as $file) {
            if (! $file->document_id) {
                continue;
            }
            DocumentView::query()->updateOrCreate(
                ['user_id' => $actor->id, 'document_id' => $file->document_id],
                ['viewed_at' => now()],
            );
        }

        $this->recordEvent($occurrence, $actor, 'consulted', [
            'recipient_ids' => $recipients->pluck('id')->all(),
        ]);
    }

    public function find(User $actor, ProductionOccurrence $occurrence): ProductionOccurrence
    {
        $this->assertCanView($actor, $occurrence);

        return $occurrence->load([
            'obligation.actors.user:id,name',
            'obligation.reminderRules',
            'responsible:id,name,email',
            'structure:id,code,name',
            'task:id,reference,status,due_at,assignee_id',
            'files.document.latestVersion',
            'files.uploader:id,name',
            'validations.validator:id,name',
            'transmissions.recipients.user:id,name',
            'transmissions.transmitter:id,name',
            'events' => fn ($q) => $q->orderByDesc('id')->limit(50),
        ]);
    }

    /**
     * Generate occurrences for all active obligations for a target date.
     *
     * @return array{created: int, skipped: int, run_id: int}
     */
    public function generateForDate(?CarbonInterface $date = null, ?User $systemActor = null): array
    {
        $target = ($date ?? now())->copy()->startOfDay();
        $run = ProductionGenerationRun::query()->create([
            'target_date' => $target->toDateString(),
            'status' => 'running',
            'started_at' => now(),
        ]);

        $created = 0;
        $skipped = 0;
        $actor = $systemActor ?? User::query()->orderBy('id')->first();

        try {
            ProductionObligation::query()
                ->where('is_active', true)
                ->with(['calendar', 'suspensions'])
                ->orderBy('id')
                ->each(function (ProductionObligation $obligation) use ($target, $actor, &$created, &$skipped) {
                    if (! $this->recurrence->shouldGenerate($obligation, $target)) {
                        $skipped++;

                        return;
                    }

                    $occurrence = $this->createIfMissing($obligation, $target, $actor);
                    if ($occurrence->wasRecentlyCreated ?? false) {
                        $created++;
                        $this->folders->attachToMatchingFolders($occurrence);
                    } else {
                        $skipped++;
                    }
                });

            $run->update([
                'status' => 'done',
                'created_count' => $created,
                'skipped_count' => $skipped,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'created_count' => $created,
                'skipped_count' => $skipped,
                'finished_at' => now(),
            ]);
            throw $e;
        }

        return ['created' => $created, 'skipped' => $skipped, 'run_id' => $run->id];
    }

    public function createIfMissing(
        ProductionObligation $obligation,
        CarbonInterface $date,
        ?User $actor = null,
    ): ProductionOccurrence {
        $periodKey = $this->recurrence->periodKey($obligation, $date);
        $existing = ProductionOccurrence::query()
            ->where('production_obligation_id', $obligation->id)
            ->where('period_key', $periodKey)
            ->first();

        if ($existing) {
            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        return DB::transaction(function () use ($obligation, $date, $periodKey, $actor) {
            try {
                $bounds = $this->recurrence->periodBounds($obligation, $date);
                $deadline = $this->recurrence->deadlineAt($obligation, $date);
                $responsibleId = $obligation->producer_id;

                $occurrence = ProductionOccurrence::query()->create([
                    'reference' => $this->numbering->nextNumber(NumberingSequenceCode::ProductionOccurrence),
                    'production_obligation_id' => $obligation->id,
                    'period_key' => $periodKey,
                    'reference_date' => $date->toDateString(),
                    'period_start' => $bounds['start']->toDateString(),
                    'period_end' => $bounds['end']->toDateString(),
                    'deadline_at' => $deadline,
                    'structure_id' => $obligation->structure_id,
                    'responsible_id' => $responsibleId,
                    'status' => ProductionOccurrenceStatus::AProduire->value,
                ]);

                if ($actor && $responsibleId) {
                    $taskTypeId = TaskType::query()->where('code', 'production')->value('id');
                    $task = $this->tasks->create($actor, [
                        'title' => $obligation->label.' — '.$date->format('d/m/Y'),
                        'description' => $obligation->description,
                        'assignee_id' => $responsibleId,
                        'structure_id' => $obligation->structure_id,
                        'due_at' => $deadline,
                        'task_type_id' => $taskTypeId,
                        'source_kind' => TaskSource::Production->value,
                        'source_type' => ProductionOccurrence::class,
                        'source_id' => $occurrence->id,
                        'confidentiality' => $obligation->confidentiality?->value ?? $obligation->confidentiality,
                        'validator_id' => $obligation->actors()->where('role', 'validator')->orderBy('step_order')->value('user_id')
                            ?: $actor->id,
                    ]);
                    $occurrence->task_id = $task->id;
                    $occurrence->save();
                }

                $this->recordEvent($occurrence, $actor, 'generated', [
                    'period_key' => $periodKey,
                ]);

                $occurrence->wasRecentlyCreated = true;

                return $occurrence;
            } catch (QueryException $e) {
                // Concurrent insert — return existing
                $existing = ProductionOccurrence::query()
                    ->where('production_obligation_id', $obligation->id)
                    ->where('period_key', $periodKey)
                    ->first();
                if ($existing) {
                    $existing->wasRecentlyCreated = false;

                    return $existing;
                }
                throw $e;
            }
        });
    }

    public function updateStatus(
        User $actor,
        ProductionOccurrence $occurrence,
        ProductionOccurrenceStatus $status,
        ?string $notes = null,
    ): ProductionOccurrence {
        $this->assertCanUpdate($actor, $occurrence);

        return DB::transaction(function () use ($actor, $occurrence, $status, $notes) {
            $from = $occurrence->status?->value;
            $occurrence->status = $status;
            if ($notes) {
                $occurrence->notes = $notes;
            }
            if ($status === ProductionOccurrenceStatus::EnPreparation) {
                // no-op timestamps
            }
            if ($status === ProductionOccurrenceStatus::Cloturee) {
                $occurrence->closed_at = now();
            }
            if ($status === ProductionOccurrenceStatus::Annulee) {
                $occurrence->cancelled_at = now();
            }
            $occurrence->save();

            $this->recordEvent($occurrence, $actor, 'status_changed', [
                'from' => $from,
                'to' => $status->value,
            ]);
            $this->audit->log('production.occurrence.status', $occurrence, [
                'actor_id' => $actor->id,
                'status' => $status->value,
            ]);

            return $this->find($actor, $occurrence);
        });
    }

    public function justifyDelay(User $actor, ProductionOccurrence $occurrence, string $justification): ProductionOccurrence
    {
        $this->assertCanUpdate($actor, $occurrence);
        $occurrence->delay_justification = $justification;
        $occurrence->save();
        $this->recordEvent($occurrence, $actor, 'delay_justified', ['justification' => $justification]);

        return $occurrence->fresh();
    }

    public function assertCanView(User $actor, ProductionOccurrence $occurrence): void
    {
        if ($actor->can('production.view') || $actor->can('production.view_all') || $actor->can('production.manage') || $actor->can('admin.access')) {
            return;
        }

        $ok = (int) $occurrence->responsible_id === (int) $actor->id
            || ($occurrence->structure_id && (int) $occurrence->structure_id === (int) $actor->structure_id)
            || $occurrence->obligation?->actors()->where('user_id', $actor->id)->exists()
            || \App\Models\ProductionTransmissionRecipient::query()
                ->where('user_id', $actor->id)
                ->whereHas('transmission', fn ($query) => $query->where('production_occurrence_id', $occurrence->id))
                ->exists();

        abort_unless($ok, 403);
    }

    public function assertCanUpdate(User $actor, ProductionOccurrence $occurrence): void
    {
        if ($actor->can('production.manage') || $actor->can('admin.access')) {
            return;
        }

        $ok = (int) $occurrence->responsible_id === (int) $actor->id
            || (int) $occurrence->obligation?->deputy_id === (int) $actor->id;

        abort_unless($ok, 403);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function recordEvent(
        ProductionOccurrence $occurrence,
        ?User $actor,
        string $event,
        array $properties = [],
    ): void {
        ProductionEvent::query()->create([
            'production_occurrence_id' => $occurrence->id,
            'production_obligation_id' => $occurrence->production_obligation_id,
            'user_id' => $actor?->id,
            'event' => $event,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
