<?php

namespace App\Services\Archives;

use App\Models\ArchiveRecord;
use App\Models\ArchiveTransfer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ArchiveSearchService
{
    public function __construct(
        private readonly ArchiveAccessService $access,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ArchiveTransfer>
     */
    public function searchTransfers(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ArchiveTransfer::query()
            ->with(['structure', 'creator', 'items'])
            ->orderByDesc('created_at');

        $this->access->scopeTransfersFor($user, $query);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['mine'])) {
            $query->where('created_by', $user->id);
        }

        if (! empty($filters['to_control'])) {
            $query->whereIn('status', ['soumis', 'en_controle']);
        }

        if (! empty($filters['q'])) {
            $q = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('reference', 'like', $q)
                    ->orWhere('title', 'like', $q)
                    ->orWhere('notes', 'like', $q);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ArchiveRecord>
     */
    public function searchRecords(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ArchiveRecord::query()
            ->with(['structure', 'fonds', 'series', 'classificationNode', 'document'])
            ->orderByDesc('conserved_at')
            ->orderByDesc('id');

        $this->access->scopeRecordsFor($user, $query);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', (int) $filters['structure_id']);
        }

        if (! empty($filters['fonds_id'])) {
            $query->where('fonds_id', (int) $filters['fonds_id']);
        }

        if (! empty($filters['series_id'])) {
            $query->where('series_id', (int) $filters['series_id']);
        }

        if (! empty($filters['q'])) {
            $q = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', $q)
                    ->orWhere('reference', 'like', $q)
                    ->orWhere('permanent_id', 'like', $q)
                    ->orWhere('description', 'like', $q);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, int>
     */
    public function dashboardCounts(User $user): array
    {
        $transfers = ArchiveTransfer::query();
        $this->access->scopeTransfersFor($user, $transfers);

        $records = ArchiveRecord::query();
        $this->access->scopeRecordsFor($user, $records);

        return [
            'records_total' => (clone $records)->count(),
            'records_conserved' => (clone $records)->where('status', 'conserve')->count(),
            'transfers_mine' => (clone $transfers)->where('created_by', $user->id)->count(),
            'transfers_pending' => (clone $transfers)->whereIn('status', ['soumis', 'en_controle'])->count(),
            'transfers_draft' => (clone $transfers)->where('status', 'brouillon')->count(),
            'transfers_conserved' => (clone $transfers)->where('status', 'conserve')->count(),
        ];
    }
}
