<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Enums\QuotaScopeType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceQuotaOverride;
use App\Services\AuditLogger;
use App\Services\WorkspaceQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceQuotaAdminController extends Controller
{
    public function __construct(
        private readonly WorkspaceQuotaService $quotas,
        private readonly AuditLogger $audit,
    ) {}

    public function policy(Request $request): JsonResponse
    {
        $this->authorize('manageQuotas', Workspace::class);

        return response()->json($this->quotas->policy());
    }

    public function updatePolicy(Request $request): JsonResponse
    {
        $this->authorize('manageQuotas', Workspace::class);

        $data = $request->validate([
            'default_personal_quota_bytes' => ['sometimes', 'integer', 'min:1048576'],
            'default_shared_quota_bytes' => ['sometimes', 'integer', 'min:1048576'],
            'max_upload_bytes' => ['sometimes', 'integer', 'min:1024'],
            'allowed_extensions' => ['nullable', 'array'],
            'allowed_extensions.*' => ['string', 'max:20'],
            'denied_extensions' => ['nullable', 'array'],
            'denied_extensions.*' => ['string', 'max:20'],
            'trash_retention_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'warn_threshold_percent' => ['sometimes', 'integer', 'min:50', 'max:100'],
            'block_on_exceed' => ['sometimes', 'boolean'],
        ]);

        $policy = $this->quotas->policy();
        $policy->fill($data);
        $policy->updated_by = $request->user()->id;
        $policy->save();

        $this->audit->log('workspace.storage_policy_updated', $policy, [
            'actor_id' => $request->user()->id,
        ]);

        return response()->json($policy->fresh());
    }

    public function overrides(Request $request): JsonResponse
    {
        $this->authorize('manageQuotas', Workspace::class);

        return response()->json([
            'data' => WorkspaceQuotaOverride::query()->orderByDesc('id')->get(),
        ]);
    }

    public function storeOverride(Request $request): JsonResponse
    {
        $this->authorize('manageQuotas', Workspace::class);

        $data = $request->validate([
            'scope_type' => ['required', Rule::in([QuotaScopeType::User->value, QuotaScopeType::Workspace->value])],
            'scope_id' => ['required', 'integer', 'min:1'],
            'quota_bytes' => ['required', 'integer', 'min:1048576'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['scope_type'] === QuotaScopeType::User->value) {
            User::query()->whereKey($data['scope_id'])->firstOrFail();
        } else {
            Workspace::query()->whereKey($data['scope_id'])->firstOrFail();
        }

        $override = WorkspaceQuotaOverride::query()->updateOrCreate(
            [
                'scope_type' => $data['scope_type'],
                'scope_id' => $data['scope_id'],
            ],
            [
                'quota_bytes' => $data['quota_bytes'],
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]
        );

        $this->audit->log('workspace.quota_override_saved', $override, [
            'actor_id' => $request->user()->id,
        ]);

        return response()->json($override, 201);
    }

    public function destroyOverride(Request $request, WorkspaceQuotaOverride $override): JsonResponse
    {
        $this->authorize('manageQuotas', Workspace::class);

        $override->delete();

        return response()->json(['ok' => true]);
    }

    public function recalculate(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageQuotas', Workspace::class);

        $updated = $this->quotas->recalculate($workspace);

        return response()->json([
            'workspace_id' => $workspace->id,
            'storage_used_bytes' => (int) $updated->storage_used_bytes,
            'storage' => $this->quotas->storagePayload($updated),
        ]);
    }
}
