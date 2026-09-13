<?php

namespace App\Http\Controllers\Api\Ged;

use App\Enums\DocumentAccessAbility;
use App\Enums\DocumentArchiveStatus;
use App\Enums\DocumentAttachmentKind;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentLinkRelation;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\ExpectedAction;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAccessRule;
use App\Models\DocumentLink;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\DocumentSearchService;
use App\Services\DocumentService;
use App\Support\AllowedDocumentUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class GedDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentSearchService $search,
        private readonly DocumentAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'dossier_number' => ['nullable', 'string', 'max:100'],
            'object' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:500'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'type_id' => ['nullable', 'exists:document_types,id'],
            'category_id' => ['nullable', 'exists:document_categories,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'owner_structure_id' => ['nullable', 'exists:structures,id'],
            'author_id' => ['nullable', 'exists:users,id'],
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
            'status' => ['nullable', Rule::in(array_column(DocumentStatus::cases(), 'value'))],
            'priority' => ['nullable', Rule::in(array_column(DocumentPriority::cases(), 'value'))],
            'confidentiality' => ['nullable', Rule::in(array_column(DocumentConfidentiality::cases(), 'value'))],
            'origin' => ['nullable', Rule::in(array_column(DocumentOrigin::cases(), 'value'))],
            'archive_status' => ['nullable', Rule::in(array_column(DocumentArchiveStatus::cases(), 'value'))],
            'expected_action' => ['nullable', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'keywords' => ['nullable'],
            'tag' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'document_date_from' => ['nullable', 'date'],
            'document_date_to' => ['nullable', 'date'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'exercice' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'unclassified' => ['nullable', 'boolean'],
            'scope' => ['nullable', Rule::in(['mine', 'shared', 'to_process', 'archives', 'all'])],
            'meeting_id' => ['nullable', 'exists:meetings,id'],
            'sort' => ['nullable', 'string'],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $data['per_page'] ?? 15;
        unset($data['per_page']);

        return response()->json($this->search->search($user, $data, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validate([
            'object' => ['required', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:documents,reference'],
            'dossier_number' => ['nullable', 'string', 'max:100'],
            'document_type_id' => ['required', 'exists:document_types,id'],
            'category_id' => ['nullable', 'exists:document_categories,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'owner_structure_id' => ['nullable', 'exists:structures,id'],
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
            'priority' => ['nullable', Rule::in(array_column(DocumentPriority::cases(), 'value'))],
            'confidentiality' => ['nullable', Rule::in(array_column(DocumentConfidentiality::cases(), 'value'))],
            'expected_action' => ['nullable', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'document_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'keywords' => ['nullable'],
            'tags' => ['nullable'],
            'language' => ['nullable', 'string', 'max:10'],
            'source' => ['nullable', 'string', 'max:255'],
            'main_file' => AllowedDocumentUploads::fileRules(),
            'attachments' => ['nullable', 'array'],
            'attachments.*' => AllowedDocumentUploads::fileRules(),
            'pieces_jointes' => ['nullable', 'array'],
            'pieces_jointes.*' => AllowedDocumentUploads::fileRules(),
            'annexes' => ['nullable', 'array'],
            'annexes.*' => AllowedDocumentUploads::fileRules(),
            'justificatifs' => ['nullable', 'array'],
            'justificatifs.*' => AllowedDocumentUploads::fileRules(),
        ]);

        if (isset($data['keywords']) && is_string($data['keywords'])) {
            $decoded = json_decode($data['keywords'], true);
            $data['keywords'] = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $data['keywords']))));
        }

        if (isset($data['tags']) && is_string($data['tags'])) {
            $decoded = json_decode($data['tags'], true);
            $data['tags'] = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $data['tags']))));
        }

        if (empty($data['dossier_number'])) {
            $data['dossier_number'] = $this->documents->generateDossierNumber($request->user());
        }

        $attachments = [];
        foreach ($this->uploadedFiles($request, 'pieces_jointes') as $file) {
            $attachments[] = ['file' => $file, 'kind' => DocumentAttachmentKind::PieceJointe->value];
        }
        foreach ($this->uploadedFiles($request, 'annexes') as $file) {
            $attachments[] = ['file' => $file, 'kind' => DocumentAttachmentKind::Annexe->value];
        }
        foreach ($this->uploadedFiles($request, 'justificatifs') as $file) {
            $attachments[] = ['file' => $file, 'kind' => DocumentAttachmentKind::Justificatif->value];
        }
        foreach ($this->uploadedFiles($request, 'attachments') as $file) {
            $attachments[] = ['file' => $file, 'kind' => DocumentAttachmentKind::PieceJointe->value];
        }

        $document = $this->documents->create(
            $request->user(),
            $data,
            $request->file('main_file'),
            $attachments,
        );

        return response()->json($this->documents->detailPayload($document, $request->user()), 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        app(\App\Services\DocumentEngagementService::class)->recordView($request->user(), $document);

        return response()->json($this->documents->detailPayload($document, $request->user()));
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $data = $request->validate([
            'object' => ['sometimes', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('documents', 'reference')->ignore($document->id)],
            'dossier_number' => ['nullable', 'string', 'max:100'],
            'document_type_id' => ['sometimes', 'exists:document_types,id'],
            'category_id' => ['nullable', 'exists:document_categories,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'owner_structure_id' => ['nullable', 'exists:structures,id'],
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
            'priority' => ['nullable', Rule::in(array_column(DocumentPriority::cases(), 'value'))],
            'confidentiality' => ['nullable', Rule::in(array_column(DocumentConfidentiality::cases(), 'value'))],
            'expected_action' => ['nullable', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'document_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'keywords' => ['nullable'],
            'tags' => ['nullable'],
            'language' => ['nullable', 'string', 'max:10'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['keywords']) && is_string($data['keywords'])) {
            $decoded = json_decode($data['keywords'], true);
            $data['keywords'] = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $data['keywords']))));
        }

        if (isset($data['tags']) && is_string($data['tags'])) {
            $decoded = json_decode($data['tags'], true);
            $data['tags'] = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $data['tags']))));
        }

        $document = $this->documents->updateMetadata($document, $request->user(), $data);

        return response()->json($this->documents->detailPayload($document, $request->user()));
    }

    public function classify(Request $request, Document $document): JsonResponse
    {
        $this->authorize('classify', $document);

        $data = $request->validate([
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
        ]);

        $document = $this->documents->assignClassification(
            $document,
            $request->user(),
            $data['classification_node_id'] ?? null,
        );

        return response()->json($document);
    }

    public function archive(Request $request, Document $document): JsonResponse
    {
        $this->authorize('archive', $document);

        $data = $request->validate([
            'comment' => ['nullable', 'string'],
        ]);

        $document = $this->documents->archiveGed($document, $request->user(), $data['comment'] ?? null);

        return response()->json($this->documents->detailPayload($document, $request->user()));
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        try {
            $this->documents->softDelete($document, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Document placé en corbeille.']);
    }

    public function addVersion(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);
        abort_unless($request->user()->can('ged.create_version') || $request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'file' => AllowedDocumentUploads::fileRules(true),
            'change_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $version = $this->documents->addVersion(
            $document,
            $request->user(),
            $request->file('file'),
            $data['change_note'] ?? null,
        );

        return response()->json($version, 201);
    }

    public function addAttachment(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $data = $request->validate([
            'file' => AllowedDocumentUploads::fileRules(true),
            'kind' => ['nullable', Rule::in(array_column(DocumentAttachmentKind::cases(), 'value'))],
        ]);

        $attachment = $this->documents->addAttachment(
            $document,
            $request->user(),
            $request->file('file'),
            $data['kind'] ?? DocumentAttachmentKind::PieceJointe->value,
        );

        return response()->json($attachment, 201);
    }

    public function link(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $data = $request->validate([
            'target_document_id' => ['required', 'exists:documents,id'],
            'relation_type' => ['nullable', Rule::in(array_column(DocumentLinkRelation::cases(), 'value'))],
            'note' => ['nullable', 'string'],
        ]);

        $target = Document::query()->findOrFail($data['target_document_id']);
        $this->access->authorize($request->user(), $target);

        $link = $this->documents->linkDocuments(
            $document,
            $target,
            $request->user(),
            $data['relation_type'] ?? DocumentLinkRelation::RelatedTo->value,
            $data['note'] ?? null,
        );

        return response()->json($link, 201);
    }

    public function unlink(Request $request, Document $document, DocumentLink $link): JsonResponse
    {
        $this->authorize('update', $document);
        abort_unless(
            (int) $link->source_document_id === (int) $document->id
            || (int) $link->target_document_id === (int) $document->id,
            404
        );

        $this->documents->unlinkDocuments($link, $request->user());

        return response()->json(['message' => 'Lien supprimé.']);
    }

    public function share(Request $request, Document $document): JsonResponse
    {
        $this->authorize('share', $document);

        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'role_name' => ['nullable', 'string', 'max:100'],
            'ability' => ['nullable', Rule::in(array_column(DocumentAccessAbility::cases(), 'value'))],
            'expires_at' => ['nullable', 'date'],
        ]);

        $rule = $this->documents->share(
            $document,
            $request->user(),
            DocumentAccessAbility::tryFrom($data['ability'] ?? 'view') ?? DocumentAccessAbility::View,
            $data['user_id'] ?? null,
            $data['structure_id'] ?? null,
            $data['role_name'] ?? null,
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        return response()->json($rule, 201);
    }

    public function revokeShare(Request $request, Document $document, DocumentAccessRule $rule): JsonResponse
    {
        $this->authorize('share', $document);
        abort_unless((int) $rule->document_id === (int) $document->id, 404);

        $this->documents->revokeShare($rule, $request->user());

        return response()->json(['message' => 'Partage révoqué.']);
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(Request $request, string $key): array
    {
        $files = $request->file($key, []);
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return is_array($files) ? array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile)) : [];
    }
}
