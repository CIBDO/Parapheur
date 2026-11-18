<?php

namespace Tests\Feature;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentPublicationStatus;
use App\Enums\DocumentStatus;
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

class GedInstitutionalCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_institutional_catalog_hides_brouillons_and_null_publication(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        Document::query()->create([
            'reference' => 'GED-DRAFT-001',
            'object' => 'Brouillon invisible catalogue',
            'title' => 'Brouillon invisible catalogue',
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
            'reference' => 'GED-NULL-001',
            'object' => 'Sans statut publication',
            'title' => 'Sans statut publication',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'status' => DocumentStatus::Brouillon->value,
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Parapheur->value,
            'publication_status' => null,
            'document_date' => now()->toDateString(),
            'current_version' => 0,
            'archive_status' => 'actif',
        ]);

        Document::query()->create([
            'reference' => 'GED-DEP-001',
            'object' => 'Document versé visible',
            'title' => 'Document versé visible',
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
            ->getJson('/api/ged/documents?catalog=institutional')
            ->assertOk()
            ->assertJsonFragment(['reference' => 'GED-DEP-001'])
            ->assertJsonMissing(['reference' => 'GED-DRAFT-001'])
            ->assertJsonMissing(['reference' => 'GED-NULL-001']);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents?catalog=all')
            ->assertOk()
            ->assertJsonFragment(['reference' => 'GED-DRAFT-001'])
            ->assertJsonFragment(['reference' => 'GED-NULL-001']);
    }

    public function test_structure_folder_retired_when_structure_deactivated(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = \App\Models\StructureType::query()->firstOrFail();

        $structure = Structure::query()->create([
            'code' => 'TMPSVC',
            'name' => 'Service Temporaire',
            'structure_type_id' => $type->id,
            'parent_id' => Structure::query()->where('code', 'DGTCP')->value('id'),
            'is_active' => true,
            'sort_order' => 50,
        ]);

        app(DocumentFolderService::class)->ensureStructureFolder($structure, $admin);

        $this->assertDatabaseHas('document_folders', [
            'code' => 'STR_TMPSVC',
            'structure_id' => $structure->id,
            'is_archived' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/structures/'.$structure->id, [
                'code' => 'TMPSVC',
                'name' => 'Service Temporaire',
                'structure_type_id' => $type->id,
                'parent_id' => $structure->parent_id,
                'is_active' => false,
                'sort_order' => 50,
            ])
            ->assertOk();

        $folder = DocumentFolder::withTrashed()
            ->where('code', 'STR_TMPSVC')
            ->first();

        $this->assertNotNull($folder);
        $this->assertTrue(
            $folder->trashed() || $folder->is_archived,
            'Le dossier GED doit être soft-deleted ou archivé après désactivation.'
        );

        $tree = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ged/folders')
            ->assertOk()
            ->json('data');

        $codes = $this->collectFolderCodes($tree);
        $this->assertNotContains('STR_TMPSVC', $codes);
    }

    public function test_structure_delete_removes_empty_ged_folder(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = \App\Models\StructureType::query()->firstOrFail();

        $structure = Structure::query()->create([
            'code' => 'DELSVC',
            'name' => 'Service À Supprimer',
            'structure_type_id' => $type->id,
            'parent_id' => Structure::query()->where('code', 'DGTCP')->value('id'),
            'is_active' => true,
            'sort_order' => 51,
        ]);

        app(DocumentFolderService::class)->ensureStructureFolder($structure, $admin);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/structures/'.$structure->id)
            ->assertOk();

        $this->assertSoftDeleted('document_folders', ['code' => 'STR_DELSVC']);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<string>
     */
    private function collectFolderCodes(array $nodes): array
    {
        $codes = [];
        foreach ($nodes as $node) {
            if (! empty($node['code'])) {
                $codes[] = $node['code'];
            }
            if (! empty($node['children']) && is_array($node['children'])) {
                $codes = array_merge($codes, $this->collectFolderCodes($node['children']));
            }
        }

        return $codes;
    }
}
