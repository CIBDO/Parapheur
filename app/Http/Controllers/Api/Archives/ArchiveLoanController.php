<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveLoan;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveLoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveLoanController extends Controller
{
    public function __construct(
        private readonly ArchiveLoanService $loans,
        private readonly ArchiveAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $this->access->canViewModule($request->user())
            || $request->user()->can('archives.gerer_prets'),
            403
        );

        $query = ArchiveLoan::query()
            ->with(['box', 'requester', 'structure'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('mine')) {
            $query->where('requester_id', $request->user()->id);
        } elseif (! $request->user()->can('archives.gerer_prets') && ! $request->user()->can('admin.access')) {
            $query->where('requester_id', $request->user()->id);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $data = $request->validate([
            'box_id' => ['nullable', 'integer', 'exists:archive_boxes,id'],
            'record_id' => ['nullable', 'integer', 'exists:archive_records,id'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'loan_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            return response()->json($this->loans->request($request->user(), $data), 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, ArchiveLoan $loan): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        return response()->json($loan->load(['box.location', 'requester', 'validator', 'structure']));
    }

    public function validateLoan(Request $request, ArchiveLoan $loan): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_prets') || $request->user()->can('admin.access'), 403);

        try {
            return response()->json($this->loans->validate($request->user(), $loan));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function prepare(Request $request, ArchiveLoan $loan): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_prets') || $request->user()->can('admin.access'), 403);

        try {
            return response()->json($this->loans->prepare($request->user(), $loan));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function checkout(Request $request, ArchiveLoan $loan): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_prets') || $request->user()->can('admin.access'), 403);

        try {
            return response()->json($this->loans->checkout($request->user(), $loan));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function returnLoan(Request $request, ArchiveLoan $loan): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_prets') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'condition_in' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            return response()->json($this->loans->returnLoan($request->user(), $loan, $data['condition_in'] ?? null));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refuse(Request $request, ArchiveLoan $loan): JsonResponse
    {
        abort_unless($request->user()->can('archives.gerer_prets') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            return response()->json($this->loans->refuse($request->user(), $loan, $data['reason']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
