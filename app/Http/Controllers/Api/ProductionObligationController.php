<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionObligation;
use App\Models\SlaCalendar;
use App\Services\Productions\ProductionDefinitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionObligationController extends Controller
{
    public function __construct(
        private readonly ProductionDefinitionService $definitions,
    ) {}

    public function calendars(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductionObligation::class);

        return response()->json(
            SlaCalendar::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductionObligation::class);

        return response()->json($this->definitions->list($request->user(), $request->all()));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ProductionObligation::class);

        $data = $this->validated($request);
        $obligation = $this->definitions->create($request->user(), $data);

        return response()->json($obligation, 201);
    }

    public function show(Request $request, ProductionObligation $obligation): JsonResponse
    {
        $this->authorize('view', $obligation);

        return response()->json($this->definitions->load($obligation));
    }

    public function update(Request $request, ProductionObligation $obligation): JsonResponse
    {
        $this->authorize('update', $obligation);

        return response()->json(
            $this->definitions->update($request->user(), $obligation, $this->validated($request, $obligation))
        );
    }

    public function setActive(Request $request, ProductionObligation $obligation): JsonResponse
    {
        $this->authorize('update', $obligation);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        return response()->json(
            $this->definitions->setActive($request->user(), $obligation, (bool) $data['is_active'])
        );
    }

    public function suspend(Request $request, ProductionObligation $obligation): JsonResponse
    {
        $this->authorize('update', $obligation);
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(
            $this->definitions->suspend($request->user(), $obligation, $data),
            201
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ProductionObligation $obligation = null): array
    {
        return $request->validate([
            'code' => [
                $obligation ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('production_obligations', 'code')->ignore($obligation?->id),
            ],
            'label' => [$obligation ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'domain' => ['nullable', 'string', 'max:100'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'producer_id' => ['nullable', 'exists:users,id'],
            'deputy_id' => ['nullable', 'exists:users,id'],
            'calendar_id' => ['nullable', 'exists:sla_calendars,id'],
            'frequency' => ['nullable', 'string'],
            'schedule_config' => ['nullable', 'array'],
            'deadline_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'confidentiality' => ['nullable', 'string'],
            'deliverable_type' => ['nullable', 'string'],
            'allowed_formats' => ['nullable', 'array'],
            'min_files' => ['nullable', 'integer', 'min:0'],
            'max_files' => ['nullable', 'integer', 'min:1'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:1'],
            'template_document_id' => ['nullable', 'exists:documents,id'],
            'validation_required' => ['nullable', 'boolean'],
            'validation_mode' => ['nullable', 'in:sequential,parallel'],
            'auto_transmit' => ['nullable', 'boolean'],
            'submit_to_parapheur' => ['nullable', 'boolean'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'archive_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'actors' => ['nullable', 'array'],
            'validators' => ['nullable', 'array'],
            'recipients' => ['nullable', 'array'],
            'contributors' => ['nullable', 'array'],
            'reminder_rules' => ['nullable', 'array'],
        ]);
    }
}
