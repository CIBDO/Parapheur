<?php

namespace App\Http\Controllers\Api\Ged;

use App\Enums\DocumentArchiveStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentPriority;
use App\Enums\DocumentPublicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExpectedAction;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GedCatalogController extends Controller
{
    public function __construct(
        private readonly DocumentSearchService $search,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'type_id' => ['nullable', 'exists:document_types,id'],
            'category_id' => ['nullable', 'exists:document_categories,id'],
            'domain_id' => ['nullable', 'exists:document_domains,id'],
            'folder_id' => ['nullable', 'exists:document_folders,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'author_id' => ['nullable', 'exists:users,id'],
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
            'status' => ['nullable', Rule::in(array_column(DocumentStatus::cases(), 'value'))],
            'publication_status' => ['nullable', Rule::in(array_column(DocumentPublicationStatus::cases(), 'value'))],
            'priority' => ['nullable', Rule::in(array_column(DocumentPriority::cases(), 'value'))],
            'confidentiality' => ['nullable', Rule::in(array_column(DocumentConfidentiality::cases(), 'value'))],
            'origin' => ['nullable', Rule::in(array_column(DocumentOrigin::cases(), 'value'))],
            'archive_status' => ['nullable', Rule::in(array_column(DocumentArchiveStatus::cases(), 'value'))],
            'expected_action' => ['nullable', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'tag' => ['nullable', 'string'],
            'document_date_from' => ['nullable', 'date'],
            'document_date_to' => ['nullable', 'date'],
            'exercice' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'exercise_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'unclassified' => ['nullable', 'boolean'],
            'scope' => ['nullable', Rule::in(['mine', 'shared', 'to_process', 'to_validate', 'published', 'my_deposits', 'archives', 'institutional', 'all'])],
            'catalog' => ['nullable', Rule::in(['institutional', 'work', 'all', 'default'])],
            'sort' => ['nullable', 'string'],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($data['per_page'] ?? 25);
        unset($data['per_page'], $data['page']);

        if (! isset($data['catalog'])) {
            $data['catalog'] = 'institutional';
        }

        return response()->json($this->search->catalog($user, $data, $perPage));
    }
}
