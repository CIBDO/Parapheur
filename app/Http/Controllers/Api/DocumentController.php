<?php

namespace App\Http\Controllers\Api;

use App\Contracts\DocumentPreviewDriver;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\ExpectedAction;
use App\Http\Controllers\Controller;
use App\Enums\ParapheurFolder;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\ArchivePackService;
use App\Services\DocumentAccessService;
use App\Services\DocumentWorkflowService;
use App\Services\ParapheurService;
use App\Services\PrivateDocumentStorage;
use App\Services\SignedDownloadService;
use App\Support\AllowedDocumentUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
        private readonly ParapheurService $parapheur,
        private readonly PrivateDocumentStorage $storage,
        private readonly DocumentAccessService $access,
        private readonly DocumentPreviewDriver $preview,
        private readonly ArchivePackService $archivePack,
        private readonly SignedDownloadService $signedDownloads,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'folder' => [
                'nullable',
                'string',
                Rule::in(array_map(fn ($c) => $c->value, ParapheurFolder::cases())),
            ],

            // Recherche native (sans moteur externe) — filtres sur métadonnées.
            'q' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'object' => ['nullable', 'string', 'max:500'],

            // Alias UI possible : `type_id` ↔ `document_type_id`
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'type_id' => ['nullable', 'exists:document_types,id'],

            'structure_id' => ['nullable', 'exists:structures,id'],
            'author_id' => ['nullable', 'exists:users,id'],

            'status' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, DocumentStatus::cases()))],
            'priority' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, DocumentPriority::cases()))],
            'confidentiality' => [
                'nullable',
                Rule::in(array_map(fn ($c) => $c->value, DocumentConfidentiality::cases())),
            ],

            'keywords' => ['nullable', 'string'],
            'document_date_from' => ['nullable', 'date'],
            'document_date_to' => ['nullable', 'date'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date'],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $folder = $data['folder'] ?? null;
        $perPage = $data['per_page'] ?? 15;

        // Normalisation des alias UI (type_id ↔ document_type_id).
        if (! empty($data['type_id']) && empty($data['document_type_id'])) {
            $data['document_type_id'] = $data['type_id'];
        }

        unset($data['folder'], $data['per_page'], $data['type_id']);

        return response()->json($this->parapheur->listFolder($user, $folder, $data, $perPage));
    }

    public function counts(Request $request): JsonResponse
    {
        return response()->json($this->parapheur->countsFor($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'object' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:documents,reference'],
            'document_type_id' => ['required', 'exists:document_types,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'priority' => ['nullable', Rule::in(['normale', 'importante', 'urgente', 'tres_urgente'])],
            'confidentiality' => ['nullable', Rule::in(['normal', 'restreint', 'confidentiel', 'tres_confidentiel'])],
            'expected_action' => ['nullable', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'document_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'keywords' => ['nullable'],
            'main_file' => AllowedDocumentUploads::fileRules(),
            'attachments' => ['nullable', 'array'],
            'attachments.*' => AllowedDocumentUploads::fileRules(),
            'pieces_jointes' => ['nullable', 'array'],
            'pieces_jointes.*' => AllowedDocumentUploads::fileRules(),
            'annexes' => ['nullable', 'array'],
            'annexes.*' => AllowedDocumentUploads::fileRules(),
            'transmit_to' => ['nullable'], // id unique (rétrocompat) ou ignoré si transmit_to_ids
            'transmit_to_ids' => ['nullable', 'array', 'max:25'],
            'transmit_to_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'transmit_message' => ['nullable', 'string'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
        ]);

        if (isset($data['keywords']) && is_string($data['keywords'])) {
            $decoded = json_decode($data['keywords'], true);
            $data['keywords'] = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $data['keywords']))));
        }

        $attachments = [];
        foreach ($this->uploadedFiles($request, 'pieces_jointes') as $file) {
            $attachments[] = ['file' => $file, 'kind' => 'piece_jointe'];
        }
        foreach ($this->uploadedFiles($request, 'annexes') as $file) {
            $attachments[] = ['file' => $file, 'kind' => 'annexe'];
        }
        // Compatibilité : ancien champ unique « attachments[] »
        foreach ($this->uploadedFiles($request, 'attachments') as $file) {
            $attachments[] = ['file' => $file, 'kind' => 'piece_jointe'];
        }

        $document = $this->workflow->createDraft(
            $request->user(),
            $data,
            $this->firstUploadedFile($request, 'main_file'),
            $attachments,
        );

        $recipientIds = $this->normalizeTransmitRecipientIds($data);
        $hasWorkflow = ! empty($data['workflow_id']);

        if ($hasWorkflow || $recipientIds !== []) {
            $recipients = $this->usersInOrder($recipientIds);
            $action = ExpectedAction::from($data['expected_action'] ?? ExpectedAction::Consultation->value);
            try {
                $document = $this->workflow->submitAndTransmitToMany(
                    $document,
                    $request->user(),
                    $recipients,
                    $action,
                    $data['transmit_message'] ?? null,
                    $hasWorkflow ? (int) $data['workflow_id'] : null,
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json($document, 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);

        $document->load([
            'type',
            'category',
            'structure',
            'ownerStructure',
            'classificationNode',
            'author',
            'currentAssignee',
            'versions.uploader',
            'attachments.uploader',
            'tags',
            'comments.user',
            'actions.actor',
            'actions.delegator',
            'visas.user',
            'approvals.user',
            'transmissions.fromUser',
            'transmissions.toUser',
            'instructions.assignee',
            'workflowInstance.workflow.steps',
            'officialVersion',
            'outgoingLinks.target.type',
            'incomingLinks.source.type',
        ]);

        $userId = $request->user()->id;

        $versions = $document->versions->map(function (DocumentVersion $version) use ($document, $request) {
            $payload = $version->toArray();
            $user = $request->user();
            $downloadParams = $this->signedDownloads->paramsForVersion(
                $document,
                $version->id,
                $user,
                SignedDownloadService::PURPOSE_DOWNLOAD,
            );
            $streamParams = $this->signedDownloads->paramsForVersion(
                $document,
                $version->id,
                $user,
                SignedDownloadService::PURPOSE_STREAM,
            );
            $payload['download_url'] = URL::temporarySignedRoute(
                'documents.version.download',
                now()->addMinutes($this->signedDownloads->ttlMinutes(SignedDownloadService::PURPOSE_DOWNLOAD)),
                $downloadParams
            );
            $payload['stream_url'] = URL::temporarySignedRoute(
                'documents.version.stream',
                now()->addMinutes($this->signedDownloads->ttlMinutes(SignedDownloadService::PURPOSE_STREAM)),
                $streamParams
            );
            $payload['preview'] = $this->preview->preview(
                $version,
                $payload['stream_url'],
                $payload['download_url'],
            );

            return $payload;
        })->values();

        $attachments = $document->attachments->map(function (DocumentAttachment $attachment) use ($document, $request) {
            $payload = $attachment->toArray();
            $params = $this->signedDownloads->paramsForAttachment(
                $document,
                $attachment->id,
                $request->user(),
            );
            $payload['download_url'] = URL::temporarySignedRoute(
                'documents.attachment.download',
                now()->addMinutes($this->signedDownloads->ttlMinutes(SignedDownloadService::PURPOSE_DOWNLOAD)),
                $params
            );

            return $payload;
        })->values();

        $document->unsetRelation('versions');
        $document->unsetRelation('attachments');

        return response()->json(array_merge($document->toArray(), [
            'versions' => $versions,
            'attachments' => $attachments,
        ]));
    }

    public function transmit(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeTransmit($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'to_user_id' => ['nullable'], // id unique (rétrocompat)
            'to_user_ids' => ['nullable', 'array', 'max:25'],
            'to_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'expected_action' => ['required', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'message' => ['nullable', 'string'],
        ]);

        $recipientIds = $this->normalizeTransmitRecipientIds([
            'transmit_to' => $data['to_user_id'] ?? null,
            'transmit_to_ids' => $data['to_user_ids'] ?? null,
        ]);
        $hasWorkflow = ! empty($data['workflow_id']);

        if (! $hasWorkflow && $recipientIds === []) {
            return response()->json(['message' => 'Destinataire(s) ou circuit requis.'], 422);
        }

        $recipients = $this->usersInOrder($recipientIds);

        try {
            $document = $this->workflow->submitAndTransmitToMany(
                $document,
                $request->user(),
                $recipients,
                ExpectedAction::from($data['expected_action']),
                $data['message'] ?? null,
                $hasWorkflow ? (int) $data['workflow_id'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($document);
    }

    public function reassign(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'to_user_id' => ['required', 'exists:users,id'],
            'expected_action' => ['required', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'message' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->workflow->reassign(
                $document,
                $request->user(),
                User::query()->findOrFail($data['to_user_id']),
                ExpectedAction::from($data['expected_action']),
                $data['message'] ?? null,
            )
        );
    }

    public function acknowledge(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->acknowledge($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function hold(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->putOnHold($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function classify(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->classify($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function comment(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'body' => ['required', 'string'],
            'kind' => ['nullable', Rule::in(['general', 'avis', 'observation', 'recommandation'])],
        ]);

        $kind = $data['kind'] ?? 'general';
        $this->access->authorizeProcess($request->user(), $document);

        try {
            $comment = $this->workflow->addComment(
                $document,
                $request->user(),
                $data['body'],
                $kind,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($comment, 201);
    }

    public function returnCorrection(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['required', 'string']]);

        return response()->json(
            $this->workflow->returnForCorrection($document, $request->user(), $data['comment'])
        );
    }

    public function requestComplement(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['required', 'string']]);

        return response()->json(
            $this->workflow->requestComplement($document, $request->user(), $data['comment'])
        );
    }

    public function vise(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document, 'vise');
        abort_unless($request->user()->can('documents.vise') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->vise($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function validateAction(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document, 'validate');
        abort_unless($request->user()->can('documents.validate') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->validateDocument($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function reject(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless(
            $request->user()->can('documents.validate')
            || $request->user()->can('documents.vise')
            || $request->user()->can('documents.act')
            || $request->user()->can('admin.access'),
            403
        );
        $data = $request->validate(['comment' => ['required', 'string']]);

        return response()->json(
            $this->workflow->reject($document, $request->user(), $data['comment'])
        );
    }

    public function archive(Request $request, Document $document): JsonResponse
    {
        $this->access->authorizeProcess($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->archive($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function downloadArchivePack(Request $request, Document $document): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access') || $request->user()->can('reporting.view'), 403);

        try {
            $pack = $this->archivePack->build($document, $request->user());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->download($pack['path'], $pack['filename'], [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function addVersion(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);

        $data = $request->validate([
            'file' => AllowedDocumentUploads::fileRules(required: true),
            'change_note' => ['nullable', 'string'],
        ]);

        $file = $this->firstUploadedFile($request, 'file');
        abort_unless($file, 422, 'Fichier requis.');

        $version = $this->workflow->addVersion(
            $document,
            $request->user(),
            $file,
            $data['change_note'] ?? null,
        );

        return response()->json($version, 201);
    }

    public function addAttachment(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);

        $data = $request->validate([
            'file' => AllowedDocumentUploads::fileRules(required: true),
            'kind' => ['nullable', Rule::in(['piece_jointe', 'annexe', 'complement'])],
        ]);

        $file = $this->firstUploadedFile($request, 'file');
        abort_unless($file, 422, 'Fichier requis.');

        $attachment = $this->workflow->addAttachment(
            $document,
            $request->user(),
            $file,
            $data['kind'] ?? 'piece_jointe',
        );

        return response()->json($attachment, 201);
    }

    public function createInstruction(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless(
            $request->user()->can('instructions.manage') || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'comment_id' => ['nullable', 'exists:comments,id'],
            'assignee_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(['normale', 'importante', 'urgente', 'tres_urgente'])],
            'structure_id' => ['nullable', 'exists:structures,id'],
        ]);

        $comment = ! empty($data['comment_id'])
            ? Comment::query()->where('document_id', $document->id)->findOrFail($data['comment_id'])
            : Comment::query()->create([
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'kind' => 'observation',
                'body' => $data['body'],
            ]);

        $instruction = $this->workflow->createInstructionFromComment(
            $document,
            $comment,
            $request->user(),
            User::query()->findOrFail($data['assignee_id']),
            $data,
        );

        return response()->json($instruction, 201);
    }

    public function downloadVersion(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless($version->document_id === $document->id, 404);
        $user = User::query()->findOrFail($request->integer('user'));
        $this->access->authorize($user, $document);
        abort_if($document->antivirus_status === 'infected', 422, 'Fichier identifié comme dangereux : accès refusé.');
        $this->signedDownloads->consume($request, $document, $user, [
            SignedDownloadService::PURPOSE_DOWNLOAD,
            SignedDownloadService::PURPOSE_ONLYOFFICE,
        ]);
        abort_unless($this->storage->exists($version->disk, $version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->original_name);
    }

    public function streamVersion(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless($version->document_id === $document->id, 404);
        $user = User::query()->findOrFail($request->integer('user'));
        $this->access->authorize($user, $document);
        abort_if($document->antivirus_status === 'infected', 422, 'Fichier identifié comme dangereux : accès refusé.');
        $this->signedDownloads->consume($request, $document, $user, [
            SignedDownloadService::PURPOSE_STREAM,
        ]);
        abort_unless($this->storage->exists($version->disk, $version->path), 404);

        return Storage::disk($version->disk)->response($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function downloadAttachment(Request $request, Document $document, DocumentAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->document_id === $document->id, 404);
        $user = User::query()->findOrFail($request->integer('user'));
        $this->access->authorize($user, $document);
        abort_if($document->antivirus_status === 'infected', 422, 'Fichier identifié comme dangereux : accès refusé.');
        $this->signedDownloads->consume($request, $document, $user, [
            SignedDownloadService::PURPOSE_DOWNLOAD,
        ]);
        abort_unless($this->storage->exists($attachment->disk, $attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function firstUploadedFile(Request $request, string $key): ?UploadedFile
    {
        $file = $request->file($key);

        if ($file instanceof UploadedFile) {
            return $file;
        }

        if (is_array($file)) {
            $first = reset($file);

            return $first instanceof UploadedFile ? $first : null;
        }

        return null;
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

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile
        ));
    }

    /**
     * Normalise transmit_to / transmit_to_ids (ou to_user_id / to_user_ids) en liste d’IDs uniques ordonnés.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function normalizeTransmitRecipientIds(array $data): array
    {
        $ids = [];

        if (! empty($data['transmit_to_ids']) && is_array($data['transmit_to_ids'])) {
            $ids = $data['transmit_to_ids'];
        } elseif (! empty($data['to_user_ids']) && is_array($data['to_user_ids'])) {
            $ids = $data['to_user_ids'];
        } elseif (isset($data['transmit_to']) && $data['transmit_to'] !== '' && $data['transmit_to'] !== null) {
            $ids = is_array($data['transmit_to']) ? $data['transmit_to'] : [$data['transmit_to']];
        } elseif (isset($data['to_user_id']) && $data['to_user_id'] !== '' && $data['to_user_id'] !== null) {
            $ids = is_array($data['to_user_id']) ? $data['to_user_id'] : [$data['to_user_id']];
        }

        $normalized = [];
        $seen = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId <= 0 || isset($seen[$intId])) {
                continue;
            }
            $seen[$intId] = true;
            $normalized[] = $intId;
        }

        return $normalized;
    }

    /**
     * @param  list<int>  $ids
     * @return list<User>
     */
    private function usersInOrder(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $byId = User::query()->whereIn('id', $ids)->get()->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            $user = $byId->get($id);
            if ($user) {
                $ordered[] = $user;
            }
        }

        return $ordered;
    }
}
