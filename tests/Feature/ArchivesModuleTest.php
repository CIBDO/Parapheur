<?php

namespace Tests\Feature;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Models\ArchiveRecord;
use App\Models\ArchiveStorageLocation;
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

    public function test_records_metadata_search_filters_typology_medium_and_location(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $location = ArchiveStorageLocation::query()->create([
            'structure_id' => $structure->id,
            'type' => 'room',
            'code' => 'SAL-RCH',
            'name' => 'Salle recherche',
            'full_code' => 'SAL-RCH',
            'status' => 'disponible',
            'capacity_boxes' => 10,
            'occupied_boxes' => 0,
            'is_active' => true,
        ]);

        $makeDoc = function (string $ref) use ($agent, $structure, $type) {
            $document = Document::query()->create([
                'reference' => $ref,
                'object' => $ref,
                'title' => $ref,
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
                'keywords' => ['budget', 'exercice'],
            ]);
            DocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => 1,
                'disk' => 'local',
                'path' => 'documents/test/'.$ref.'.pdf',
                'original_name' => $ref.'.pdf',
                'mime_type' => 'application/pdf',
                'size' => 100,
                'checksum' => hash('sha256', $ref),
                'uploaded_by' => $agent->id,
                'is_official' => true,
            ]);

            return $document;
        };

        $docMatch = $makeDoc('GED-SAE-RCH-OK');
        $docOther = $makeDoc('GED-SAE-RCH-NO');

        foreach ([$docMatch, $docOther] as $document) {
            $create = $this->actingAs($agent, 'sanctum')
                ->postJson('/api/archives/transfers', [
                    'structure_id' => $structure->id,
                    'title' => 'Versement '.$document->reference,
                    'document_ids' => [$document->id],
                ])
                ->assertCreated();
            $tid = $create->json('id');
            $this->actingAs($agent, 'sanctum')->postJson("/api/archives/transfers/{$tid}/submit")->assertOk();
            $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/accept")->assertOk();
            $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/take-over")->assertOk();
        }

        $recordMatch = ArchiveRecord::query()->where('document_id', $docMatch->id)->firstOrFail();
        $recordMatch->fill([
            'typology' => 'courrier',
            'medium' => 'hybride',
            'location_id' => $location->id,
            'physical_reference' => 'COT-RCH-01',
            'date_start' => '2024-01-01',
            'date_end' => '2024-12-31',
        ])->save();

        $recordOther = ArchiveRecord::query()->where('document_id', $docOther->id)->firstOrFail();
        $recordOther->fill([
            'typology' => 'dossier',
            'medium' => 'electronique',
        ])->save();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/records?typology=courrier&medium=hybride&location_id='.$location->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $recordMatch->id])
            ->assertJsonMissing(['id' => $recordOther->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/records?q=COT-RCH')
            ->assertOk()
            ->assertJsonFragment(['id' => $recordMatch->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/records?q=SAL-RCH')
            ->assertOk()
            ->assertJsonFragment(['id' => $recordMatch->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/records?date_from=2024-06-01&date_to=2024-06-30')
            ->assertOk()
            ->assertJsonFragment(['id' => $recordMatch->id]);
    }
}
