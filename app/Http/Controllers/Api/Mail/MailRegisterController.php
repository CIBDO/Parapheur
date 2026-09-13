<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Services\CorrespondenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailRegisterController extends Controller
{
    public function __construct(
        private readonly CorrespondenceService $correspondenceService
    ) {}

    public function incoming(Request $request): JsonResponse
    {
        $this->authorize('create', Correspondence::class);

        if ($request->exists('requires_reply') && ! is_bool($request->input('requires_reply'))) {
            $request->merge([
                'requires_reply' => filter_var($request->input('requires_reply'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'observations' => 'nullable|string',
            'external_reference' => 'nullable|string|max:200',
            'correspondence_date' => 'required|date',
            'received_at' => 'required|date',
            'due_date' => 'nullable|date',
            'medium' => 'required|in:physique,electronique,hybride',
            'priority' => 'nullable|string',
            'confidentiality' => 'nullable|string',
            'structure_id' => 'nullable|exists:structures,id',
            'channel_id' => 'nullable|exists:correspondence_channels,id',
            'category_id' => 'nullable|exists:correspondence_categories,id',
            'qualification_id' => 'nullable|exists:correspondence_qualifications,id',
            'piece_count' => 'nullable|integer|min:1',
            'keywords' => 'nullable|array',
            'requires_reply' => 'nullable|boolean',
            'scan_file' => 'nullable|file|max:20480',
            'document_data' => 'nullable|array',
        ]);

        $scanFile = $request->file('scan_file');
        unset($validated['scan_file'], $validated['document_data']);

        $correspondence = $this->correspondenceService->createIncoming($request->user(), $validated, $scanFile);

        return response()->json($this->correspondenceService->serialize($correspondence), 201);
    }

    public function outgoing(Request $request): JsonResponse
    {
        $this->authorize('create', Correspondence::class);

        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'observations' => 'nullable|string',
            'correspondence_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'medium' => 'required|in:physique,electronique,hybride',
            'priority' => 'nullable|string',
            'confidentiality' => 'nullable|string',
            'structure_id' => 'nullable|exists:structures,id',
            'channel_id' => 'nullable|exists:correspondence_channels,id',
            'category_id' => 'nullable|exists:correspondence_categories,id',
            'keywords' => 'nullable|array',
            'requires_reply' => 'nullable|boolean',
        ]);

        $correspondence = $this->correspondenceService->createOutgoing($request->user(), $validated);

        return response()->json($this->correspondenceService->serialize($correspondence), 201);
    }

    public function internal(Request $request): JsonResponse
    {
        $this->authorize('create', Correspondence::class);

        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'observations' => 'nullable|string',
            'correspondence_date' => 'required|date',
            'due_date' => 'nullable|date',
            'medium' => 'required|in:physique,electronique,hybride',
            'priority' => 'nullable|string',
            'confidentiality' => 'nullable|string',
            'structure_id' => 'nullable|exists:structures,id',
            'channel_id' => 'nullable|exists:correspondence_channels,id',
            'category_id' => 'nullable|exists:correspondence_categories,id',
            'keywords' => 'nullable|array',
        ]);

        $correspondence = $this->correspondenceService->createInternal($request->user(), $validated);

        return response()->json($this->correspondenceService->serialize($correspondence), 201);
    }
}
