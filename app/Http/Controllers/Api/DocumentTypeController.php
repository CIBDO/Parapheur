<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            DocumentType::query()->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $type = DocumentType::query()->create($data);

        return response()->json($type, 201);
    }

    public function update(Request $request, DocumentType $documentType): JsonResponse
    {
        $documentType->update($this->validated($request, $documentType));

        return response()->json($documentType->fresh());
    }

    public function destroy(DocumentType $documentType): JsonResponse
    {
        if ($documentType->documents()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un type déjà utilisé par des documents.',
            ], 422);
        }

        $documentType->delete();

        return response()->json(['message' => 'Type de document supprimé']);
    }

    private function validated(Request $request, ?DocumentType $documentType = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('document_types', 'code')->ignore($documentType?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($documentType?->is_active ?? true);
        $data['sort_order'] = $data['sort_order'] ?? ($documentType?->sort_order ?? 0);

        return $data;
    }
}
