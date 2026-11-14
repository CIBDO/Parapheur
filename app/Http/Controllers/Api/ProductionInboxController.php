<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmission;
use App\Services\Productions\ProductionTransmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionInboxController extends Controller
{
    public function __construct(
        private readonly ProductionTransmissionService $transmissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductionOccurrence::class);

        return response()->json(
            $this->transmissions->inbox($request->user(), $request->all())
        );
    }

    public function show(Request $request, ProductionTransmission $transmission): JsonResponse
    {
        $recipient = $transmission->recipients()->where('user_id', $request->user()->id)->first();
        abort_unless($recipient, 403);

        if (! $recipient->viewed_at) {
            $recipient->viewed_at = now();
            $recipient->save();
        }

        $transmission->load([
            'occurrence.obligation',
            'occurrence.structure:id,code,name',
            'occurrence.responsible:id,name',
            'occurrence.files.document',
            'transmitter:id,name',
            'files.productionFile.document',
            'recipients.user:id,name',
        ]);

        return response()->json([
            ...$transmission->toArray(),
            'my_recipient' => $recipient->fresh(),
        ]);
    }

    public function opinion(Request $request, ProductionTransmission $transmission): JsonResponse
    {
        $data = $request->validate([
            'opinion' => ['required', Rule::in(['favorable', 'reserve', 'a_revoir'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $recipient = $this->transmissions->recordOpinion(
            $request->user(),
            $transmission,
            $data['opinion'],
            $data['comment'] ?? null,
        );

        return response()->json($recipient);
    }
}
