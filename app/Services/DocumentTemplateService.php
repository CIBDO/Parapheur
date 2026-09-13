<?php

namespace App\Services;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentTemplateVersionStatus;
use App\Models\Document;
use App\Models\DocumentGenerationEvent;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentTemplateService
{
    public function __construct(
        private readonly DocumentTemplateRenderer $renderer,
        private readonly PrivateDocumentStorage $storage,
        private readonly DocumentService $documentService,
        private readonly AuditLogger $audit,
    ) {}

    public function create(User $user, array $data, ?UploadedFile $file = null): DocumentTemplate
    {
        return DB::transaction(function () use ($user, $data, $file) {
            $template = DocumentTemplate::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'kind' => $data['kind'],
                'structure_id' => $data['structure_id'] ?? null,
                'confidentiality' => $data['confidentiality'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'description' => $data['description'] ?? null,
            ]);

            if ($file) {
                $this->addVersion($template, $user, $file, $data['change_note'] ?? 'Version initiale');
            }

            $this->audit->log('document_template.created', $template, ['actor_id' => $user->id]);

            return $template->fresh(['versions', 'currentPublishedVersion']);
        });
    }

    public function addVersion(
        DocumentTemplate $template,
        User $user,
        UploadedFile $file,
        ?string $changeNote = null
    ): DocumentTemplateVersion {
        $stored = $this->storage->store($file, 'templates/'.$template->id);

        $next = ((int) $template->versions()->max('version_number')) + 1;

        return DocumentTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_number' => $next,
            'status' => DocumentTemplateVersionStatus::Brouillon,
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime' => $stored['mime_type'],
            'size' => $stored['size'],
            'checksum' => $stored['checksum'],
            'created_by' => $user->id,
            'change_note' => $changeNote,
        ]);
    }

    public function publish(DocumentTemplate $template, DocumentTemplateVersion $version, User $user): DocumentTemplate
    {
        if ($version->template_id !== $template->id) {
            throw new InvalidArgumentException('Version non liée à ce modèle.');
        }

        return DB::transaction(function () use ($template, $version, $user) {
            if ($template->current_published_version_id) {
                $previous = DocumentTemplateVersion::query()->find($template->current_published_version_id);
                if ($previous) {
                    $previous->status = DocumentTemplateVersionStatus::Remplace;
                    $previous->save();
                }
            }

            $version->status = DocumentTemplateVersionStatus::Publie;
            $version->published_at = now();
            $version->save();

            $template->current_published_version_id = $version->id;
            $template->save();

            $this->audit->log('document_template.published', $template, [
                'actor_id' => $user->id,
                'version_id' => $version->id,
                'version_number' => $version->version_number,
            ]);

            return $template->fresh(['currentPublishedVersion', 'versions']);
        });
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function generateDocument(
        DocumentTemplate $template,
        User $user,
        array $variables,
        ?int $correspondenceId = null,
        ?int $transmissionSlipId = null,
    ): Document {
        $version = $template->currentPublishedVersion;
        if (! $version) {
            throw new InvalidArgumentException('Aucun modèle publié disponible.');
        }

        $absolute = $this->storage->absolutePath($version->disk, $version->path);
        $renderedPath = $this->renderer->render($absolute, $variables);

        $upload = new UploadedFile(
            $renderedPath,
            ($template->code ?: 'DOC').'.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $document = $this->documentService->create($user, [
            'origin' => DocumentOrigin::Courrier->value,
            'object' => $template->name.' — généré',
            'title' => $template->name,
            'reference' => $variables['numero'] ?? $variables['numero_bordereau'] ?? null,
        ], $upload);

        @unlink($renderedPath);

        DocumentGenerationEvent::query()->create([
            'template_version_id' => $version->id,
            'correspondence_id' => $correspondenceId,
            'transmission_slip_id' => $transmissionSlipId,
            'generated_document_id' => $document->id,
            'user_id' => $user->id,
            'data' => $variables,
        ]);

        return $document->fresh(['latestVersion']);
    }

    public function delete(DocumentTemplate $template): void
    {
        if ($template->current_published_version_id) {
            throw new InvalidArgumentException('Impossible de supprimer un modèle publié. Désactivez-le.');
        }

        $template->delete();
    }
}
