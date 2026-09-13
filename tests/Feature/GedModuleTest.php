<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Models\ClassificationNode;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GedModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_agent_can_create_ged_document_without_workflow(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $response = $this->actingAs($agent, 'sanctum')
            ->post('/api/ged/documents', [
                'object' => 'Note GED interconnexion',
                'title' => 'Note GED',
                'document_type_id' => $type->id,
                'confidentiality' => 'normal',
                'tags' => json_encode(['SIGRAC', 'Douanes']),
                'main_file' => UploadedFile::fake()->create('note.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('object', 'Note GED interconnexion')
            ->assertJsonPath('origin', DocumentOrigin::Ged->value);

        $this->assertDatabaseHas('documents', [
            'object' => 'Note GED interconnexion',
            'origin' => 'ged',
        ]);

        $doc = Document::query()->where('object', 'Note GED interconnexion')->first();
        $this->assertNotNull($doc);
        $this->assertTrue($doc->tags()->where('slug', 'sigrac')->exists());
    }

    public function test_search_does_not_leak_tres_confidentiel_to_agent(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        Document::query()->create([
            'reference' => 'GED-TC-001',
            'object' => 'Secret absolu',
            'title' => 'Secret absolu',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $admin->id,
            'status' => DocumentStatus::Brouillon->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::TresConfidentiel->value,
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Ged->value,
            'document_date' => now()->toDateString(),
            'current_version' => 0,
            'archive_status' => 'actif',
        ]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents?q=Secret')
            ->assertOk()
            ->assertJsonMissing(['reference' => 'GED-TC-001']);
    }

    public function test_classification_assign_and_filter(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $node = ClassificationNode::query()->where('code', 'NOTES')->first()
            ?? ClassificationNode::query()->where('code', 'DSI')->firstOrFail();

        $create = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Note à classer',
                'document_type_id' => $type->id,
                'classification_node_id' => $node->id,
            ])
            ->assertCreated();

        $id = $create->json('id');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents?classification_node_id='.$node->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);
    }

    public function test_unauthorized_download_policy_via_ged_show(): void
    {
        $agentDsi = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $agentDfm = User::query()->where('email', 'directeur.dfm@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'GED-REST-001',
            'object' => 'Note restreinte GED',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agentDsi->id,
            'status' => DocumentStatus::Brouillon->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Restreint->value,
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Ged->value,
            'document_date' => now()->toDateString(),
            'current_version' => 0,
            'archive_status' => 'actif',
        ]);

        $this->actingAs($agentDfm, 'sanctum')
            ->getJson('/api/ged/documents/'.$document->id)
            ->assertForbidden();
    }

    public function test_official_version_marked_on_validate(): void
    {
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'GED-OFF-001',
            'object' => 'À valider',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'current_assignee_id' => $dg->id,
            'status' => DocumentStatus::AValider->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'expected_action' => 'validation',
            'origin' => DocumentOrigin::Parapheur->value,
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'actif',
        ]);

        DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'disk' => 'local',
            'path' => 'documents/test.docx',
            'original_name' => 'test.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 10,
            'checksum' => hash('sha256', 'x'),
            'is_main' => true,
            'is_official' => false,
            'uploaded_by' => $agent->id,
            'change_source' => 'upload',
        ]);

        $this->actingAs($dg, 'sanctum')
            ->postJson('/api/parapheur/documents/'.$document->id.'/validate', [
                'comment' => 'OK',
            ])
            ->assertOk();

        $this->assertTrue(
            DocumentVersion::query()
                ->where('document_id', $document->id)
                ->where('is_main', true)
                ->where('is_official', true)
                ->exists()
        );
    }

    public function test_soft_delete_blocked_for_validated_document(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'GED-DEL-001',
            'object' => 'Validé non supprimable',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'status' => DocumentStatus::Valide->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'expected_action' => 'validation',
            'origin' => DocumentOrigin::Ged->value,
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'actif',
        ]);

        $this->actingAs($agent, 'sanctum')
            ->deleteJson('/api/ged/documents/'.$document->id)
            ->assertStatus(422);

        $this->assertNull($document->fresh()->deleted_at);
    }

    public function test_dashboard_accessible(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/dashboard')
            ->assertOk()
            ->assertJsonStructure(['counts' => ['actifs', 'archives', 'mes_documents'], 'recent']);
    }

    public function test_link_documents(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $a = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc A',
                'document_type_id' => $type->id,
            ])->json('id');

        $b = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc B',
                'document_type_id' => $type->id,
            ])->json('id');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents/'.$a.'/links', [
                'target_document_id' => $b,
                'relation_type' => 'related_to',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('document_links', [
            'source_document_id' => $a,
            'target_document_id' => $b,
        ]);
    }
}
