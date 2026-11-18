<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCommunicationRequest;
use App\Models\ArchiveRecord;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveCommunicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveCommunicationController extends Controller
{
    public function __construct(
        private readonly ArchiveCommunicationService $communications,
        private readonly ArchiveAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->canViewModule($request->user()), 403);

        $query = ArchiveCommunicationRequest::query()
            ->with(['record', 'requester', 'structure'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('mine')) {
            $query->where('requester_id', $request->user()->id);
        } elseif (! $request->user()->can('archives.communiquer') && ! $request->user()->can('admin.access')) {
            $query->where('requester_id', $request->user()->id);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'record_id' => ['required', 'integer', 'exists:archive_records,id'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'access_mode' => ['nullable', 'string', 'in:consultation,telechargement,extrait'],
        ]);

        $record = ArchiveRecord::query()->findOrFail($data['record_id']);

        try {
            return response()->json(
                $this->communications->request($request->user(), $record, $data),
                201
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function authorizeRequest(Request $request, ArchiveCommunicationRequest $communication): JsonResponse
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        try {
            return response()->json(
                $this->communications->authorize($request->user(), $communication, (int) ($data['days'] ?? 30))
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refuse(Request $request, ArchiveCommunicationRequest $communication): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            return response()->json(
                $this->communications->refuse($request->user(), $communication, $data['reason'])
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function revoke(Request $request, ArchiveCommunicationRequest $communication): JsonResponse
    {
        try {
            return response()->json($this->communications->revoke($request->user(), $communication));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
