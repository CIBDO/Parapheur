<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\KnownError;
use App\Models\Problem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnownErrorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.view')
            || $request->user()->can('ticket.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $query = KnownError::query()
            ->with(['problem:id,number,title'])
            ->orderByDesc('updated_at');

        if ($request->filled('problem_id')) {
            $query->where('problem_id', (int) $request->input('problem_id'));
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($q = $request->input('q')) {
            $like = '%'.addcslashes((string) $q, '%_\\').'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('title', 'like', $like)
                    ->orWhere('symptoms', 'like', $like)
                    ->orWhere('workaround', 'like', $like);
            });
        }

        return response()->json($query->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.create')
            || $request->user()->can('problem.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'problem_id' => 'nullable|exists:problems,id',
            'title' => 'required|string|max:500',
            'symptoms' => 'nullable|string',
            'workaround' => 'nullable|string',
            'solution' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);

        $row = KnownError::query()->create([
            ...$validated,
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ]);

        return response()->json($row->load('problem:id,number,title'), 201);
    }

    public function show(Request $request, KnownError $knownError): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.view')
            || $request->user()->can('ticket.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($knownError->load('problem:id,number,title'));
    }

    public function update(Request $request, KnownError $knownError): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'problem_id' => 'nullable|exists:problems,id',
            'title' => 'sometimes|required|string|max:500',
            'symptoms' => 'nullable|string',
            'workaround' => 'nullable|string',
            'solution' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);

        $knownError->update($validated);

        return response()->json($knownError->fresh('problem:id,number,title'));
    }

    public function destroy(Request $request, KnownError $knownError): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $knownError->delete();

        return response()->json(['message' => 'Erreur connue supprimée']);
    }

    public function storeForProblem(Request $request, Problem $problem): JsonResponse
    {
        abort_unless(
            $request->user()->can('problem.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'symptoms' => 'nullable|string',
            'workaround' => 'nullable|string',
            'solution' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);

        $row = KnownError::query()->create([
            'problem_id' => $problem->id,
            'title' => $validated['title'],
            'symptoms' => $validated['symptoms'] ?? null,
            'workaround' => $validated['workaround'] ?? ($problem->workaround),
            'solution' => $validated['solution'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ]);

        return response()->json($row, 201);
    }
}
