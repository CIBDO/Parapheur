<?php

namespace App\Services\Archives;

use App\Enums\ArchiveRecordStatus;
use App\Models\ArchiveRecord;
use App\Models\ArchiveRetentionEvent;
use App\Models\ArchiveRetentionRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArchiveRetentionService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRule(User $actor, array $data): ArchiveRetentionRule
    {
        $rule = ArchiveRetentionRule::query()->create([
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'typology' => $data['typology'] ?? null,
            'structure_id' => $data['structure_id'] ?? null,
            'series_id' => $data['series_id'] ?? null,
            'trigger_event' => $data['trigger_event'] ?? 'cloture',
            'current_years' => (int) ($data['current_years'] ?? 0),
            'intermediate_years' => (int) ($data['intermediate_years'] ?? 0),
            'final_disposition' => $data['final_disposition'] ?? 'conservation_definitive',
            'legal_basis' => $data['legal_basis'] ?? null,
            'authority' => $data['authority'] ?? null,
            'version' => (int) ($data['version'] ?? 1),
            'valid_from' => $data['valid_from'] ?? now()->toDateString(),
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_validated' => false,
        ]);

        $this->audit->log('retention.rule_created', $rule, $actor);

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRule(User $actor, ArchiveRetentionRule $rule, array $data): ArchiveRetentionRule
    {
        if ($rule->is_validated && ! ($data['force'] ?? false)) {
            // Une règle validée reste consultable ; on peut seulement désactiver sans forcer.
            if (array_key_exists('is_active', $data) && count($data) <= 2) {
                $rule->is_active = (bool) $data['is_active'];
                $rule->save();
                $this->audit->log('retention.rule_toggled', $rule, $actor);

                return $rule->fresh(['structure', 'series', 'validator']);
            }

            throw new InvalidArgumentException('Règle déjà validée : créez une nouvelle version ou désactivez-la.');
        }

        $rule->fill([
            'code' => array_key_exists('code', $data) ? strtoupper(trim((string) $data['code'])) : $rule->code,
            'name' => $data['name'] ?? $rule->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $rule->description,
            'typology' => array_key_exists('typology', $data) ? $data['typology'] : $rule->typology,
            'structure_id' => array_key_exists('structure_id', $data) ? $data['structure_id'] : $rule->structure_id,
            'series_id' => array_key_exists('series_id', $data) ? $data['series_id'] : $rule->series_id,
            'trigger_event' => $data['trigger_event'] ?? $rule->trigger_event,
            'current_years' => array_key_exists('current_years', $data) ? (int) $data['current_years'] : $rule->current_years,
            'intermediate_years' => array_key_exists('intermediate_years', $data) ? (int) $data['intermediate_years'] : $rule->intermediate_years,
            'final_disposition' => $data['final_disposition'] ?? $rule->final_disposition,
            'legal_basis' => array_key_exists('legal_basis', $data) ? $data['legal_basis'] : $rule->legal_basis,
            'authority' => array_key_exists('authority', $data) ? $data['authority'] : $rule->authority,
            'valid_from' => array_key_exists('valid_from', $data) ? $data['valid_from'] : $rule->valid_from,
            'valid_to' => array_key_exists('valid_to', $data) ? $data['valid_to'] : $rule->valid_to,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $rule->is_active,
        ])->save();

        $this->audit->log('retention.rule_updated', $rule, $actor);

        return $rule->fresh(['structure', 'series', 'validator']);
    }

    public function validateRule(User $actor, ArchiveRetentionRule $rule): ArchiveRetentionRule
    {
        if (! $actor->can('archives.gerer_conservation') && ! $actor->can('archives.administrer') && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Habilitation insuffisante pour valider un tableau de gestion.');
        }

        $rule->fill([
            'is_validated' => true,
            'validated_by' => $actor->id,
            'validated_at' => now(),
        ])->save();

        $this->audit->log('retention.rule_validated', $rule, $actor);

        return $rule->fresh(['validator', 'series', 'structure']);
    }

    public function applyToRecord(User $actor, ArchiveRecord $record, ArchiveRetentionRule $rule): ArchiveRecord
    {
        if (! $rule->is_active || ! $rule->is_validated) {
            throw new InvalidArgumentException('La règle doit être active et validée institutionnellement.');
        }

        return DB::transaction(function () use ($actor, $record, $rule) {
            $base = $record->date_end
                ?: $record->date_start
                ?: ($record->conserved_at ? Carbon::parse($record->conserved_at) : now());

            $due = Carbon::parse($base)->addYears($rule->totalYears())->toDateString();

            $record->fill([
                'retention_rule_id' => $rule->id,
                'disposition_due_at' => $due,
                'final_disposition' => $rule->final_disposition,
            ])->save();

            ArchiveRetentionEvent::query()->create([
                'record_id' => $record->id,
                'rule_id' => $rule->id,
                'event_type' => 'applied',
                'due_at' => $due,
                'actor_id' => $actor->id,
                'payload' => [
                    'current_years' => $rule->current_years,
                    'intermediate_years' => $rule->intermediate_years,
                    'final_disposition' => $rule->final_disposition,
                ],
            ]);

            $this->audit->log('retention.applied', $record, $actor, [
                'rule_id' => $rule->id,
                'due_at' => $due,
            ]);

            return $record->fresh(['retentionRule']);
        });
    }

    /**
     * @return list<ArchiveRecord>
     */
    public function dueRecords(?int $structureId = null, int $limit = 100): array
    {
        $query = ArchiveRecord::query()
            ->with(['structure', 'retentionRule', 'holds'])
            ->whereNotNull('disposition_due_at')
            ->whereDate('disposition_due_at', '<=', now()->toDateString())
            ->whereIn('status', [
                ArchiveRecordStatus::Conserve->value,
                ArchiveRecordStatus::AEliminer->value,
            ])
            ->orderBy('disposition_due_at')
            ->limit($limit);

        if ($structureId) {
            $query->where('structure_id', $structureId);
        }

        return $query->get()->all();
    }
}
