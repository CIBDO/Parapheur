<?php

namespace Tests\Feature;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentPublicationStatus;
use App\Models\Document;
use App\Models\DocumentDomain;
use App\Models\DocumentFolder;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstitutionalDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_domains_and_folders_are_seeded(): void
    {
        $this->assertTrue(DocumentDomain::query()->where('code', 'TRESORERIE')->exists());
        $this->assertTrue(DocumentFolder::query()->where('code', 'ROOT_DOC')->exists());
        $this->assertTrue(DocumentType::query()->where('code', 'CIRCULAIRE')->where('scope', 'institutional')->exists());
    }

    public function test_agent_can_deposit_in_folder_without_auto_publish(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->where('code', 'NOTE')->firstOrFail();
        $folder = DocumentFolder::query()->where('code', 'ROOT_DOC')->firstOrFail();
        $domain = DocumentDomain::query()->where('code', 'INFORMATIQUE')->firstOrFail();

        $response = $this->actingAs($agent, 'sanctum')
            ->post('/api/ged/documents', [
                'object' => 'Circulaire test dépôt',
                'title' => 'Circulaire test',
                'document_type_id' => $type->id,
                'folder_id' => $folder->id,
                'domain_id' => $domain->id,
                'exercise_year' => 2026,
                'confidentiality' => 'normal',
                'main_file' => UploadedFile::fake()->create('circulaire.pdf', 80, 'application/pdf'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('publication_status', DocumentPublicationStatus::Depose->value)
            ->assertJsonPath('folder.id', $folder->id)
            ->assertJsonPath('domain.id', $domain->id);

        $this->assertDatabaseHas('documents', [
            'object' => 'Circulaire test dépôt',
            'origin' => DocumentOrigin::Ged->value,
            'publication_status' => 'depose',
            'folder_id' => $folder->id,
        ]);
    }

    public function test_unauthorized_user_cannot_create_document(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $agent->syncRoles([]);
        $agent->syncPermissions(['ged.view', 'ged.search', 'ged.download']);
        $type = DocumentType::query()->firstOrFail();

        $this->actingAs($agent->fresh(), 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Interdit',
                'document_type_id' => $type->id,
            ])
            ->assertForbidden();
    }

    public function test_multi_file_deposit_creates_multiple_documents(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $folder = DocumentFolder::query()->where('code', 'ROOT_DOC')->firstOrFail();

        $response = $this->actingAs($agent, 'sanctum')
            ->post('/api/ged/documents', [
                'object' => 'Lot documents',
                'document_type_id' => $type->id,
                'folder_id' => $folder->id,
                'files' => [
                    UploadedFile::fake()->create('a.pdf', 20, 'application/pdf'),
                    UploadedFile::fake()->create('b.pdf', 20, 'application/pdf'),
                ],
            ]);

        $response->assertCreated()->assertJsonPath('count', 2);
        $this->assertSame(2, Document::query()->where('object', 'like', 'Lot documents%')->count());
    }

    public function test_publication_workflow_controlled(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $create = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc à publier',
                'document_type_id' => $type->id,
                'publication_workflow' => 'controlled',
                'confidentiality' => 'normal',
            ])
            ->assertCreated();

        $id = $create->json('id');

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/ged/documents/{$id}/submit-publication")
            ->assertOk()
            ->assertJsonPath('publication_status', 'en_verification');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/ged/documents/{$id}/approve-publication")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/ged/documents/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('publication_status', 'publie');

        $this->assertNotNull(Document::query()->find($id)?->published_at);
    }

    public function test_reject_publication_requires_motif(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc rejet',
                'document_type_id' => $type->id,
            ])
            ->json('id');

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/ged/documents/{$id}/submit-publication")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/ged/documents/{$id}/reject-publication", [])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/ged/documents/{$id}/reject-publication", ['motif' => 'Incomplet'])
            ->assertOk()
            ->assertJsonPath('publication_status', 'rejete');
    }

    public function test_search_by_domain_and_structure(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $domain = DocumentDomain::query()->where('code', 'COMPTA_PUB')->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'État comptable Q1',
                'document_type_id' => $type->id,
                'domain_id' => $domain->id,
                'publication_workflow' => 'simple',
            ])
            ->assertCreated();

        // Publication simple si l'agent a ged.publish — sinon forcer via admin
        $doc = Document::query()->where('object', 'État comptable Q1')->firstOrFail();
        $doc->update([
            'publication_status' => DocumentPublicationStatus::Publie->value,
            'published_at' => now(),
        ]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents?domain_id='.$domain->id.'&q=comptable')
            ->assertOk()
            ->assertJsonFragment(['object' => 'État comptable Q1']);
    }

    public function test_folder_tree_endpoint(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/folders')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_unpublished_not_listed_for_other_agents(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Brouillon secret admin',
                'document_type_id' => $type->id,
                'confidentiality' => 'normal',
            ])
            ->assertCreated();

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents?q=Brouillon+secret+admin')
            ->assertOk()
            ->assertJsonMissing(['object' => 'Brouillon secret admin']);
    }
}
