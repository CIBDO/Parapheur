<?php

namespace Tests\Feature;

use App\Enums\DocumentAccessAbility;
use App\Enums\DocumentFolderKind;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentPublicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\PublicationWorkflow;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use App\Services\DocumentFolderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GedFolderAclAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_catalog_endpoint_returns_facets_and_hides_drafts(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        Document::query()->create([
            'reference' => 'CAT-DRAFT-1',
            'object' => 'Brouillon facette',
            'title' => 'Brouillon facette',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'status' => DocumentStatus::Brouillon->value,
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Courrier->value,
            'publication_status' => DocumentPublicationStatus::Brouillon->value,
            'document_date' => now()->toDateString(),
            'current_version' => 0,
            'archive_status' => 'actif',
        ]);

        Document::query()->create([
            'reference' => 'CAT-DEP-1',
            'object' => 'Déposé facette',
            'title' => 'Déposé facette',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'status' => DocumentStatus::Depose->value,
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Ged->value,
            'publication_status' => DocumentPublicationStatus::Depose->value,
            'document_date' => now()->toDateString(),
            'current_version' => 0,
            'archive_status' => 'actif',
        ]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/catalog')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'total',
                'facets' => ['origin', 'publication_status', 'confidentiality', 'structure', 'document_type'],
            ])
            ->assertJsonFragment(['reference' => 'CAT-DEP-1'])
            ->assertJsonMissing(['reference' => 'CAT-DRAFT-1']);
    }

    public function test_gated_folder_hides_documents_from_unauthorized_user(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $root = DocumentFolder::query()->where('code', 'ROOT_DOC')->firstOrFail();

        $folders = app(DocumentFolderService::class);
        $secret = $folders->create($admin, [
            'parent_id' => $root->id,
            'code' => 'SECRET_CAB',
            'name' => 'Dossier confidentiel cabinet',
            'kind' => DocumentFolderKind::Restricted->value,
            'default_publication_workflow' => PublicationWorkflow::Controlled->value,
        ]);

        // Grant view only to admin (gated folder)
        $folders->grantAccess(
            $secret,
            $admin,
            DocumentAccessAbility::View,
            userId: $admin->id,
        );

        $doc = Document::query()->create([
            'reference' => 'ACL-FOLDER-1',
            'object' => 'Note cabinet gated',
            'title' => 'Note cabinet gated',
            'document_type_id' => $type->id,
            'structure_id' => $agent->structure_id,
            'folder_id' => $secret->id,
            'author_id' => $admin->id,
            'status' => DocumentStatus::Depose->value,
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Ged->value,
            'publication_status' => DocumentPublicationStatus::Publie->value,
            'published_at' => now(),
            'document_date' => now()->toDateString(),
            'current_version' => 0,
            'archive_status' => 'actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ged/documents?catalog=institutional&q=ACL-FOLDER-1')
            ->assertOk()
            ->assertJsonFragment(['id' => $doc->id]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents?catalog=institutional&q=ACL-FOLDER-1')
            ->assertOk()
            ->assertJsonMissing(['reference' => 'ACL-FOLDER-1']);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/folders/'.$secret->id)
            ->assertForbidden();

        $tree = $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/folders')
            ->assertOk()
            ->json('data');

        $this->assertFalse($this->treeContainsCode($tree, 'SECRET_CAB'));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function treeContainsCode(array $nodes, string $code): bool
    {
        foreach ($nodes as $node) {
            if (($node['code'] ?? null) === $code) {
                return true;
            }
            if (! empty($node['children']) && $this->treeContainsCode($node['children'], $code)) {
                return true;
            }
        }

        return false;
    }
}
