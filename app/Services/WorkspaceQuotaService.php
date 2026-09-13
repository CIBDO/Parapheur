<?php

namespace App\Services;

use App\Enums\QuotaScopeType;
use App\Enums\WorkspaceType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocumentLink;
use App\Models\WorkspaceQuotaOverride;
use App\Models\WorkspaceStoragePolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkspaceQuotaService
{
    public function __construct(
        private readonly WorkspaceBootstrapService $bootstrap,
    ) {}

    public function policy(): WorkspaceStoragePolicy
    {
        return $this->bootstrap->ensureStoragePolicy();
    }

    public function effectiveQuota(Workspace $workspace): int
    {
        $override = WorkspaceQuotaOverride::query()
            ->where('scope_type', QuotaScopeType::Workspace->value)
            ->where('scope_id', $workspace->id)
            ->first();

        if ($override) {
            return (int) $override->quota_bytes;
        }

        if ($workspace->quota_bytes !== null) {
            return (int) $workspace->quota_bytes;
        }

        $ownerOverride = WorkspaceQuotaOverride::query()
            ->where('scope_type', QuotaScopeType::User->value)
            ->where('scope_id', $workspace->owner_id)
            ->first();

        if ($ownerOverride && $workspace->type === WorkspaceType::Personal) {
            return (int) $ownerOverride->quota_bytes;
        }

        $policy = $this->policy();

        return $workspace->type === WorkspaceType::Personal
            ? (int) $policy->default_personal_quota_bytes
            : (int) $policy->default_shared_quota_bytes;
    }

    public function usageOf(Workspace $workspace): int
    {
        return (int) $workspace->storage_used_bytes;
    }

    public function assertCanStore(Workspace $workspace, int $bytes): void
    {
        $policy = $this->policy();

        if ($bytes > (int) $policy->max_upload_bytes) {
            throw new InvalidArgumentException(
                'Fichier trop volumineux (limite '.number_format((int) $policy->max_upload_bytes / 1048576, 0).' Mo).'
            );
        }

        if (! $policy->block_on_exceed) {
            return;
        }

        $quota = $this->effectiveQuota($workspace);
        $usage = $this->usageOf($workspace);

        if ($usage + $bytes > $quota) {
            throw new InvalidArgumentException(
                'Quota de stockage dépassé pour cet espace de travail.'
            );
        }
    }

    public function recalculate(Workspace $workspace): Workspace
    {
        $total = (int) DB::table('workspace_document_links')
            ->join('document_versions', 'document_versions.document_id', '=', 'workspace_document_links.document_id')
            ->where('workspace_document_links.workspace_id', $workspace->id)
            ->where('document_versions.is_main', true)
            ->sum('document_versions.size');

        $attachments = (int) DB::table('workspace_document_links')
            ->join('document_attachments', 'document_attachments.document_id', '=', 'workspace_document_links.document_id')
            ->where('workspace_document_links.workspace_id', $workspace->id)
            ->sum('document_attachments.size');

        $workspace->storage_used_bytes = $total + $attachments;
        $workspace->storage_recalculated_at = now();
        $workspace->save();

        return $workspace->fresh();
    }

    public function incrementUsage(Workspace $workspace, int $bytes): void
    {
        Workspace::query()->whereKey($workspace->id)->increment('storage_used_bytes', max(0, $bytes));
        $workspace->refresh();
    }

    /**
     * @return array{used_bytes: int, quota_bytes: int, remaining_bytes: int, warn: bool, percent: float}
     */
    public function storagePayload(Workspace $workspace): array
    {
        $used = $this->usageOf($workspace);
        $quota = $this->effectiveQuota($workspace);
        $percent = $quota > 0 ? round(($used / $quota) * 100, 1) : 0.0;
        $warnThreshold = (int) $this->policy()->warn_threshold_percent;

        return [
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'remaining_bytes' => max(0, $quota - $used),
            'warn' => $percent >= $warnThreshold,
            'percent' => $percent,
        ];
    }

    public function updatePolicy(array $data, User $actor): WorkspaceStoragePolicy
    {
        $policy = $this->policy();
        $fillable = [
            'default_personal_quota_bytes',
            'default_shared_quota_bytes',
            'max_upload_bytes',
            'allowed_extensions',
            'denied_extensions',
            'trash_retention_days',
            'warn_threshold_percent',
            'block_on_exceed',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $policy->{$field} = $data[$field];
            }
        }

        $policy->updated_by = $actor->id;
        $policy->save();

        return $policy->fresh();
    }

    public function setOverride(string $scopeType, int $scopeId, int $quotaBytes, User $actor, ?string $note = null): WorkspaceQuotaOverride
    {
        return WorkspaceQuotaOverride::query()->updateOrCreate(
            [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ],
            [
                'quota_bytes' => $quotaBytes,
                'note' => $note,
                'created_by' => $actor->id,
            ]
        );
    }
}
