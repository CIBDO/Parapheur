<?php

namespace App\Services;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentPublicationStatus;
use App\Enums\PublicationWorkflow;
use App\Models\Document;
use App\Models\DocumentDomain;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Versement unifié vers la Documentation institutionnelle depuis les modules métier.
 */
class InstitutionalDepositService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AntivirusScanService $antivirus,
    ) {}

    /**
     * @param  array{
     *   classification_node_id?: int|null,
     *   folder_id?: int|null,
     *   domain_id?: int|null,
     *   exercise_year?: int|null,
     *   publication_workflow?: string|null,
     *   pin_version?: bool,
     *   origin?: string|null,
     * }  $options
     */
    public function deposit(Document $document, User $actor, array $options = []): Document
    {
        return DB::transaction(function () use ($document, $actor, $options) {
            $document->origin = DocumentOrigin::tryFrom((string) ($options['origin'] ?? DocumentOrigin::Ged->value))
                ?? DocumentOrigin::Ged;

            if (! empty($options['classification_node_id'])) {
                $document->classification_node_id = (int) $options['classification_node_id'];
            }

            $folderId = $options['folder_id'] ?? null;
            if (! $folderId) {
                $folderId = $this->resolveStructureFolderId($document);
            }
            if ($folderId) {
                $document->folder_id = $folderId;
                $folder = DocumentFolder::query()->find($folderId);
                if ($folder && ! $document->publication_workflow) {
                    $document->publication_workflow = $folder->default_publication_workflow
                        ?? PublicationWorkflow::Controlled;
                }
            }

            if (! empty($options['domain_id'])) {
                $document->domain_id = (int) $options['domain_id'];
            } elseif (! $document->domain_id) {
                $document->domain_id = $this->guessDomainId($document);
            }

            if (! empty($options['exercise_year'])) {
                $document->exercise_year = (int) $options['exercise_year'];
            } elseif (! $document->exercise_year && $document->document_date) {
                $document->exercise_year = (int) $document->document_date->format('Y');
            }

            if (! empty($options['publication_workflow'])) {
                $document->publication_workflow = $options['publication_workflow'];
            }

            if (! $document->publication_status
                || $document->publication_status === DocumentPublicationStatus::Brouillon) {
                $document->publication_status = DocumentPublicationStatus::Depose;
            }

            $document->save();

            if ($options['pin_version'] ?? true) {
                $latest = $document->latestVersion;
                if ($latest) {
                    $document->versions()->where('is_official', true)->update(['is_official' => false]);
                    $latest->is_official = true;
                    $latest->change_note = trim(($latest->change_note ? $latest->change_note.' — ' : '').'Version figée à l’entrée institutionnelle');
                    $latest->change_source = $latest->change_source ?: 'import';
                    $latest->save();
                }
            }

            $this->antivirus->scanDocument($document);

            $this->audit->log('document.institutional_deposit', $document, [
                'actor_id' => $actor->id,
                'folder_id' => $document->folder_id,
                'domain_id' => $document->domain_id,
                'classification_node_id' => $document->classification_node_id,
            ]);

            return $document->fresh([
                'type', 'folder', 'domain', 'classificationNode', 'author', 'latestVersion', 'officialVersion',
            ]);
        });
    }

    private function resolveStructureFolderId(Document $document): ?int
    {
        $structureId = $document->owner_structure_id ?: $document->structure_id;
        if (! $structureId) {
            return DocumentFolder::query()->where('code', 'ROOT_DOC')->value('id');
        }

        $folderId = DocumentFolder::query()
            ->where('structure_id', $structureId)
            ->where('kind', 'structure')
            ->value('id');

        return $folderId ?: DocumentFolder::query()->where('code', 'ROOT_DOC')->value('id');
    }

    private function guessDomainId(Document $document): ?int
    {
        $haystack = mb_strtolower(($document->title ?: '').' '.($document->object ?: '').' '.($document->type?->code ?: ''));

        $map = [
            'TRESORERIE' => ['trésor', 'tresor', 'caisse', 'situation'],
            'COMPTA_PUB' => ['compta', 'comptable', 'écriture'],
            'RH' => ['rh', 'personnel', 'agent', 'carrière'],
            'INFORMATIQUE' => ['si', 'informatique', 'dsi', 'logiciel'],
            'REGLEMENTATION' => ['loi', 'décret', 'decret', 'arrêté', 'arrete', 'circulaire'],
            'FINANCES_PUB' => ['budget', 'finance'],
            'CONTROLE' => ['contrôle', 'controle', 'audit'],
            'ORGANISATION' => ['procédure', 'procedure', 'organisation', 'manuel'],
        ];

        foreach ($map as $code => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return DocumentDomain::query()->where('code', $code)->value('id');
                }
            }
        }

        return DocumentDomain::query()->where('code', 'AUTRE')->value('id');
    }
}
