<?php

namespace App\Http\Controllers\Api\Archives;

use App\Enums\ArchiveClassificationLevel;
use App\Enums\ArchiveDispositionStatus;
use App\Enums\ArchiveFinalDisposition;
use App\Enums\ArchiveLocationStatus;
use App\Enums\ArchiveLocationType;
use App\Enums\ArchiveRecordStatus;
use App\Enums\ArchiveTransferStatus;
use App\Http\Controllers\Controller;
use App\Services\Archives\ArchiveAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveMetaController extends Controller
{
    public function __construct(
        private readonly ArchiveAccessService $access,
    ) {}

    public function options(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()) || $request->user()->can('admin.access'), 403);

        $map = static fn ($cases) => collect($cases)
            ->map(fn ($case) => [
                'value' => $case->value,
                'title' => method_exists($case, 'label') ? $case->label() : $case->value,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'location_types' => $map(ArchiveLocationType::cases()),
                'location_statuses' => $map(ArchiveLocationStatus::cases()),
                'classification_levels' => $map(ArchiveClassificationLevel::cases()),
                'final_dispositions' => $map(ArchiveFinalDisposition::cases()),
                'record_statuses' => $map(ArchiveRecordStatus::cases()),
                'transfer_statuses' => $map(ArchiveTransferStatus::cases()),
                'disposition_statuses' => $map(ArchiveDispositionStatus::cases()),
                'disposition_types' => $map(ArchiveFinalDisposition::cases()),
                'trigger_events' => [
                    ['value' => 'cloture', 'title' => 'Clôture'],
                    ['value' => 'versement', 'title' => 'Versement'],
                    ['value' => 'date_fin', 'title' => 'Date de fin'],
                    ['value' => 'decision', 'title' => 'Décision'],
                ],
            ],
        ]);
    }
}
