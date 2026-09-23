<?php

namespace App\Services\Tasks;

use App\Enums\DocumentOrigin;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Task;
use App\Models\TaskDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentAccessService;
use App\Services\DocumentService;
use App\Services\DocumentTemplateService;
use App\Services\PrivateDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ZipArchive;

class TaskDocumentService
{
    public function __construct(
        private readonly TaskAccessService $access,
        private readonly TaskService $tasks,
        private readonly DocumentService $documents,
        private readonly DocumentTemplateService $templates,
        private readonly DocumentAccessService $documentAccess,
        private readonly PrivateDocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<TaskDocument>
     */
    public function list(User $actor, Task $task): array
    {
        if (! $this->access->canView($actor, $task)) {
            abort(403);
        }

        return $task->documentLinks()
            ->with([
                'document:id,uuid,reference,object,title,status,origin,classification_node_id,current_version',
                'document.type:id,code,name',
                'document.latestVersion:id,document_id,version_number,original_name,mime_type',
                'document.classificationNode:id,code,name',
                'linker:id,name',
            ])
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function linkExisting(User $actor, Task $task, int $documentId, string $role = 'ged_link', ?string $note = null): TaskDocument
    {
        if (! $this->access->canUpdate($actor, $task) && ! $this->access->canComment($actor, $task)) {
            abort(403);
        }

        $document = Document::query()->findOrFail($documentId);
        abort_unless($this->documentAccess->canAccess($actor, $document), 403);

        return DB::transaction(function () use ($actor, $task, $document, $role, $note) {
            $link = TaskDocument::query()->updateOrCreate(
                ['task_id' => $task->id, 'document_id' => $document->id],
                [
                    'linked_by' => $actor->id,
                    'role' => $role,
                    'note' => $note,
                    'submitted_to_ged' => $document->origin === DocumentOrigin::Ged
                        || ($document->origin instanceof DocumentOrigin && $document->origin === DocumentOrigin::Ged),
                ]
            );

            $this->tasks->recordHistory(
                $task,
                $actor,
                'document_linked',
                $task->status->value,
                $task->status->value,
                'Document lié : '.($document->reference ?: $document->object),
                ['document_id' => $document->id, 'role' => $role]
            );
            $this->audit->log('task.document_linked', $task, [
                'actor_id' => $actor->id,
                'document_id' => $document->id,
            ]);

            return $link->load(['document.latestVersion', 'document.type', 'linker:id,name']);
        });
    }

    /**
     * Crée un document Office editable (ONLYOFFICE) rattaché à la tâche.
     *
     * @param  array{title?: string, document_type_id?: int, format?: string}  $data
     */
    public function createOfficeDocument(User $actor, Task $task, array $data = []): TaskDocument
    {
        if (! $this->access->canUpdate($actor, $task) && ! $this->access->canComplete($actor, $task)) {
            abort(403);
        }

        $format = strtolower((string) ($data['format'] ?? 'docx'));
        if (! in_array($format, ['docx', 'xlsx', 'pptx'], true)) {
            throw new InvalidArgumentException('Format Office non supporté (docx, xlsx, pptx).');
        }

        $type = isset($data['document_type_id'])
            ? DocumentType::query()->findOrFail((int) $data['document_type_id'])
            : (DocumentType::query()->where('code', 'NOTE')->first()
                ?? DocumentType::query()->where('code', 'LETTRE')->first()
                ?? DocumentType::query()->firstOrFail());

        $title = trim((string) ($data['title'] ?? 'Livrable — '.$task->title));

        $structureId = $task->structure_id
            ?? $actor->structure_id
            ?? \App\Models\Structure::query()->value('id');

        if (! $structureId) {
            throw new InvalidArgumentException('Aucune structure disponible pour créer le document.');
        }

        return DB::transaction(function () use ($actor, $task, $type, $title, $format, $structureId) {
            $tmp = $this->makeBlankOfficeFile($format, $title);
            $upload = new UploadedFile($tmp, 'livrable.'.$format, $this->mimeFor($format), null, true);

            $document = $this->documents->create($actor, [
                'origin' => DocumentOrigin::Task->value,
                'object' => $title,
                'title' => $title,
                'document_type_id' => $type->id,
                'structure_id' => $structureId,
                'owner_structure_id' => $structureId,
                'confidentiality' => $task->confidentiality?->value ?? 'normal',
                'priority' => $task->priority?->value ?? 'normale',
                'keywords' => ['tache', $task->reference],
                'description' => 'Document produit depuis la tâche '.$task->reference,
            ], $upload);

            @unlink($tmp);

            $link = TaskDocument::query()->create([
                'task_id' => $task->id,
                'document_id' => $document->id,
                'linked_by' => $actor->id,
                'role' => 'working',
                'note' => 'Créé pour édition ONLYOFFICE',
            ]);

            $this->tasks->recordHistory(
                $task,
                $actor,
                'document_created',
                $task->status->value,
                $task->status->value,
                'Document Office créé : '.$document->reference,
                ['document_id' => $document->id]
            );
            $this->audit->log('task.document_created', $task, [
                'actor_id' => $actor->id,
                'document_id' => $document->id,
            ]);

            return $link->load(['document.latestVersion', 'document.type', 'linker:id,name']);
        });
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function createFromTemplate(User $actor, Task $task, int $templateId, array $variables = []): TaskDocument
    {
        if (! $this->access->canUpdate($actor, $task) && ! $this->access->canComplete($actor, $task)) {
            abort(403);
        }

        $template = DocumentTemplate::query()->findOrFail($templateId);

        return DB::transaction(function () use ($actor, $task, $template, $variables) {
            $document = $this->templates->generateDocument($template, $actor, array_merge([
                'objet' => $task->title,
                'reference_tache' => $task->reference,
            ], $variables));

            $document->origin = DocumentOrigin::Task;
            $document->object = $template->name.' — '.$task->title;
            $document->save();

            $link = TaskDocument::query()->create([
                'task_id' => $task->id,
                'document_id' => $document->id,
                'linked_by' => $actor->id,
                'role' => 'working',
                'note' => 'Généré depuis le modèle '.$template->code,
            ]);

            $this->tasks->recordHistory(
                $task,
                $actor,
                'document_from_template',
                $task->status->value,
                $task->status->value,
                'Document généré depuis modèle : '.$template->name,
                ['document_id' => $document->id, 'template_id' => $template->id]
            );

            return $link->load(['document.latestVersion', 'document.type', 'linker:id,name']);
        });
    }

    public function submitToGed(User $actor, Task $task, TaskDocument $link, ?int $classificationNodeId = null): TaskDocument
    {
        if ((int) $link->task_id !== (int) $task->id) {
            abort(404);
        }
        if (! $this->access->canUpdate($actor, $task) && ! $this->access->canComplete($actor, $task) && ! $this->access->isAdmin($actor)) {
            abort(403);
        }

        $document = $link->document;
        if (! $document) {
            throw new InvalidArgumentException('Document introuvable.');
        }
        abort_unless($this->documentAccess->canAccess($actor, $document), 403);

        return DB::transaction(function () use ($actor, $task, $link, $document, $classificationNodeId) {
            $document->origin = DocumentOrigin::Ged;
            if ($classificationNodeId) {
                $document->classification_node_id = $classificationNodeId;
            }
            $document->save();

            $latest = $document->latestVersion;
            if ($latest) {
                $document->versions()->where('is_official', true)->update(['is_official' => false]);
                $latest->is_official = true;
                $latest->change_note = trim(($latest->change_note ? $latest->change_note.' — ' : '').'Version figée à l’entrée GED');
                $latest->save();
            }

            $link->update([
                'role' => 'final',
                'submitted_to_ged' => true,
                'submitted_to_ged_at' => now(),
            ]);

            $this->tasks->recordHistory(
                $task,
                $actor,
                'document_submitted_ged',
                $task->status->value,
                $task->status->value,
                'Document versé à la GED : '.($document->reference ?: $document->object),
                ['document_id' => $document->id, 'classification_node_id' => $classificationNodeId]
            );
            $this->audit->log('task.document_submitted_ged', $task, [
                'actor_id' => $actor->id,
                'document_id' => $document->id,
            ]);

            return $link->fresh(['document.latestVersion', 'document.classificationNode', 'document.type']);
        });
    }

    public function unlink(User $actor, Task $task, TaskDocument $link): void
    {
        if ((int) $link->task_id !== (int) $task->id) {
            abort(404);
        }
        if (! $this->access->canUpdate($actor, $task) && ! $this->access->isAdmin($actor)) {
            abort(403);
        }

        $documentId = $link->document_id;
        $link->delete();

        $this->tasks->recordHistory(
            $task,
            $actor,
            'document_unlinked',
            $task->status->value,
            $task->status->value,
            'Lien document retiré',
            ['document_id' => $documentId]
        );
        $this->audit->log('task.document_unlinked', $task, [
            'actor_id' => $actor->id,
            'document_id' => $documentId,
        ]);
    }

    private function mimeFor(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };
    }

    /**
     * Minimal OOXML stub compatible ONLYOFFICE (docx/xlsx/pptx).
     */
    private function makeBlankOfficeFile(string $format, string $title): string
    {
        $safeTitle = htmlspecialchars(mb_substr($title, 0, 120), ENT_XML1);
        $tmp = tempnam(sys_get_temp_dir(), 'tsk_oo_');
        $path = $tmp.'.'.$format;
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('Impossible de créer le fichier Office.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml($format));
        $zip->addFromString('_rels/.rels', $this->rootRelsXml($format));

        match ($format) {
            'xlsx' => $this->addBlankXlsx($zip),
            'pptx' => $this->addBlankPptx($zip, $safeTitle),
            default => $this->addBlankDocx($zip, $safeTitle),
        };

        $zip->close();

        return $path;
    }

    private function contentTypesXml(string $format): string
    {
        return match ($format) {
            'xlsx' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML,
            'pptx' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
  <Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>
</Types>
XML,
            default => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        };
    }

    private function rootRelsXml(string $format): string
    {
        $target = match ($format) {
            'xlsx' => 'xl/workbook.xml',
            'pptx' => 'ppt/presentation.xml',
            default => 'word/document.xml',
        };
        $type = match ($format) {
            'xlsx' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument',
            'pptx' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument',
            default => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument',
        };

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="{$type}" Target="{$target}"/>
</Relationships>
XML;
    }

    private function addBlankDocx(ZipArchive $zip, string $safeTitle): void
    {
        $zip->addFromString('word/_rels/document.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
XML);
        $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>{$safeTitle}</w:t></w:r></w:p>
    <w:p><w:r><w:t></w:t></w:r></w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML);
    }

    private function addBlankXlsx(ZipArchive $zip): void
    {
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Feuil1" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData/>
</worksheet>
XML);
    }

    private function addBlankPptx(ZipArchive $zip, string $safeTitle): void
    {
        $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
        $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst>
</p:presentation>
XML);
        $zip->addFromString('ppt/slides/slide1.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:spPr/>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$safeTitle}</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
        $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
XML);
    }
}
