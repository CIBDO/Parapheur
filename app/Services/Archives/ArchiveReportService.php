<?php

namespace App\Services\Archives;

use App\Models\ArchiveBox;
use App\Models\ArchiveCommunicationRequest;
use App\Models\ArchiveDispositionRequest;
use App\Models\ArchiveLoan;
use App\Models\ArchiveRecord;
use App\Models\ArchiveStorageLocation;
use App\Models\ArchiveTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ArchiveReportService
{
    public function __construct(private readonly ArchiveAccessService $access) {}

    /**
     * @return array<string, mixed>
     */
    public function responsibleDashboard(User $user): array
    {
        $structureId = $user->can('admin.access') || $user->can('archives.administrer')
            ? null
            : (int) ($user->structure_id ?? 0);

        $records = ArchiveRecord::query();
        $boxes = ArchiveBox::query();
        $transfers = ArchiveTransfer::query();
        $locations = ArchiveStorageLocation::query();
        $loans = ArchiveLoan::query();
        $dispositions = ArchiveDispositionRequest::query();

        if ($structureId) {
            $records->where('structure_id', $structureId);
            $boxes->where('structure_id', $structureId);
            $transfers->where('structure_id', $structureId);
            $locations->where('structure_id', $structureId);
            $loans->where('structure_id', $structureId);
            $dispositions->where('structure_id', $structureId);
        }

        $saturated = (clone $locations)
            ->whereNotNull('capacity_boxes')
            ->whereColumn('occupied_boxes', '>=', 'capacity_boxes')
            ->count();

        return [
            'records_total' => (clone $records)->count(),
            'boxes_total' => (clone $boxes)->count(),
            'transfers_pending' => (clone $transfers)->whereIn('status', ['soumis', 'en_controle'])->count(),
            'transfers_accepted' => (clone $transfers)->whereIn('status', ['accepte', 'pris_en_charge', 'conserve'])->count(),
            'locations_saturated' => $saturated,
            'loans_open' => (clone $loans)->whereIn('status', ['sorti', 'remis', 'en_retard'])->count(),
            'loans_overdue' => (clone $loans)->where(function ($q) {
                $q->where('status', 'en_retard')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'sorti')->whereDate('due_at', '<', now()->toDateString());
                    });
            })->count(),
            'retention_due' => (clone $records)->whereNotNull('disposition_due_at')
                ->whereDate('disposition_due_at', '<=', now()->toDateString())
                ->whereIn('status', ['conserve', 'a_eliminer'])
                ->count(),
            'holds_active' => DB::table('archive_holds')->whereNull('lifted_at')->when($structureId, function ($q) use ($structureId) {
                $q->whereIn('record_id', ArchiveRecord::query()->where('structure_id', $structureId)->select('id'));
            })->count(),
            'dispositions_pending' => (clone $dispositions)->whereIn('status', ['proposition', 'en_validation', 'autorisee'])->count(),
            'communications_pending' => ArchiveCommunicationRequest::query()
                ->when($structureId, fn ($q) => $q->where('structure_id', $structureId))
                ->where('status', 'demande')
                ->count(),
            'by_structure' => ArchiveRecord::query()
                ->select('structure_id', DB::raw('count(*) as total'))
                ->when($structureId, fn ($q) => $q->where('structure_id', $structureId))
                ->groupBy('structure_id')
                ->get()
                ->load('structure:id,code,name'),
            'by_typology' => ArchiveRecord::query()
                ->select('typology', DB::raw('count(*) as total'))
                ->when($structureId, fn ($q) => $q->where('structure_id', $structureId))
                ->groupBy('typology')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Indicateurs consolidés DG — sans contenu confidentiel.
     *
     * @return array<string, mixed>
     */
    public function dgDashboard(User $user): array
    {
        if (! $user->can('dashboard.dg') && ! $user->can('archives.administrer') && ! $user->can('admin.access')) {
            throw new HttpException(403, 'Accès DG archives refusé.');
        }

        return [
            'coverage' => ArchiveRecord::query()
                ->select('structure_id', DB::raw('count(*) as records_count'))
                ->groupBy('structure_id')
                ->get()
                ->load('structure:id,code,name')
                ->map(fn ($row) => [
                    'structure_id' => $row->structure_id,
                    'structure_code' => $row->structure?->code,
                    'structure_name' => $row->structure?->name,
                    'records_count' => (int) $row->records_count,
                ]),
            'transfers' => [
                'pending' => ArchiveTransfer::query()->whereIn('status', ['soumis', 'en_controle'])->count(),
                'conserved' => ArchiveTransfer::query()->where('status', 'conserve')->count(),
            ],
            'occupation' => [
                'locations' => ArchiveStorageLocation::query()->count(),
                'saturated' => ArchiveStorageLocation::query()
                    ->whereNotNull('capacity_boxes')
                    ->whereColumn('occupied_boxes', '>=', 'capacity_boxes')
                    ->count(),
                'boxes' => ArchiveBox::query()->count(),
            ],
            'compliance' => [
                'retention_due' => ArchiveRecord::query()
                    ->whereNotNull('disposition_due_at')
                    ->whereDate('disposition_due_at', '<=', now()->toDateString())
                    ->whereIn('status', ['conserve', 'a_eliminer'])
                    ->count(),
                'holds' => DB::table('archive_holds')->whereNull('lifted_at')->count(),
                'dispositions_pending' => ArchiveDispositionRequest::query()
                    ->whereIn('status', ['proposition', 'en_validation', 'autorisee'])
                    ->count(),
            ],
            'loans_overdue' => ArchiveLoan::query()
                ->where(function ($q) {
                    $q->where('status', 'en_retard')
                        ->orWhere(function ($q2) {
                            $q2->where('status', 'sorti')->whereDate('due_at', '<', now()->toDateString());
                        });
                })
                ->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRecordsMetadata(User $user, ?int $structureId = null): array
    {
        $query = ArchiveRecord::query()
            ->with(['structure:id,code,name', 'fonds:id,code,name', 'series:id,code,name', 'retentionRule:id,code,name'])
            ->orderBy('permanent_id');

        $this->access->scopeRecordsFor($user, $query);

        if ($structureId) {
            $query->where('structure_id', $structureId);
        }

        return $query->limit(5000)->get()->map(fn (ArchiveRecord $r) => [
            'permanent_id' => $r->permanent_id,
            'reference' => $r->reference,
            'title' => $r->title,
            'status' => $r->status?->value ?? $r->status,
            'confidentiality' => $r->confidentiality,
            'structure' => $r->structure?->code,
            'fonds' => $r->fonds?->code,
            'series' => $r->series?->code,
            'retention_rule' => $r->retentionRule?->code,
            'disposition_due_at' => $r->disposition_due_at?->toDateString(),
            'final_disposition' => $r->final_disposition,
            'checksum' => $r->checksum,
            'conserved_at' => $r->conserved_at?->toIso8601String(),
        ])->all();
    }
}
