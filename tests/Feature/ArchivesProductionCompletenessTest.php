<?php

namespace Tests\Feature;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Jobs\MigrateArchiveFormatJob;
use App\Models\ArchiveBox;
use App\Models\ArchiveRecord;
use App\Models\ArchiveRetentionRule;
use App\Models\ArchiveStorageLocation;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Structure;
use App\Models\User;
use App\Services\Archives\ArchivePackageService;
use App\Services\Archives\ArchiveRestoreService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivesProductionCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('archives');
        Storage::fake('archives_replica');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_propose_from_document_creates_draft_transfer(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $document = $this->makeDocument($agent, $structure);

        $res = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/propose', [
                'document_id' => $document->id,
                'structure_id' => $structure->id,
                'source_module' => 'ged',
                'source_ref' => $document->reference,
                'medium' => 'hybride',
            ]);

        $res->assertCreated()
            ->assertJsonPath('status', 'brouillon')
            ->assertJsonPath('source_module', 'ged')
            ->assertJsonPath('medium', 'hybride');
    }

    public function test_hybrid_metadata_update_and_classification_seed(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $this->assertDatabaseHas('archive_fonds', ['code' => 'DGTCP']);
        $this->assertDatabaseHas('archive_classification_nodes', ['code' => 'COMPTA']);
        $this->assertTrue(
            ArchiveRetentionRule::query()->where('code', 'TMPL-CORRESP')->where('is_validated', false)->exists()
        );

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $document = $this->makeDocument($agent, $structure);

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/propose', [
                'document_id' => $document->id,
                'structure_id' => $structure->id,
            ])
            ->assertCreated();

        $document2 = $this->makeDocument($agent, $structure, 'GED-SAE-HYB');
        $create = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/transfers', [
                'structure_id' => $structure->id,
                'document_ids' => [$document2->id],
                'title' => 'Hybrid test',
            ]);
        $create->assertCreated();
        $tid = $create->json('id');
        $this->actingAs($agent, 'sanctum')->postJson("/api/archives/transfers/{$tid}/submit")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/accept")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/take-over")->assertOk();

        $record = ArchiveRecord::query()->where('document_id', $document2->id)->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/archives/records/{$record->id}/hybrid", [
                'medium' => 'hybride',
                'physical_reference' => 'COT-2026-001',
            ])
            ->assertOk()
            ->assertJsonPath('medium', 'hybride')
            ->assertJsonPath('physical_reference', 'COT-2026-001');
    }

    public function test_import_preview_and_commit_physical(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $location = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/locations', [
                'structure_id' => $structure->id,
                'type' => 'room',
                'code' => 'MAG1',
                'name' => 'Magasin 1',
                'capacity_boxes' => 50,
            ])
            ->assertCreated()
            ->json();

        $csv = "box_code;location_code;title\nBOX-IMP-1;{$location['full_code']};Boite test\n";
        $file = UploadedFile::fake()->createWithContent('boxes.csv', $csv);

        $preview = $this->actingAs($admin, 'sanctum')
            ->post('/api/archives/imports/preview', [
                'structure_id' => $structure->id,
                'import_type' => 'physical',
                'file' => $file,
            ], ['Accept' => 'application/json']);

        $preview->assertCreated();
        $importId = $preview->json('id');
        $this->assertSame(1, (int) $preview->json('valid_count'));

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/archives/imports/{$importId}/commit")
            ->assertOk()
            ->assertJsonPath('status', 'committed');

        $this->assertTrue(ArchiveBox::query()->where('code', 'BOX-IMP-1')->exists());
    }

    public function test_restore_from_replica_after_corruption(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $document = $this->makeDocument($agent, $structure, 'GED-SAE-RST');

        Storage::disk('local')->put('documents/test/rst.pdf', 'contenu-original');

        $create = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/transfers', [
                'structure_id' => $structure->id,
                'document_ids' => [$document->id],
                'title' => 'Restore test',
            ])->assertCreated();

        $tid = $create->json('id');
        $this->actingAs($agent, 'sanctum')->postJson("/api/archives/transfers/{$tid}/submit")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/accept")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/take-over")->assertOk();

        $record = ArchiveRecord::query()->where('document_id', $document->id)->firstOrFail();
        $package = app(ArchivePackageService::class)->buildAipForRecord($admin, $record);
        $package = app(ArchivePackageService::class)->replicate($admin, $package);

        Storage::disk('archives')->put($package->path, 'CORRUPTED');

        $restored = app(ArchiveRestoreService::class)->restorePackageFromReplica($admin, $package->fresh());
        $this->assertSame('restored', $restored->status);
        $this->assertTrue(
            hash_equals((string) $restored->package_checksum, hash('sha256', Storage::disk('archives')->get($restored->path)))
        );
    }

    public function test_preventive_climate_and_format_migration_queues_job(): void
    {
        Queue::fake();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $location = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/locations', [
                'structure_id' => $structure->id,
                'type' => 'room',
                'code' => 'S1',
                'name' => 'Salle 1',
            ])
            ->assertCreated()
            ->json();
        $locationId = $location['id'];

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/preventive/climate', [
                'structure_id' => $structure->id,
                'location_id' => $locationId,
                'temperature_c' => 30,
                'humidity_pct' => 80,
            ])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/preventive/events', [
                'structure_id' => $structure->id,
                'event_type' => 'incident',
                'severity' => 'warning',
                'title' => 'Fuite détectée',
            ])
            ->assertCreated();

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $document = $this->makeDocument($agent, $structure, 'GED-SAE-MIG');
        $create = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/transfers', [
                'structure_id' => $structure->id,
                'document_ids' => [$document->id],
            ])->assertCreated();
        $tid = $create->json('id');
        $this->actingAs($agent, 'sanctum')->postJson("/api/archives/transfers/{$tid}/submit")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/accept")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/archives/transfers/{$tid}/take-over")->assertOk();
        $record = ArchiveRecord::query()->where('document_id', $document->id)->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/archives/records/{$record->id}/format-migration")
            ->assertCreated();

        Queue::assertPushed(MigrateArchiveFormatJob::class);
        $this->assertSame('migration_planned', $record->fresh()->preservation_status);
    }

    private function makeDocument(User $author, Structure $structure, string $ref = 'GED-SAE-PROP'): Document
    {
        $type = DocumentType::query()->firstOrFail();
        $document = Document::query()->create([
            'reference' => $ref,
            'object' => 'Doc SAE',
            'title' => 'Doc SAE',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $author->id,
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
            'path' => 'documents/test/'.$ref.'.pdf',
            'original_name' => $ref.'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'checksum' => hash('sha256', $ref),
            'uploaded_by' => $author->id,
            'is_official' => true,
        ]);

        Storage::disk('local')->put('documents/test/'.$ref.'.pdf', 'pdf-'.$ref);

        return $document;
    }
}
