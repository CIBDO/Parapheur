<?php

namespace App\Http\Controllers\Api\Library;

use App\Http\Controllers\Controller;
use App\Models\BibliographicReference;
use App\Models\ReferenceCollection;
use App\Services\BibliographicReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceCollectionController extends Controller
{
    public function __construct(
        private readonly BibliographicReferenceService $references,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('library.access') || $request->user()->can('admin.access'), 403);

        $collections = ReferenceCollection::query()
            ->where('owner_id', $request->user()->id)
            ->withCount('references')
            ->orderBy('name')
            ->get();

        return response()->json($collections);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('library.manage_own') || $request->user()->can('admin.access'),
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string', 'max:50'],
        ]);

        $collection = $this->references->createCollection($request->user(), $data);

        return response()->json($collection, 201);
    }

    public function show(Request $request, ReferenceCollection $collection): JsonResponse
    {
        abort_unless($request->user()->can('library.access') || $request->user()->can('admin.access'), 403);
        abort_unless(
            (int) $collection->owner_id === (int) $request->user()->id || $request->user()->can('admin.access'),
            403
        );

        return response()->json($collection->load(['references.type', 'references.authors']));
    }

    public function attach(Request $request, ReferenceCollection $collection): JsonResponse
    {
        abort_unless(
            $request->user()->can('library.manage_own') || $request->user()->can('admin.access'),
            403
        );
        abort_unless((int) $collection->owner_id === (int) $request->user()->id || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'reference_id' => ['required', 'exists:bibliographic_references,id'],
        ]);

        $reference = BibliographicReference::query()->findOrFail($data['reference_id']);
        abort_unless(
            (int) $reference->owner_id === (int) $request->user()->id || $request->user()->can('admin.access'),
            403
        );

        $this->references->attachToCollection($collection, $reference);

        return response()->json(['message' => 'Référence ajoutée à la collection.']);
    }

    public function detach(Request $request, ReferenceCollection $collection, BibliographicReference $reference): JsonResponse
    {
        abort_unless(
            $request->user()->can('library.manage_own') || $request->user()->can('admin.access'),
            403
        );
        abort_unless((int) $collection->owner_id === (int) $request->user()->id || $request->user()->can('admin.access'), 403);

        $this->references->detachFromCollection($collection, $reference);

        return response()->json(['message' => 'Référence retirée de la collection.']);
    }

    public function destroy(Request $request, ReferenceCollection $collection): JsonResponse
    {
        abort_unless(
            $request->user()->can('library.manage_own') || $request->user()->can('admin.access'),
            403
        );
        abort_unless((int) $collection->owner_id === (int) $request->user()->id || $request->user()->can('admin.access'), 403);

        $collection->delete();

        return response()->json(['message' => 'Collection supprimée.']);
    }
}
