<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Jobs\IndexDocumentContentJob;
use App\Models\ClassificationNode;
use App\Models\Document;
use App\Models\DocumentClassificationRule;
use App\Models\DocumentIndexContent;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Structure;
use App\Models\User;
use App\Services\DocumentAutoClassificationService;
use App\Services\DocumentTextExtractionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GedP1FeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_favorite_toggle_and_list(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc favori',
                'document_type_id' => $type->id,
            ])->json('id');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents/'.$id.'/favorite')
            ->assertOk()
            ->assertJsonPath('favorited', true);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/favorites')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);
    }

    public function test_recent_views_respect_permissions(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc consulté',
                'document_type_id' => $type->id,
            ])->json('id');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents/'.$id)
            ->assertOk();

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/recent')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);
    }

    public function test_text_extraction_indexes_txt_content(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'GED-IDX-001',
            'object' => 'Indexation test',
            'title' => 'Indexation test',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'status' => DocumentStatus::Brouillon->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Ged->value,
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'actif',
        ]);

        Storage::disk('local')->put('documents/test.txt', 'Contenu interconnexion Douanes SIGRAC unique');
        DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'disk' => 'local',
            'path' => 'documents/test.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 40,
            'checksum' => hash('sha256', 'x'),
            'is_main' => true,
            'uploaded_by' => $agent->id,
            'change_source' => 'upload',
        ]);

        app(DocumentTextExtractionService::class)->indexDocument($document->fresh('latestVersion'));

        $this->assertDatabaseHas('document_index_contents', [
            'document_id' => $document->id,
        ]);

        $this->assertTrue(
            DocumentIndexContent::query()
                ->where('document_id', $document->id)
                ->where('content', 'like', '%SIGRAC%')
                ->exists()
        );

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents?q=SIGRAC')
            ->assertOk()
            ->assertJsonFragment(['id' => $document->id]);
    }

    public function test_legal_hold_blocks_delete(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'À geler',
                'document_type_id' => $type->id,
            ])->json('id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/ged/documents/'.$id.'/legal-hold', [
                'reason' => 'Contentieux en cours',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/ged/documents/'.$id)
            ->assertStatus(422);
    }

    public function test_auto_classification_after_validation_rule(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->where('code', 'NOTE')->first()
            ?? DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();
        $node = ClassificationNode::query()->where('code', 'NOTES')->firstOrFail();

        DocumentClassificationRule::query()->updateOrCreate(
            ['code' => 'TEST_AUTO'],
            [
                'name' => 'Test auto',
                'document_type_id' => $type->id,
                'structure_id' => $dsi->id,
                'target_classification_node_id' => $node->id,
                'trigger_status' => 'valide',
                'priority' => 1,
                'is_active' => true,
            ]
        );

        $document = Document::query()->create([
            'reference' => 'GED-AUTO-001',
            'object' => 'Note auto classée',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'status' => DocumentStatus::Valide->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'expected_action' => 'validation',
            'origin' => DocumentOrigin::Parapheur->value,
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'actif',
            'classification_node_id' => null,
        ]);

        $ok = app(DocumentAutoClassificationService::class)->applyAfterStatus($document, 'valide', $agent);
        $this->assertTrue($ok);
        $this->assertEquals($node->id, $document->fresh()->classification_node_id);
    }

    public function test_create_dispatches_index_job(): void
    {
        Queue::fake();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Avec job',
                'document_type_id' => $type->id,
            ])
            ->assertCreated();

        Queue::assertPushed(IndexDocumentContentJob::class);
    }

    public function test_infected_document_blocks_export(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc infecté',
                'document_type_id' => $type->id,
            ])->json('id');

        Document::query()->whereKey($id)->update(['antivirus_status' => 'infected']);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents/'.$id.'/export')
            ->assertStatus(422);
    }
}
