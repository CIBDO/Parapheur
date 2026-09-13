<?php

namespace App\Http\Controllers\Api\Library;

use App\Http\Controllers\Controller;
use App\Models\BibliographicReference;
use App\Services\BibliographicReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibliographicReferenceController extends Controller
{
    public function __construct(
        private readonly BibliographicReferenceService $library,
    ) {}

    public function types(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('library.access') || $request->user()->can('admin.access'), 403);

        return response()->json(['data' => $this->library->types()]);
    }

    public function collections(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('library.access') || $request->user()->can('admin.access'), 403);

        return response()->json(['data' => $this->library->collectionsFor($request->user())]);
    }

    public function storeCollection(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('library.manage_own') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string'],
        ]);

        return response()->json($this->library->createCollection($request->user(), $data), 201);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('library.access') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'reference_type_id' => ['nullable', 'exists:reference_types,id'],
            'publication_year' => ['nullable', 'integer'],
            'tag' => ['nullable', 'string'],
            'scope' => ['nullable', 'in:mine,institutional,pending,all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->library->search($request->user(), $data, $data['per_page'] ?? 25);

        return response()->json($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('library.manage_own') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'reference_type_id' => ['nullable', 'exists:reference_types,id'],
            'institutional_author' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'publication_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:10'],
            'abstract' => ['nullable', 'string'],
            'source_url' => ['nullable', 'string', 'max:1000'],
            'doi' => ['nullable', 'string', 'max:100'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'issn' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'authors' => ['nullable', 'array'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['integer'],
            'document_id' => ['nullable', 'exists:documents,id'],
        ]);

        $result = $this->library->create($request->user(), $data);

        return response()->json([
            ...$result['reference']->toArray(),
            'duplicates_warning' => count($result['duplicates']) > 0,
            'duplicates' => $result['duplicates'],
        ], 201);
    }

    public function show(Request $request, BibliographicReference $reference): JsonResponse
    {
        abort_unless($request->user()->can('library.access') || $request->user()->can('admin.access'), 403);

        $allowed = (int) $reference->owner_id === (int) $request->user()->id
            || $reference->publication_status === 'institutional'
            || $request->user()->can('admin.access')
            || ($reference->publication_status === 'proposed' && ($request->user()->can('library.moderate') || $request->user()->can('admin.access')));

        abort_unless($allowed, 403);

        $payload = $reference->load(['type', 'authors', 'tags', 'collections'])->toArray();
        $payload['my_note'] = $this->library->myNote($reference, $request->user());

        return response()->json($payload);
    }

    public function propose(Request $request, BibliographicReference $reference): JsonResponse
    {
        abort_unless($request->user()->can('library.manage_own') || $request->user()->can('admin.access'), 403);

        try {
            $ref = $this->library->proposeInstitutional($reference, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($ref);
    }

    public function moderate(Request $request, BibliographicReference $reference): JsonResponse
    {
        abort_unless($request->user()->can('library.moderate') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $ref = $this->library->moderate($reference, $request->user(), (bool) $data['approve'], $data['note'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($ref);
    }

    public function upsertNote(Request $request, BibliographicReference $reference): JsonResponse
    {
        abort_unless($request->user()->can('library.access') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        return response()->json($this->library->upsertNote($reference, $request->user(), $data['body']));
    }
}
