<?php

namespace App\Services\Productions;

use App\Enums\DocumentConfidentiality;
use App\Enums\ProductionFrequency;
use App\Models\ProductionObligation;
use App\Models\ProductionObligationActor;
use App\Models\ProductionObligationHistory;
use App\Models\ProductionReminderRule;
use App\Models\ProductionSuspension;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionDefinitionService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = ProductionObligation::query()
            ->with(['structure:id,code,name', 'producer:id,name', 'deputy:id,name', 'calendar:id,code,name']);

        if (! $actor->can('production.manage') && ! $actor->can('admin.access') && ! $actor->can('production.view_all')) {
            $query->where(function ($q) use ($actor) {
                $q->where('producer_id', $actor->id)
                    ->orWhere('deputy_id', $actor->id)
                    ->orWhere('structure_id', $actor->structure_id)
                    ->orWhereHas('actors', fn ($a) => $a->where('user_id', $actor->id));
            });
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', $filters['structure_id']);
        }
        if (! empty($filters['frequency'])) {
            $query->where('frequency', $filters['frequency']);
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('code', 'like', $q)
                    ->orWhere('label', 'like', $q)
                    ->orWhere('description', 'like', $q);
            });
        }

        return $query->orderBy('code')->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ProductionObligation
    {
        return DB::transaction(function () use ($actor, $data) {
            $obligation = ProductionObligation::query()->create($this->mapFields($data, $actor));
            $this->syncActors($obligation, $data);
            $this->syncReminderRules($obligation, $data['reminder_rules'] ?? []);
            $this->recordHistory($obligation, $actor, 'created', null, $obligation->toArray());
            $this->audit->log('production.obligation.created', $obligation, ['actor_id' => $actor->id]);

            return $this->load($obligation);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ProductionObligation $obligation, array $data): ProductionObligation
    {
        return DB::transaction(function () use ($actor, $obligation, $data) {
            $before = $obligation->toArray();
            $obligation->fill($this->mapFields($data, $actor, false));
            $obligation->save();
            $this->syncActors($obligation, $data);
            if (array_key_exists('reminder_rules', $data)) {
                $this->syncReminderRules($obligation, $data['reminder_rules'] ?? []);
            }
            $this->recordHistory($obligation, $actor, 'updated', $before, $obligation->fresh()->toArray());
            $this->audit->log('production.obligation.updated', $obligation, ['actor_id' => $actor->id]);

            return $this->load($obligation);
        });
    }

    public function setActive(User $actor, ProductionObligation $obligation, bool $active): ProductionObligation
    {
        return DB::transaction(function () use ($actor, $obligation, $active) {
            $before = ['is_active' => $obligation->is_active];
            $obligation->is_active = $active;
            $obligation->save();
            $this->recordHistory($obligation, $actor, $active ? 'activated' : 'deactivated', $before, ['is_active' => $active]);
            $this->audit->log('production.obligation.'.($active ? 'activated' : 'deactivated'), $obligation, [
                'actor_id' => $actor->id,
            ]);

            return $this->load($obligation);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function suspend(User $actor, ProductionObligation $obligation, array $data): ProductionSuspension
    {
        return ProductionSuspension::query()->create([
            'production_obligation_id' => $obligation->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'reason' => $data['reason'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    public function load(ProductionObligation $obligation): ProductionObligation
    {
        return $obligation->fresh([
            'structure:id,code,name',
            'producer:id,name,email',
            'deputy:id,name,email',
            'calendar:id,code,name',
            'actors.user:id,name',
            'actors.structure:id,code,name',
            'reminderRules',
            'suspensions',
            'histories' => fn ($q) => $q->orderByDesc('id')->limit(20),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapFields(array $data, User $actor, bool $creating = true): array
    {
        $frequency = $data['frequency'] ?? ProductionFrequency::Daily->value;
        if (! in_array($frequency, array_map(fn ($c) => $c->value, ProductionFrequency::cases()), true)) {
            throw ValidationException::withMessages(['frequency' => 'Fréquence invalide.']);
        }

        $fields = [
            'code' => strtoupper((string) $data['code']),
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'domain' => $data['domain'] ?? null,
            'structure_id' => $data['structure_id'] ?? null,
            'producer_id' => $data['producer_id'] ?? null,
            'deputy_id' => $data['deputy_id'] ?? null,
            'calendar_id' => $data['calendar_id'] ?? null,
            'frequency' => $frequency,
            'schedule_config' => $data['schedule_config'] ?? null,
            'deadline_time' => isset($data['deadline_time'])
                ? (strlen((string) $data['deadline_time']) === 5 ? $data['deadline_time'].':00' : $data['deadline_time'])
                : '10:00:00',
            'timezone' => $data['timezone'] ?? 'Africa/Bamako',
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'confidentiality' => $data['confidentiality'] ?? DocumentConfidentiality::Normal->value,
            'deliverable_type' => $data['deliverable_type'] ?? 'files',
            'allowed_formats' => $data['allowed_formats'] ?? ['pdf', 'xlsx', 'docx', 'csv'],
            'min_files' => $data['min_files'] ?? 1,
            'max_files' => $data['max_files'] ?? null,
            'max_file_size_kb' => $data['max_file_size_kb'] ?? null,
            'template_document_id' => $data['template_document_id'] ?? null,
            'validation_required' => (bool) ($data['validation_required'] ?? true),
            'validation_mode' => $data['validation_mode'] ?? 'sequential',
            'auto_transmit' => (bool) ($data['auto_transmit'] ?? false),
            'submit_to_parapheur' => (bool) ($data['submit_to_parapheur'] ?? false),
            'workflow_id' => $data['workflow_id'] ?? null,
            'archive_enabled' => (bool) ($data['archive_enabled'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($creating) {
            $fields['created_by'] = $actor->id;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncActors(ProductionObligation $obligation, array $data): void
    {
        if (! array_key_exists('actors', $data) && ! array_key_exists('validators', $data)
            && ! array_key_exists('recipients', $data) && ! array_key_exists('contributors', $data)) {
            return;
        }

        $obligation->actors()->delete();
        $rows = [];

        foreach (['contributor' => 'contributors', 'validator' => 'validators', 'recipient' => 'recipients'] as $role => $key) {
            foreach ($data[$key] ?? [] as $index => $actor) {
                $rows[] = [
                    'production_obligation_id' => $obligation->id,
                    'role' => $role,
                    'user_id' => $actor['user_id'] ?? null,
                    'structure_id' => $actor['structure_id'] ?? null,
                    'role_name' => $actor['role_name'] ?? null,
                    'step_order' => $actor['step_order'] ?? ($index + 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach ($data['actors'] ?? [] as $actor) {
            $rows[] = [
                'production_obligation_id' => $obligation->id,
                'role' => $actor['role'],
                'user_id' => $actor['user_id'] ?? null,
                'structure_id' => $actor['structure_id'] ?? null,
                'role_name' => $actor['role_name'] ?? null,
                'step_order' => $actor['step_order'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            ProductionObligationActor::query()->insert($rows);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function syncReminderRules(ProductionObligation $obligation, array $rules): void
    {
        $obligation->reminderRules()->delete();
        foreach ($rules as $rule) {
            ProductionReminderRule::query()->create([
                'production_obligation_id' => $obligation->id,
                'offset_type' => $rule['offset_type'] ?? 'before',
                'offset_minutes' => $rule['offset_minutes'] ?? 0,
                'target' => $rule['target'] ?? 'producer',
                'escalate' => (bool) ($rule['escalate'] ?? false),
                'repeat_every_minutes' => $rule['repeat_every_minutes'] ?? null,
                'stop_when' => $rule['stop_when'] ?? 'transmitted',
                'is_active' => (bool) ($rule['is_active'] ?? true),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function recordHistory(
        ProductionObligation $obligation,
        User $actor,
        string $event,
        ?array $before,
        ?array $after,
    ): void {
        ProductionObligationHistory::query()->create([
            'production_obligation_id' => $obligation->id,
            'user_id' => $actor->id,
            'event' => $event,
            'before' => $before,
            'after' => $after,
            'created_at' => now(),
        ]);
    }
}
