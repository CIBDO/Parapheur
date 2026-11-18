<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchivePackage;
use App\Models\ArchiveRecord;
use App\Models\ArchiveTransfer;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveIntegrityService;
use App\Services\Archives\ArchivePackageService;
use App\Services\Archives\ArchivePreservationService;
use App\Services\Archives\ArchiveRestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchivePreservationController extends Controller
{
    public function __construct(
        private readonly ArchivePreservationService $preservation,
        private readonly ArchivePackageService $packages,
        private readonly ArchiveIntegrityService $integrity,
        private readonly ArchiveRestoreService $restore,
        private readonly ArchiveAccessService $access,
    ) {}

    public function supervision(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.auditer')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access')
            || $this->access->canViewModule($request->user()),
            403
        );

        return response()->json($this->preservation->supervisionSnapshot());
    }

    public function packagesIndex(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $query = ArchivePackage::query()
            ->with(['record:id,permanent_id,title', 'structure:id,code,name'])
            ->orderByDesc('id');

        if ($request->filled('package_type')) {
            $query->where('package_type', $request->string('package_type'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function packageShow(Request $request, ArchivePackage $package): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        return response()->json($package->load(['items.record', 'record', 'structure', 'creator']));
    }

    public function buildAip(Request $request, ArchiveRecord $record): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer')
            || $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'replicate' => ['nullable', 'boolean'],
        ]);

        try {
            $package = $this->preservation->sealRecord(
                $request->user(),
                $record,
                (bool) ($data['replicate'] ?? true)
            );

            return response()->json($package, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function buildSip(Request $request, ArchiveTransfer $transfer): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer')
            || $request->user()->can('archives.valider_versement')
            || $request->user()->can('admin.access'),
            403
        );

        try {
            return response()->json(
                $this->packages->buildSipFromTransfer($request->user(), $transfer),
                201
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function buildDip(Request $request, ArchiveRecord $record): JsonResponse
    {
        abort_unless(
            $this->access->canDownloadRecord($request->user(), $record)
            || $request->user()->can('archives.exporter')
            || $request->user()->can('admin.access'),
            403
        );

        try {
            return response()->json(
                $this->preservation->restitutionPackage($request->user(), $record),
                201
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function replicate(Request $request, ArchivePackage $package): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer') || $request->user()->can('admin.access'),
            403
        );

        try {
            return response()->json($this->packages->replicate($request->user(), $package));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function verifyRecord(Request $request, ArchiveRecord $record): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.auditer')
            || $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->integrity->verifyRecord($request->user(), $record));
    }

    public function verifyPackage(Request $request, ArchivePackage $package): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.auditer')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->integrity->verifyPackage($request->user(), $package));
    }

    public function verifyBatch(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.auditer')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $limit = (int) $request->integer('limit', 50);

        return response()->json($this->integrity->verifyBatch($request->user(), max(1, min(500, $limit))));
    }

    public function planMigration(Request $request, ArchiveRecord $record): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer') || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'target_profile' => ['nullable', 'string', 'max:64'],
        ]);

        $event = $this->preservation->registerFormatMigration(
            $request->user(),
            $record,
            $data['target_profile'] ?? 'pdfa'
        );

        return response()->json($event, 201);
    }

    public function restorePackage(Request $request, ArchivePackage $package): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer') || $request->user()->can('admin.access'),
            403
        );

        try {
            return response()->json($this->restore->restorePackageFromReplica($request->user(), $package));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function restoreRecord(Request $request, ArchiveRecord $record): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer') || $request->user()->can('admin.access'),
            403
        );

        try {
            return response()->json($this->restore->restoreRecordFromReplica($request->user(), $record));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function downloadPackage(Request $request, ArchivePackage $package): BinaryFileResponse|JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.exporter')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access')
            || $this->access->canViewModule($request->user()),
            403
        );

        try {
            $path = $this->preservation->absolutePackagePath($package);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->download($path, $package->reference.'.zip');
    }
}
