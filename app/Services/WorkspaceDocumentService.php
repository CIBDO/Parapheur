<?php

namespace App\Services;

use App\Enums\DocumentOrigin;
use App\Enums\WorkspaceType;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocumentLink;
use App\Models\WorkspaceFolder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkspaceDocumentService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly WorkspaceQuotaService $quotas,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(Workspace $workspace, ?int $folderId = null, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = WorkspaceDocumentLink::query()
            ->with([
                'document.type:id,code,name',
                'document.author:id,name,email',
                'document.latestVersion',
                'folder:id,name,path',
                'addedBy:id,name',
            ])
            ->where('workspace_id', $workspace->id);

        if ($folderId !== null) {
            $query->where('folder_id', $folderId);
        } elseif (array_key_exists('folder_id', $filters) && $filters['folder_id'] === null) {
            $query->whereNull('folder_id');
        } elseif ($folderId === null && ! array_key_exists('folder_id', $filters)) {
            // Par défaut en navigation racine : ne pas mélanger avec le contenu des sous-dossiers
            // (les appels explicites passent folder_id dans $filters)
        }

        if (! empty($filters['q'])) {
            $like = '%'.$filters['q'].'%';
            $query->whereHas('document', function ($d) use ($like) {
                $d->where('object', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('reference', 'like', $like);
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upload(
        Workspace $workspace,
        User $actor,
        array $data,
        ?UploadedFile $mainFile = null,
        ?int $folderId = null,
    ): WorkspaceDocumentLink {
        if ($folderId) {
            WorkspaceFolder::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($folderId)
                ->firstOrFail();
        }

        $bytes = $mainFile?->getSize() ?? 0;
        $this->quotas->assertCanStore($workspace, $bytes);

        if (empty($data['document_type_id'])) {
            $data['document_type_id'] = DocumentType::query()->orderBy('id')->value('id');
        }

        if (empty($data['document_type_id'])) {
            throw new InvalidArgumentException('Aucun type de document disponible.');
        }

        $data['object'] = $data['object'] ?? ($mainFile?->getClientOriginalName() ?? 'Document');
        $data['origin'] = $workspace->type === WorkspaceType::Personal
            ? DocumentOrigin::Personal->value
            : DocumentOrigin::Workspace->value;

        return DB::transaction(function () use ($workspace, $actor, $data, $mainFile, $folderId, $bytes) {
            $document = $this->documents->create($actor, $data, $mainFile);

            $link = WorkspaceDocumentLink::query()->create([
                'workspace_id' => $workspace->id,
                'folder_id' => $folderId,
                'document_id' => $document->id,
                'added_by' => $actor->id,
            ]);

            if ($bytes > 0) {
                $this->quotas->incrementUsage($workspace, $bytes);
            }

            $this->audit->log('workspace.document_uploaded', $document, [
                'workspace_id' => $workspace->id,
                'folder_id' => $folderId,
                'actor_id' => $actor->id,
            ]);

            app(WorkspaceActivityService::class)->record(
                $workspace,
                $actor,
                'document_added',
                $actor->name.' a ajouté « '.($document->title ?: $document->object).' »',
                $document,
            );

            return $link->fresh(['document.latestVersion', 'document.type', 'folder', 'addedBy']);
        });
    }

    public function move(WorkspaceDocumentLink $link, ?int $folderId): WorkspaceDocumentLink
    {
        if ($folderId !== null) {
            WorkspaceFolder::query()
                ->where('workspace_id', $link->workspace_id)
                ->whereKey($folderId)
                ->firstOrFail();
        }

        $link->folder_id = $folderId;
        $link->save();

        return $link->fresh(['document', 'folder']);
    }

    public function linkExisting(Workspace $workspace, Document $document, User $actor, ?int $folderId = null): WorkspaceDocumentLink
    {
        if ($folderId) {
            WorkspaceFolder::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($folderId)
                ->firstOrFail();
        }

        $existing = WorkspaceDocumentLink::query()
            ->where('workspace_id', $workspace->id)
            ->where('document_id', $document->id)
            ->first();

        if ($existing) {
            if ($folderId !== null) {
                $existing->folder_id = $folderId;
                $existing->save();
            }

            return $existing->fresh(['document', 'folder']);
        }

        $link = WorkspaceDocumentLink::query()->create([
            'workspace_id' => $workspace->id,
            'folder_id' => $folderId,
            'document_id' => $document->id,
            'added_by' => $actor->id,
        ]);

        $this->quotas->recalculate($workspace);

        return $link->fresh(['document', 'folder']);
    }

    public function unlink(WorkspaceDocumentLink $link, User $actor): void
    {
        $workspace = $link->workspace;
        $documentId = $link->document_id;
        $link->delete();
        $this->quotas->recalculate($workspace);
        $this->audit->log('workspace.document_unlinked', Document::query()->find($documentId), [
            'workspace_id' => $workspace->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function trash(WorkspaceDocumentLink $link, User $actor): void
    {
        $document = $link->document;
        $workspace = $link->workspace;

        DB::transaction(function () use ($link, $document, $actor, $workspace) {
            $link->delete();
            $this->documents->softDelete($document, $actor);
            $this->quotas->recalculate($workspace);
        });
    }

    public function restoreDocument(Document $document, Workspace $workspace, User $actor, ?int $folderId = null): WorkspaceDocumentLink
    {
        $document->restore();

        $link = WorkspaceDocumentLink::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('document_id', $document->id)
            ->first();

        // Les liens n'ont pas SoftDeletes — recréer si besoin
        if (! $link) {
            $link = WorkspaceDocumentLink::query()->create([
                'workspace_id' => $workspace->id,
                'folder_id' => $folderId,
                'document_id' => $document->id,
                'added_by' => $actor->id,
            ]);
        }

        $this->quotas->recalculate($workspace);

        return $link->fresh(['document', 'folder']);
    }
}
