<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveImport;
use App\Services\Archives\ArchiveImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveImportController extends Controller
{
    public function __construct(private readonly ArchiveImportService $imports) {}

    public function preview(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer')
            || $request->user()->can('archives.verser')
            || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'import_type' => ['required', 'string', 'in:records,physical'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'column_mapping' => ['nullable', 'array'],
        ]);

        try {
            $import = $this->imports->preview(
                $request->user(),
                (int) $data['structure_id'],
                $data['import_type'],
                $request->file('file'),
                $data['column_mapping'] ?? []
            );

            return response()->json($import, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function commit(Request $request, ArchiveImport $import): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.administrer') || $request->user()->can('admin.access'),
            403
        );

        try {
            return response()->json($this->imports->commit($request->user(), $import));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, ArchiveImport $import): JsonResponse
    {
        abort_unless(
            $request->user()->can('archives.consulter')
            || $request->user()->can('archives.administrer')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($import->load(['structure', 'creator']));
    }
}
