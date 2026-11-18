<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveClassificationNode;
use App\Models\ArchiveFonds;
use App\Models\ArchiveSeries;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveClassificationController extends Controller
{
    public function __construct(
        private readonly ArchiveClassificationService $classification,
        private readonly ArchiveAccessService $access,
    ) {}

    public function fondsIndex(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $items = ArchiveFonds::query()
            ->with('series')
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function fondsStore(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer')
            || $request->user()->can('archives.classer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->classification->createFonds($request->user(), $data), 201);
    }

    public function seriesIndex(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $query = ArchiveSeries::query()->with('fonds')->orderBy('sort_order')->orderBy('name');
        if ($request->filled('fonds_id')) {
            $query->where('fonds_id', (int) $request->integer('fonds_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function seriesStore(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer')
            || $request->user()->can('archives.classer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'fonds_id' => ['required', 'integer', 'exists:archive_fonds,id'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->classification->createSeries($request->user(), $data), 201);
    }

    public function nodesIndex(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $nodes = ArchiveClassificationNode::query()
            ->with(['parent', 'fonds', 'series'])
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', (int) $request->integer('parent_id')))
            ->when($request->boolean('roots_only'), fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $nodes]);
    }

    public function nodesStore(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer')
            || $request->user()->can('archives.classer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:archive_classification_nodes,id'],
            'fonds_id' => ['nullable', 'integer', 'exists:archive_fonds,id'],
            'series_id' => ['nullable', 'integer', 'exists:archive_series,id'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'version' => ['nullable', 'integer', 'min:1'],
            'level' => ['nullable', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->classification->createNode($request->user(), $data), 201);
    }

    public function nodesUpdate(Request $request, ArchiveClassificationNode $classificationNode): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer')
            || $request->user()->can('archives.classer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:32'],
            'fonds_id' => ['nullable', 'integer', 'exists:archive_fonds,id'],
            'series_id' => ['nullable', 'integer', 'exists:archive_series,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->classification->updateNode($request->user(), $classificationNode, $data));
    }

    public function nodesDestroy(Request $request, ArchiveClassificationNode $classificationNode): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer') || $request->user()->can('admin.access'),
            403
        );

        try {
            $this->classification->deleteNode($request->user(), $classificationNode);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
