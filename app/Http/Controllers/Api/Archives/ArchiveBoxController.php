<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveBox;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\PhysicalArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveBoxController extends Controller
{
    public function __construct(
        private readonly PhysicalArchiveService $physical,
        private readonly ArchiveAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $query = ArchiveBox::query()
            ->with(['location', 'structure', 'fonds', 'series'])
            ->orderByDesc('id');

        if ($request->filled('structure_id')) {
            $query->where('structure_id', (int) $request->integer('structure_id'));
        } elseif (! $request->user()->can('admin.access') && ! $request->user()->can('archives.administrer')) {
            $query->where('structure_id', (int) ($request->user()->structure_id ?? 0));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->integer('location_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $q = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('code', 'like', $q)
                    ->orWhere('label', 'like', $q);
            });
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_boites')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'code' => ['nullable', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:255'],
            'producer_unit_id' => ['nullable', 'integer', 'exists:structures,id'],
            'fonds_id' => ['nullable', 'integer', 'exists:archive_fonds,id'],
            'series_id' => ['nullable', 'integer', 'exists:archive_series,id'],
            'location_id' => ['nullable', 'integer', 'exists:archive_storage_locations,id'],
            'typology' => ['nullable', 'string', 'max:64'],
            'physical_state' => ['nullable', 'string', 'max:32'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date'],
            'linear_meters' => ['nullable', 'numeric', 'min:0'],
            'force_capacity' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            return response()->json($this->physical->createBox($request->user(), $data), 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, ArchiveBox $box): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        return response()->json(
            $box->load(['location', 'structure', 'fonds', 'series', 'contents.record', 'movements'])
        );
    }

    public function assign(Request $request, ArchiveBox $box): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_boites')
            || $request->user()->can('archives.gerer_emplacements')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:archive_storage_locations,id'],
            'force_capacity' => ['nullable', 'boolean'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return response()->json($this->physical->assignLocation(
                $request->user(),
                $box,
                (int) $data['location_id'],
                (bool) ($data['force_capacity'] ?? false),
                $data['motif'] ?? null,
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function addContent(Request $request, ArchiveBox $box): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_boites')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'record_id' => ['nullable', 'integer', 'exists:archive_records,id'],
            'folder_code' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $content = $this->physical->addContent(
            $request->user(),
            $box,
            $data['record_id'] ?? null,
            $data
        );

        return response()->json($content, 201);
    }

    public function label(Request $request, ArchiveBox $box): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        return response()->json([
            'code' => $box->code,
            'label' => $box->label,
            'location' => $box->location?->full_code,
            'dates' => trim(($box->date_start?->format('Y') ?? '').'-'.($box->date_end?->format('Y') ?? ''), '-'),
            'qr_token' => $box->qr_token,
            'qr_payload' => 'sae:box:'.$box->qr_token,
        ]);
    }

    public function scan(Request $request, string $token): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $box = $this->physical->resolveByQrToken($token);
        abort_if(! $box, 404, 'Boîte introuvable.');

        if (! $request->user()->can('admin.access')
            && ! $request->user()->can('archives.administrer')
            && (int) $box->structure_id !== (int) ($request->user()->structure_id ?? 0)) {
            abort(403, 'Habilitation insuffisante pour cette boîte.');
        }

        return response()->json($box);
    }
}
