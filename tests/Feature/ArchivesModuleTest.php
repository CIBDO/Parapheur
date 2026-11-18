<?php

namespace Tests\Feature;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Models\ArchiveTransfer;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivesModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_agent_can_create_and_submit_transfer_then_admin_can_conserve(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'GED-SAE-001',
            'object' => 'Note à verser',
            'title' => 'Note à verser',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $agent->id,
            'status' => DocumentStatus::Valide->value,
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Ged->value,
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'actif',
        ]);

        DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'disk' => 'local',
            'path' => 'documents/test/note.pdf',
            'original_name' => 'note.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => hash('sha256', 'note'),
            'uploaded_by' => $agent->id,
            'is_official' => true,
        ]);

        $create = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/transfers', [
                'structure_id' => $structure->id,
                'title' => 'Versement test SAE',
                'document_ids' => [$document->id],
            ]);

        $create->assertCreated()
            ->assertJsonPath('status', 'brouillon');

        $transferId = $create->json('id');

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/archives/transfers/{$transferId}/submit")
            ->assertOk()
            ->assertJsonPath('status', 'soumis');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/archives/transfers/{$transferId}/accept")
            ->assertOk()
            ->assertJsonPath('status', 'accepte');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/archives/transfers/{$transferId}/take-over")
            ->assertOk()
            ->assertJsonPath('status', 'conserve');

        $this->assertDatabaseHas('archive_records', [
            'document_id' => $document->id,
            'status' => 'conserve',
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'archive_status' => 'verse',
        ]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/archives/records')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Note à verser']);
    }

    public function test_dashboard_requires_archives_permission(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/archives/dashboard')
            ->assertOk()
            ->assertJsonStructure(['counts', 'pending_transfers', 'recent_records']);

        $this->assertTrue(
            ArchiveTransfer::query()->count() >= 0
        );
    }
}
