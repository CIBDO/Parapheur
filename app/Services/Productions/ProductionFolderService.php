<?php

namespace App\Services\Productions;

use App\Enums\NumberingSequenceCode;
use App\Enums\ProductionOccurrenceStatus;
use App\Models\ProductionFolder;
use App\Models\ProductionFolderTemplate;
use App\Models\ProductionObligation;
use App\Models\ProductionOccurrence;
use App\Models\User;
use App\Services\NumberingService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductionFolderService
{
    public function __construct(
        private readonly NumberingService $numbering,
    ) {}

    /**
     * Pack trésorerie du jour : attendus / manquants / envoyés / consultés / avis.
     *
     * @return array<string, mixed>
     */
    public function todayPack(User $actor, ?string $date = null): array
    {
        $day = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();

        try {
            app(ProductionOccurrenceService::class)->generateForDate($day, $actor);
        } catch (\Throwable) {
            // ne bloque pas la consultation du pack
        }

        $template = ProductionFolderTemplate::query()
            ->where('code', 'daily_treasury')
            ->where('is_active', true)
            ->first();

        $codes = $template?->obligation_codes ?? [];
        if ($codes === []) {
            $codes = ProductionObligation::query()
                ->where('category', 'tresorerie')
                ->where('is_active', true)
                ->orderBy('code')
                ->pluck('code')
                ->all();
        }

        $obligations = ProductionObligation::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->with(['producer:id,name', 'structure:id,code,name'])
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        $folder = ProductionFolder::query()
            ->where('kind', 'daily_treasury')
            ->whereDate('period_date', $day->toDateString())
            ->first();

        $items = [];

        foreach ($codes as $code) {
            $obligation = $obligations->get($code);
            if (! $obligation) {
                continue;
            }

            $occurrence = ProductionOccurrence::query()
                ->with([
                    'responsible:id,name',
                    'structure:id,code,name',
                    'files:id,production_occurrence_id',
                    'transmissions' => fn ($q) => $q->orderByDesc('id')->limit(1),
                    'transmissions.recipients.user:id,name',
                ])
                ->where('production_obligation_id', $obligation->id)
                ->where(function ($q) use ($day) {
                    $q->whereDate('reference_date', $day->toDateString())
                        ->orWhere(function ($inner) use ($day) {
                            $inner->whereDate('period_start', '<=', $day->toDateString())
                                ->whereDate('period_end', '>=', $day->toDateString());
                        });
                })
                ->orderByDesc('id')
                ->first();

            $items[] = $this->packLine($obligation, $occurrence, $actor);
        }

        $stats = [
            'expected' => count($items),
            'missing' => collect($items)->where('bucket', 'missing')->count(),
            'to_send' => collect($items)->where('bucket', 'to_send')->count(),
            'sent' => collect($items)->whereIn('bucket', ['sent', 'viewed', 'with_opinion'])->count(),
            'viewed' => collect($items)->whereIn('bucket', ['viewed', 'with_opinion'])->count(),
            'with_opinion' => collect($items)->where('bucket', 'with_opinion')->count(),
        ];

        return [
            'date' => $day->toDateString(),
            'title' => $template?->label ?? 'Dossier quotidien de trésorerie',
            'folder_id' => $folder?->id,
            'folder_reference' => $folder?->reference,
            'stats' => $stats,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packLine(ProductionObligation $obligation, ?ProductionOccurrence $occurrence, User $actor): array
    {
        if (! $occurrence) {
            return [
                'obligation_code' => $obligation->code,
                'obligation_label' => $obligation->label,
                'structure' => $obligation->structure,
                'producer' => $obligation->producer,
                'occurrence_id' => null,
                'reference' => null,
                'status' => null,
                'bucket' => 'missing',
                'bucket_label' => 'Manquant',
                'deadline_at' => null,
                'transmitted_at' => null,
                'transmission_id' => null,
                'views' => [],
                'opinions' => [],
                'my_transmission_id' => null,
            ];
        }

        $status = $occurrence->status?->value ?? $occurrence->status;
        $filesCount = $occurrence->files->count();
        $transmission = $occurrence->transmissions->first();
        $recipients = $transmission?->recipients ?? collect();

        $views = $recipients->filter(fn ($r) => $r->viewed_at)->map(fn ($r) => [
            'user' => $r->user?->only(['id', 'name']),
            'viewed_at' => optional($r->viewed_at)?->toIso8601String(),
        ])->values()->all();

        $opinions = $recipients->filter(fn ($r) => $r->opinion)->map(fn ($r) => [
            'user' => $r->user?->only(['id', 'name']),
            'opinion' => $r->opinion,
            'comment' => $r->opinion_comment,
            'opinion_at' => optional($r->opinion_at)?->toIso8601String(),
        ])->values()->all();

        $myRecipient = $recipients->firstWhere('user_id', $actor->id);

        if (in_array($status, [
            ProductionOccurrenceStatus::Transmise->value,
            ProductionOccurrenceStatus::Cloturee->value,
        ], true)) {
            if (count($opinions) > 0) {
                $bucket = 'with_opinion';
                $bucketLabel = 'Avis reçu';
            } elseif (count($views) > 0) {
                $bucket = 'viewed';
                $bucketLabel = 'Consulté';
            } else {
                $bucket = 'sent';
                $bucketLabel = 'Envoyé';
            }
        } elseif ($filesCount > 0) {
            $bucket = 'to_send';
            $bucketLabel = 'À envoyer';
        } else {
            $bucket = 'missing';
            $bucketLabel = 'Manquant';
        }

        return [
            'obligation_code' => $obligation->code,
            'obligation_label' => $obligation->label,
            'structure' => $occurrence->structure ?? $obligation->structure,
            'producer' => $occurrence->responsible ?? $obligation->producer,
            'occurrence_id' => $occurrence->id,
            'reference' => $occurrence->reference,
            'status' => $status,
            'bucket' => $bucket,
            'bucket_label' => $bucketLabel,
            'deadline_at' => optional($occurrence->deadline_at)?->toIso8601String(),
            'transmitted_at' => optional($occurrence->transmitted_at)?->toIso8601String(),
            'transmission_id' => $transmission?->id,
            'views' => $views,
            'opinions' => $opinions,
            'my_transmission_id' => $myRecipient ? $transmission?->id : null,
        ];
    }

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
