<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delegation;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = Delegation::query()->with(['delegator', 'delegate']);

        if ($request->boolean('mine')) {
            $userId = $request->user()->id;
            $query->where(function ($q) use ($userId) {
                $q->where('delegator_id', $userId)->orWhere('delegate_id', $userId);
            });
        }

        return response()->json($query->orderByDesc('starts_on')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'delegate_id' => ['required', 'exists:users,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'document_type_ids' => ['nullable', 'array'],
            'allowed_actions' => ['nullable', 'array'],
            'reason' => ['nullable', 'string'],
        ]);

        $delegation = Delegation::query()->create([
            ...$data,
            'delegator_id' => $request->user()->id,
            'is_active' => true,
        ]);

        $this->audit->log('delegation.created', $delegation);

        return response()->json($delegation->load(['delegator', 'delegate']), 201);
    }

    public function update(Request $request, Delegation $delegation): JsonResponse
    {
        abort_unless(
            $delegation->delegator_id === $request->user()->id || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'ends_on' => ['sometimes', 'date'],
            'reason' => ['nullable', 'string'],
            'allowed_actions' => ['nullable', 'array'],
        ]);

        $delegation->update($data);
        $this->audit->log('delegation.updated', $delegation);

        return response()->json($delegation->fresh(['delegator', 'delegate']));
    }
}
