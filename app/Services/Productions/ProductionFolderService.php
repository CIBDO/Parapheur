<?php

namespace App\Services\Productions;

use App\Enums\NumberingSequenceCode;
use App\Models\ProductionFolder;
use App\Models\ProductionFolderTemplate;
use App\Models\ProductionOccurrence;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductionFolderService
{
    public function __construct(
        private readonly NumberingService $numbering,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = ProductionFolder::query()
            ->with(['structure:id,code,name', 'creator:id,name'])
            ->withCount('items');

        if (! empty($filters['period_date'])) {
            $query->whereDate('period_date', $filters['period_date']);
        }
        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('reference', 'like', $q)->orWhere('title', 'like', $q);
            });
        }

        return $query->orderByDesc('period_date')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function find(ProductionFolder $folder): ProductionFolder
    {
        return $folder->load([
            'structure:id,code,name',
            'creator:id,name',
            'occurrences.obligation:id,code,label',
            'occurrences.responsible:id,name',
            'occurrences.files',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ProductionFolder
    {
        return DB::transaction(function () use ($actor, $data) {
            $folder = ProductionFolder::query()->create([
                'reference' => $this->numbering->nextNumber(NumberingSequenceCode::ProductionFolder),
                'title' => $data['title'],
                'kind' => $data['kind'] ?? 'manual',
                'period_date' => $data['period_date'] ?? null,
                'period_key' => $data['period_key'] ?? null,
                'structure_id' => $data['structure_id'] ?? null,
                'created_by' => $actor->id,
                'status' => $data['status'] ?? 'open',
            ]);

            foreach ($data['occurrence_ids'] ?? [] as $occurrenceId) {
                $folder->items()->firstOrCreate([
                    'production_occurrence_id' => $occurrenceId,
                ]);
            }

            return $this->find($folder);
        });
    }

    public function attachOccurrence(ProductionFolder $folder, ProductionOccurrence $occurrence): ProductionFolder
    {
        $folder->items()->firstOrCreate([
            'production_occurrence_id' => $occurrence->id,
        ]);

        return $this->find($folder);
    }

    public function attachToMatchingFolders(ProductionOccurrence $occurrence): void
    {
        $obligation = $occurrence->obligation;
        if (! $obligation) {
            return;
        }

        $templates = ProductionFolderTemplate::query()
            ->where('is_active', true)
            ->get();

        foreach ($templates as $template) {
            $codes = $template->obligation_codes ?? [];
            if (! in_array($obligation->code, $codes, true)) {
                continue;
            }

            $periodKey = $occurrence->period_key;
            $folder = ProductionFolder::query()->firstOrCreate(
                [
                    'kind' => $template->code,
                    'period_key' => $periodKey,
                ],
                [
                    'reference' => $this->numbering->nextNumber(NumberingSequenceCode::ProductionFolder),
                    'title' => $template->label.' — '.$occurrence->reference_date?->format('d/m/Y'),
                    'period_date' => $occurrence->reference_date,
                    'status' => 'open',
                ]
            );

            $folder->items()->firstOrCreate([
                'production_occurrence_id' => $occurrence->id,
            ]);
        }
    }

    /**
     * @return array{total: int, transmitted: int, pending: int, overdue: int, completeness: float}
     */
    public function completeness(ProductionFolder $folder): array
    {
        $occurrences = $folder->occurrences()->get();
        $total = $occurrences->count();
        $transmitted = $occurrences->where('status.value', 'transmise')->count();
        if ($transmitted === 0) {
            $transmitted = $occurrences->filter(fn ($o) => $o->status?->value === 'transmise')->count();
        }
        $overdue = $occurrences->filter(fn ($o) => $o->isPastDeadline() && $o->status?->value !== 'transmise')->count();
        $pending = $total - $transmitted;

        return [
            'total' => $total,
            'transmitted' => $transmitted,
            'pending' => $pending,
            'overdue' => $overdue,
            'completeness' => $total > 0 ? round(($transmitted / $total) * 100, 1) : 0,
        ];
    }
}
