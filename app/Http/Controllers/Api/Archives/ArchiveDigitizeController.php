<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveTransfer;
use App\Services\Archives\ArchiveDigitizeService;
use App\Support\AllowedDocumentUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ArchiveDigitizeController extends Controller
{
    public function __construct(
        private readonly ArchiveDigitizeService $digitize,
    ) {}

    public function batch(Request $request): JsonResponse
    {
        $this->authorize('create', ArchiveTransfer::class);

        $data = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'producer_unit_id' => ['nullable', 'integer', 'exists:structures,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'typology' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'medium' => ['nullable', 'string', 'in:electronique,physique,hybride'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'classification_node_id' => ['nullable', 'integer', 'exists:archive_classification_nodes,id'],
            'fonds_id' => ['nullable', 'integer', 'exists:archive_fonds,id'],
            'series_id' => ['nullable', 'integer', 'exists:archive_series,id'],
            'grouping' => ['nullable', 'string', 'in:pieces,dossier'],
            'source_channel' => ['nullable', 'string', 'in:upload,camera,scanner'],
            'submit_after' => ['nullable', 'boolean'],
            'piece_titles' => ['nullable'],
            'piece_meta' => ['nullable'],
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => array_values(array_filter([
                'required',
                'file',
                'max:20480',
                'mimes:'.AllowedDocumentUploads::MIMES.',tif,tiff',
            ])),
        ]);

        $data['piece_titles'] = $this->decodeJsonList($request->input('piece_titles'));
        $data['piece_meta'] = $this->decodeJsonList($request->input('piece_meta'));
        $data['submit_after'] = $request->boolean('submit_after');

        $files = array_values(array_filter(
            $request->file('files', []),
            fn ($f) => $f instanceof UploadedFile,
        ));

        try {
            $transfer = $this->digitize->batch($request->user(), $files, $data);

            return response()->json($transfer, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * @return list<mixed>
     */
    private function decodeJsonList(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return is_array($raw) ? array_values($raw) : [];
    }
}
