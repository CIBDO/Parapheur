<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentDomain;
use App\Policies\DocumentPolicy;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GedDomainController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return response()->json([
            'data' => DocumentDomain::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageDomains($request->user()), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:document_domains,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $domain = DocumentDomain::query()->create($data + ['is_active' => $data['is_active'] ?? true]);
        $this->audit->log('document_domain.created', $domain);

        return response()->json($domain, 201);
    }

    public function update(Request $request, DocumentDomain $domain): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageDomains($request->user()), 403);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('document_domains', 'code')->ignore($domain->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $domain->update($data);
        $this->audit->log('document_domain.updated', $domain);

        return response()->json($domain->fresh());
    }

    public function destroy(Request $request, DocumentDomain $domain): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageDomains($request->user()), 403);

        if ($domain->documents()->exists()) {
            return response()->json(['message' => 'Des documents utilisent encore ce domaine.'], 422);
        }

        $this->audit->log('document_domain.deleted', $domain);
        $domain->delete();

        return response()->json(['message' => 'Domaine supprimé.']);
    }
}
