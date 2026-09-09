<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpectedAction;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use App\Services\ParapheurService;
use App\Services\PrivateDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
        private readonly ParapheurService $parapheur,
        private readonly PrivateDocumentStorage $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $folder = $request->string('folder')->toString() ?: null;

        return response()->json($this->parapheur->listFolder($user, $folder));
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
            'keywords' => ['nullable', 'array'],
            'main_file' => ['nullable', 'file', 'max:20480'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480'],
            'transmit_to' => ['nullable', 'exists:users,id'],
            'transmit_message' => ['nullable', 'string'],
        ]);

        $document = $this->workflow->createDraft(
            $request->user(),
            $data,
            $request->file('main_file'),
            $request->file('attachments', []) ?: [],
        );

        if (! empty($data['transmit_to'])) {
            $to = User::query()->findOrFail($data['transmit_to']);
            $action = ExpectedAction::from($data['expected_action'] ?? ExpectedAction::Consultation->value);
            $document = $this->workflow->submitAndTransmit(
                $document,
                $request->user(),
                $to,
                $action,
                $data['transmit_message'] ?? null,
            );
        }

        return response()->json($document, 201);
    }

    public function show(Document $document): JsonResponse
    {
        $document->load([
            'type',
            'structure',
            'author',
            'currentAssignee',
            'versions.uploader',
            'attachments',
            'comments.user',
            'actions.actor',
            'actions.delegator',
            'visas.user',
            'approvals.user',
            'transmissions.fromUser',
            'transmissions.toUser',
            'instructions.assignee',
            'workflowInstance',
        ]);

        $document->setAttribute('versions', $document->versions->map(function (DocumentVersion $version) use ($document) {
            $payload = $version->toArray();
            $payload['download_url'] = URL::temporarySignedRoute(
                'documents.version.download',
                now()->addMinutes(30),
                ['document' => $document->id, 'version' => $version->id]
            );
            $payload['stream_url'] = URL::temporarySignedRoute(
                'documents.version.stream',
                now()->addMinutes(30),
                ['document' => $document->id, 'version' => $version->id]
            );

            return $payload;
        }));

        return response()->json($document);
    }

    public function transmit(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'exists:users,id'],
            'expected_action' => ['required', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'message' => ['nullable', 'string'],
        ]);

        $document = $this->workflow->submitAndTransmit(
            $document,
            $request->user(),
            User::query()->findOrFail($data['to_user_id']),
            ExpectedAction::from($data['expected_action']),
            $data['message'] ?? null,
        );

        return response()->json($document);
    }

    public function comment(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'kind' => ['nullable', Rule::in(['general', 'avis', 'observation'])],
        ]);

        $comment = $this->workflow->addComment(
            $document,
            $request->user(),
            $data['body'],
            $data['kind'] ?? 'general',
        );

        return response()->json($comment, 201);
    }

    public function returnCorrection(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate(['comment' => ['required', 'string']]);

        return response()->json(
            $this->workflow->returnForCorrection($document, $request->user(), $data['comment'])
        );
    }

    public function vise(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->vise($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function validateAction(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->validateDocument($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function reject(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate(['comment' => ['required', 'string']]);

        return response()->json(
            $this->workflow->reject($document, $request->user(), $data['comment'])
        );
    }

    public function archive(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string']]);

        return response()->json(
            $this->workflow->archive($document, $request->user(), $data['comment'] ?? null)
        );
    }

    public function addVersion(Request $request, Document $document): JsonResponse
    {
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

    public function createInstruction(Request $request, Document $document): JsonResponse
    {
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
        abort_unless($this->storage->exists($version->disk, $version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->original_name);
    }

    public function streamVersion(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless($version->document_id === $document->id, 404);
        abort_unless($this->storage->exists($version->disk, $version->path), 404);

        return Storage::disk($version->disk)->response($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type ?: 'application/octet-stream',
        ]);
    }
}
