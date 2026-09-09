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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'main_file' => ['nullable', 'file', 'max:20480'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480'],
            'transmit_to' => ['nullable', 'exists:users,id'],
            'transmit_message' => ['nullable', 'string'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
        ]);

        if (isset($data['keywords']) && is_string($data['keywords'])) {
            $decoded = json_decode($data['keywords'], true);
            $data['keywords'] = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', $data['keywords']))));
        }

        $document = $this->workflow->createDraft(
            $request->user(),
            $data,
            $request->file('main_file'),
            $request->file('attachments', []) ?: [],
        );

        if (! empty($data['workflow_id']) || ! empty($data['transmit_to'])) {
            $to = ! empty($data['transmit_to'])
                ? User::query()->findOrFail($data['transmit_to'])
                : null;
            $action = ExpectedAction::from($data['expected_action'] ?? ExpectedAction::Consultation->value);
            $document = $this->workflow->submitAndTransmit(
                $document,
                $request->user(),
                $to,
                $action,
                $data['transmit_message'] ?? null,
                isset($data['workflow_id']) ? (int) $data['workflow_id'] : null,
            );
        }

        return response()->json($document, 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);

        $document->load([
            'type',
            'structure',
            'author',
            'currentAssignee',
            'versions.uploader',
            'attachments.uploader',
            'comments.user',
            'actions.actor',
            'actions.delegator',
            'visas.user',
            'approvals.user',
            'transmissions.fromUser',
            'transmissions.toUser',
            'instructions.assignee',
            'workflowInstance.workflow.steps',
        ]);

        $userId = $request->user()->id;

        $versions = $document->versions->map(function (DocumentVersion $version) use ($document, $userId) {
            $payload = $version->toArray();
            $params = [
                'document' => $document->id,
                'version' => $version->id,
                'user' => $userId,
            ];
            $payload['download_url'] = URL::temporarySignedRoute(
                'documents.version.download',
                now()->addMinutes(30),
                $params
            );
            $payload['stream_url'] = URL::temporarySignedRoute(
                'documents.version.stream',
                now()->addMinutes(30),
                $params
            );
            $payload['preview'] = $this->preview->preview(
                $version,
                $payload['stream_url'],
                $payload['download_url'],
            );

            return $payload;
        })->values();

        $attachments = $document->attachments->map(function (DocumentAttachment $attachment) use ($document, $userId) {
            $payload = $attachment->toArray();
            $payload['download_url'] = URL::temporarySignedRoute(
                'documents.attachment.download',
                now()->addMinutes(30),
                [
                    'document' => $document->id,
                    'attachment' => $attachment->id,
                    'user' => $userId,
                ]
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
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'to_user_id' => ['nullable', 'exists:users,id'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'expected_action' => ['required', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'message' => ['nullable', 'string'],
        ]);

        if (empty($data['to_user_id']) && empty($data['workflow_id'])) {
            return response()->json(['message' => 'Destinataire ou circuit requis.'], 422);
        }

        $document = $this->workflow->submitAndTransmit(
            $document,
            $request->user(),
            ! empty($data['to_user_id']) ? User::query()->findOrFail($data['to_user_id']) : null,
            ExpectedAction::from($data['expected_action']),
            $data['message'] ?? null,
            isset($data['workflow_id']) ? (int) $data['workflow_id'] : null,
        );

        return response()->json($document);
    }

    public function reassign(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
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
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->acknowledge($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function hold(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->putOnHold($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function classify(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
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

        try {
            $comment = $this->workflow->addComment(
                $document,
                $request->user(),
                $data['body'],
                $data['kind'] ?? 'general',
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($comment, 201);
    }

    public function returnCorrection(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['required', 'string']]);

        return response()->json(
            $this->workflow->returnForCorrection($document, $request->user(), $data['comment'])
        );
    }

    public function requestComplement(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.act') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['required', 'string']]);

        return response()->json(
            $this->workflow->requestComplement($document, $request->user(), $data['comment'])
        );
    }

    public function vise(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.vise') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->vise($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function validateAction(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($request->user()->can('documents.validate') || $request->user()->can('admin.access'), 403);
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->validateDocument($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function reject(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
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
        $this->access->authorize($request->user(), $document);
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
            'file' => ['required', 'file', 'max:20480'],
            'change_note' => ['nullable', 'string'],
        ]);

        $version = $this->workflow->addVersion(
            $document,
            $request->user(),
            $request->file('file'),
            $data['change_note'] ?? null,
        );

        return response()->json($version, 201);
    }

    public function addAttachment(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'kind' => ['nullable', Rule::in(['piece_jointe', 'annexe', 'complement'])],
        ]);

        $attachment = $this->workflow->addAttachment(
            $document,
            $request->user(),
            $request->file('file'),
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
        abort_unless($this->storage->exists($version->disk, $version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->original_name);
    }

    public function streamVersion(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless($version->document_id === $document->id, 404);
        $user = User::query()->findOrFail($request->integer('user'));
        $this->access->authorize($user, $document);
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
        abort_unless($this->storage->exists($attachment->disk, $attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
