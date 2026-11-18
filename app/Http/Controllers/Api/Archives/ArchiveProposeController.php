<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveTransfer;
use App\Models\Document;
use App\Services\Archives\ArchiveModuleBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveProposeController extends Controller
{
    public function __construct(
        private readonly ArchiveModuleBridgeService $bridge,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('create', ArchiveTransfer::class);

        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'producer_unit_id' => ['nullable', 'integer', 'exists:structures,id'],
            'source_module' => ['nullable', 'string', 'max:64'],
            'source_ref' => ['nullable', 'string', 'max:128'],
            'title' => ['nullable', 'string', 'max:255'],
            'typology' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'medium' => ['nullable', 'string', 'in:electronique,physique,hybride'],
        ]);

        $document = Document::query()->findOrFail((int) $data['document_id']);

        try {
            $transfer = $this->bridge->proposeFromDocument($request->user(), $document, $data);

            return response()->json($transfer, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
