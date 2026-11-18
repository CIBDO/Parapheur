<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveRecord;
use App\Models\ArchiveRetentionRule;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveRetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveRetentionController extends Controller
{
    public function __construct(
        private readonly ArchiveRetentionService $retention,
        private readonly ArchiveAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $rules = ArchiveRetentionRule::query()
            ->with(['structure', 'series'])
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->orderByDesc('version')
            ->get();

        return response()->json(['data' => $rules]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'typology' => ['nullable', 'string', 'max:64'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'series_id' => ['nullable', 'integer', 'exists:archive_series,id'],
            'trigger_event' => ['nullable', 'string', 'max:64'],
            'current_years' => ['nullable', 'integer', 'min:0', 'max:200'],
            'intermediate_years' => ['nullable', 'integer', 'min:0', 'max:200'],
            'final_disposition' => ['nullable', 'string', 'max:64'],
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
        ]);

        return response()->json($this->retention->createRule($request->user(), $data), 201);
    }

    public function validateRule(Request $request, ArchiveRetentionRule $rule): JsonResponse
    {
        try {
            return response()->json($this->retention->validateRule($request->user(), $rule));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    public function apply(Request $request, ArchiveRecord $record): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.classer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'retention_rule_id' => ['required', 'integer', 'exists:archive_retention_rules,id'],
        ]);

        $rule = ArchiveRetentionRule::query()->findOrFail($data['retention_rule_id']);

        try {
            return response()->json($this->retention->applyToRecord($request->user(), $record, $rule));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function due(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.proposer_elimination')
            || $request->user()->can('admin.access')
            || $this->access->canViewModule($request->user()),
            403
        );

        $structureId = $request->filled('structure_id')
            ? (int) $request->integer('structure_id')
            : (($request->user()->can('admin.access') || $request->user()->can('archives.administrer'))
                ? null
                : (int) ($request->user()->structure_id ?? 0));

        return response()->json([
            'data' => $this->retention->dueRecords($structureId ?: null),
        ]);
    }
}
