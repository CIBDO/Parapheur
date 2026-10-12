<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionFolder;
use App\Models\ProductionOccurrence;
use App\Services\Productions\ProductionFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionFolderController extends Controller
{
    public function __construct(
        private readonly ProductionFolderService $folders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('production.view')
            || $request->user()->can('production.manage')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->folders->list($request->user(), $request->all()));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('production.manage') || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'max:50'],
            'period_date' => ['nullable', 'date'],
            'period_key' => ['nullable', 'string', 'max:100'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'occurrence_ids' => ['nullable', 'array'],
            'occurrence_ids.*' => ['integer', 'exists:production_occurrences,id'],
        ]);

        return response()->json($this->folders->create($request->user(), $data), 201);
    }

    public function show(Request $request, ProductionFolder $folder): JsonResponse
    {
        abort_unless(
            $request->user()->can('production.view')
            || $request->user()->can('production.manage')
            || $request->user()->can('admin.access'),
            403
        );

        $loaded = $this->folders->find($folder);
        $payload = $loaded->toArray();
        $payload['completeness'] = $this->folders->completeness($folder);

        return response()->json($payload);
    }

    public function attach(Request $request, ProductionFolder $folder): JsonResponse
    {
        abort_unless(
            $request->user()->can('production.manage') || $request->user()->can('admin.access'),
            403
        );
        $data = $request->validate([
            'occurrence_id' => ['required', 'exists:production_occurrences,id'],
        ]);

        $occurrence = ProductionOccurrence::query()->findOrFail($data['occurrence_id']);

        return response()->json($this->folders->attachOccurrence($folder, $occurrence));
    }
}
