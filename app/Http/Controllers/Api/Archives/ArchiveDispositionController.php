<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveDispositionRequest;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveDispositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveDispositionController extends Controller
{
    public function __construct(
        private readonly ArchiveDispositionService $dispositions,
        private readonly ArchiveAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.proposer_elimination')
            || $request->user()->can('archives.autoriser_elimination')
            || $request->user()->can('archives.executer_elimination')
            || $request->user()->can('admin.access')
            || $this->access->canViewModule($request->user()),
            403
        );

        $query = ArchiveDispositionRequest::query()
            ->with(['proposer', 'authorizer', 'structure'])
            ->withCount('items')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'disposition_type' => ['nullable', 'string', 'max:64'],
            'justification' => ['nullable', 'string'],
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer', 'exists:archive_records,id'],
        ]);

        try {
            return response()->json(
                $this->dispositions->propose($request->user(), $data, $data['record_ids']),
                201
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, ArchiveDispositionRequest $disposition): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()) || $request->user()->can('admin.access'), 403);

        return response()->json($disposition->load([
            'items.record',
            'items.box',
            'approvals.approver',
            'proposer',
            'authorizer',
            'executor',
            'structure',
        ]));
    }

    public function authorizeDisposition(Request $request, ArchiveDispositionRequest $disposition): JsonResponse
    {
        $data = $request->validate([
            'pv_reference' => ['nullable', 'string', 'max:128'],
            'comment' => ['nullable', 'string'],
        ]);

        try {
            return response()->json($this->dispositions->authorize(
                $request->user(),
                $disposition,
                $data['pv_reference'] ?? null,
                $data['comment'] ?? null,
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refuse(Request $request, ArchiveDispositionRequest $disposition): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            return response()->json($this->dispositions->refuse($request->user(), $disposition, $data['reason']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function execute(Request $request, ArchiveDispositionRequest $disposition): JsonResponse
    {
        try {
            return response()->json($this->dispositions->execute($request->user(), $disposition));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
