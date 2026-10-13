<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentAccessService;
use App\Services\DocumentPublicationService;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GedPublicationController extends Controller
{
    public function __construct(
        private readonly DocumentPublicationService $publication,
        private readonly DocumentService $documents,
        private readonly DocumentAccessService $access,
    ) {}

    public function submit(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $doc = $this->publication->submit($document, $request->user(), $data['comment'] ?? null);

        return response()->json($this->documents->detailPayload($doc, $request->user()));
    }

    public function approve(Request $request, Document $document): JsonResponse
    {
        $this->authorize('validatePublication', $document);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $doc = $this->publication->approve($document, $request->user(), $data['comment'] ?? null);

        return response()->json($this->documents->detailPayload($doc, $request->user()));
    }

    public function reject(Request $request, Document $document): JsonResponse
    {
        $this->authorize('validatePublication', $document);
        $data = $request->validate(['motif' => ['required', 'string', 'max:2000']]);
        $doc = $this->publication->reject($document, $request->user(), $data['motif']);

        return response()->json($this->documents->detailPayload($doc, $request->user()));
    }

    public function returnForCorrection(Request $request, Document $document): JsonResponse
    {
        $this->authorize('validatePublication', $document);
        $data = $request->validate(['motif' => ['required', 'string', 'max:2000']]);
        $doc = $this->publication->returnForCorrection($document, $request->user(), $data['motif']);

        return response()->json($this->documents->detailPayload($doc, $request->user()));
    }

    public function publish(Request $request, Document $document): JsonResponse
    {
        $this->authorize('publish', $document);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $doc = $this->publication->publish($document, $request->user(), $data['comment'] ?? null);

        return response()->json($this->documents->detailPayload($doc, $request->user()));
    }

    public function archive(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $doc = $this->publication->archivePublication($document, $request->user(), $data['comment'] ?? null);

        return response()->json($this->documents->detailPayload($doc, $request->user()));
    }
}
