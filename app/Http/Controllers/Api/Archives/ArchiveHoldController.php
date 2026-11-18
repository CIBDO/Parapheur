<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveHold;
use App\Models\ArchiveRecord;
use App\Services\Archives\ArchiveAccessService;
use App\Services\Archives\ArchiveHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveHoldController extends Controller
{
    public function __construct(
        private readonly ArchiveHoldService $holds,
        private readonly ArchiveAccessService $access,
    ) {}

    public function place(Request $request, ArchiveRecord $record): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );
        abort_unless($this->access->canViewRecord($request->user(), $record) || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
            'hold_type' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            return response()->json($this->holds->place(
                $request->user(),
                $record,
                $data['reason'],
                $data['hold_type'] ?? 'administratif',
                $data['notes'] ?? null,
            ), 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function lift(Request $request, ArchiveHold $hold): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.gerer_conservation')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        try {
            return response()->json($this->holds->lift($request->user(), $hold, $data['notes'] ?? null));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
