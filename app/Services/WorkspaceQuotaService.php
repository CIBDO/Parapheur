<?php

namespace App\Services;

use App\Enums\DocumentOrigin;
use App\Enums\QuotaScopeType;
use App\Enums\WorkspaceType;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocumentLink;
use App\Models\WorkspaceFolder;
use App\Models\WorkspaceQuotaOverride;
use App\Models\WorkspaceStoragePolicy;
use App\Support\AllowedDocumentUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Extensions réellement autorisées (politique − refusées, ou fallback MIMES parapheur).
     *
     * @return list<string>
     */
    public function resolvedAllowedExtensions(): array
    {
        $policy = $this->policy();
        $allowed = $this->normalizeExtensions($policy->allowed_extensions);
        if ($allowed === []) {
            $allowed = $this->normalizeExtensions(explode(',', AllowedDocumentUploads::MIMES));
        }

        $denied = $this->normalizeExtensions($policy->denied_extensions);

        return array_values(array_diff($allowed, $denied));
    }

    /**
     * Règles Laravel de validation fichier basées sur la politique de stockage.
     *
     * @return list<string>
     */
    public function fileValidationRules(bool $required = false): array
    {
        $policy = $this->policy();
        $maxKb = max(1, (int) ceil(((int) $policy->max_upload_bytes) / 1024));
        $mimes = implode(',', $this->resolvedAllowedExtensions());

        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.$maxKb,
            'mimes:'.$mimes,
        ];
    }

    public function assertExtensionAllowed(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            throw new InvalidArgumentException('Extension de fichier manquante.');
        }

        $denied = $this->normalizeExtensions($this->policy()->denied_extensions);
        if (in_array($ext, $denied, true)) {
            throw new InvalidArgumentException("Extension « .{$ext} » non autorisée.");
        }

        $allowed = $this->resolvedAllowedExtensions();
        if (! in_array($ext, $allowed, true)) {
            throw new InvalidArgumentException(
                "Extension « .{$ext} » non autorisée. Autorisées : ".implode(', ', $allowed).'.'
            );
        }
    }

    /**
     * Purge définitive des éléments en corbeille au-delà de trash_retention_days.
     *
     * @return array{folders: int, documents: int}
     */
    public function purgeExpiredTrash(): array
    {
        $days = max(1, (int) $this->policy()->trash_retention_days);
        $cutoff = now()->subDays($days);
        $foldersPurged = 0;
        $documentsPurged = 0;
        $workspacesToRecalc = [];

        WorkspaceFolder::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($folders) use (&$foldersPurged, &$workspacesToRecalc): void {
                foreach ($folders as $folder) {
                    $workspacesToRecalc[(int) $folder->workspace_id] = true;
                    $folder->forceDelete();
                    $foldersPurged++;
                }
            });

        WorkspaceDocumentLink::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($links) use (&$documentsPurged, &$workspacesToRecalc): void {
                foreach ($links as $link) {
                    $workspacesToRecalc[(int) $link->workspace_id] = true;
                    $documentId = (int) $link->document_id;
                    $link->forceDelete();

                    $document = Document::withTrashed()->whereKey($documentId)->first();
                    if (! $document || ! $document->trashed()) {
                        continue;
                    }

                    $stillLinked = WorkspaceDocumentLink::withTrashed()
                        ->where('document_id', $documentId)
                        ->exists();

                    if ($stillLinked) {
                        continue;
                    }

                    $this->purgeDocumentStorage($document);
                    $document->forceDelete();
                    $documentsPurged++;
                }
            });

        // Documents workspace/personnel soft-deleted sans aucun lien (y compris soft-deleted)
        Document::onlyTrashed()
            ->whereIn('origin', [DocumentOrigin::Personal->value, DocumentOrigin::Workspace->value])
            ->where('deleted_at', '<=', $cutoff)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('workspace_document_links')
                    ->whereColumn('workspace_document_links.document_id', 'documents.id');
            })
            ->orderBy('id')
            ->chunkById(50, function ($documents) use (&$documentsPurged): void {
                foreach ($documents as $document) {
                    $this->purgeDocumentStorage($document);
                    $document->forceDelete();
                    $documentsPurged++;
                }
            });

        foreach (array_keys($workspacesToRecalc) as $workspaceId) {
            $workspace = Workspace::query()->find($workspaceId);
            if ($workspace) {
                $this->recalculate($workspace);
            }
        }

        return [
            'folders' => $foldersPurged,
            'documents' => $documentsPurged,
        ];
    }

    private function purgeDocumentStorage(Document $document): void
    {
        $document->loadMissing(['versions', 'attachments']);

        foreach ($document->versions as $version) {
            if ($version->disk && $version->path) {
                try {
                    Storage::disk($version->disk)->delete($version->path);
                } catch (\Throwable) {
                    // ignore missing disks / files
                }
            }
        }

        foreach ($document->attachments as $attachment) {
            if ($attachment->disk && $attachment->path) {
                try {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                } catch (\Throwable) {
                    // ignore
                }
            }
        }
    }

    /**
     * @param  list<string>|array<int, string>|null  $list
     * @return list<string>
     */
    private function normalizeExtensions(?array $list): array
    {
        if ($list === null || $list === []) {
            return [];
        }

        $out = [];
        foreach ($list as $ext) {
            $ext = strtolower(ltrim(trim((string) $ext), '.'));
            if ($ext !== '') {
                $out[$ext] = $ext;
            }
        }

        return array_values($out);
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

        $structureId = $workspace->structure_id;
        if ($structureId) {
            $structureOverride = WorkspaceQuotaOverride::query()
                ->where('scope_type', QuotaScopeType::Structure->value)
                ->where('scope_id', $structureId)
                ->first();

            if ($structureOverride) {
                return (int) $structureOverride->quota_bytes;
            }
        }

        $policy = $this->policy();

        return $workspace->type === WorkspaceType::Personal
            ? (int) $policy->default_personal_quota_bytes
            : (int) $policy->default_shared_quota_bytes;
    }

    /**
     * @return array{used_bytes: int, quota_bytes: int, remaining_bytes: int, warn: bool, percent: float, source: string}
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
            'source' => $this->quotaSource($workspace),
        ];
    }

    public function quotaSource(Workspace $workspace): string
    {
        if (WorkspaceQuotaOverride::query()
            ->where('scope_type', QuotaScopeType::Workspace->value)
            ->where('scope_id', $workspace->id)
            ->exists()) {
            return 'workspace';
        }

        if ($workspace->quota_bytes !== null) {
            return 'workspace_field';
        }

        if ($workspace->type === WorkspaceType::Personal
            && WorkspaceQuotaOverride::query()
                ->where('scope_type', QuotaScopeType::User->value)
                ->where('scope_id', $workspace->owner_id)
                ->exists()) {
            return 'user';
        }

        if ($workspace->structure_id
            && WorkspaceQuotaOverride::query()
                ->where('scope_type', QuotaScopeType::Structure->value)
                ->where('scope_id', $workspace->structure_id)
                ->exists()) {
            return 'structure';
        }

        return 'policy';
    }

    public function usageOf(Workspace $workspace): int
    {
        return (int) $workspace->storage_used_bytes;
    }

    public function assertCanStore(Workspace $workspace, int $bytes, ?string $filename = null): void
    {
        $this->assertExtensionAllowed($filename);

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
            ->whereNull('workspace_document_links.deleted_at')
            ->where('document_versions.is_main', true)
            ->sum('document_versions.size');

        $attachments = (int) DB::table('workspace_document_links')
            ->join('document_attachments', 'document_attachments.document_id', '=', 'workspace_document_links.document_id')
            ->where('workspace_document_links.workspace_id', $workspace->id)
            ->whereNull('workspace_document_links.deleted_at')
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
