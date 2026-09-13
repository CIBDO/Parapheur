<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\Correspondent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Correspondent::query()->with('contacts')->where('is_active', true);

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', $q)
                    ->orWhere('organization', 'like', $q);
            });
        }

        return response()->json($query->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'function' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.type' => 'required_with:contacts|string',
            'contacts.*.value' => 'required_with:contacts|string',
            'contacts.*.is_primary' => 'nullable|boolean',
        ]);

        $contacts = $validated['contacts'] ?? [];
        unset($validated['contacts']);

        $correspondent = Correspondent::query()->create($validated);

        foreach ($contacts as $contact) {
            $correspondent->contacts()->create($contact);
        }

        return response()->json($correspondent->load('contacts'), 201);
    }

    public function show(Correspondent $correspondent): JsonResponse
    {
        return response()->json($correspondent->load('contacts'));
    }

    public function update(Request $request, Correspondent $correspondent): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:255',
            'function' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $correspondent->update($validated);

        return response()->json($correspondent->fresh('contacts'));
    }

    public function destroy(Correspondent $correspondent): JsonResponse
    {
        $correspondent->update(['is_active' => false]);

        return response()->json(['message' => 'Correspondant désactivé']);
    }
}
