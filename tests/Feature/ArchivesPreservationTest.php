<?php

namespace Tests\Feature;

use App\Enums\ArchiveRecordStatus;
use App\Models\ArchivePackage;
use App\Models\ArchiveRecord;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivesPreservationTest extends TestCase
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

    public function test_build_aip_replicate_verify_and_supervision(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        Storage::disk('local')->put('documents/2026/01/note.txt', 'contenu archive test');

        $document = Document::query()->create([
            'reference' => 'GED-PRES-001',
            'object' => 'Doc préservation',
            'title' => 'Doc préservation',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $admin->id,
            'status' => 'valide',
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => 'ged',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'verse',
        ]);

        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'disk' => 'local',
            'path' => 'documents/2026/01/note.txt',
            'original_name' => 'note.txt',
            'mime_type' => 'text/plain',
            'size' => 20,
            'checksum' => hash('sha256', 'contenu archive test'),
            'uploaded_by' => $admin->id,
            'is_official' => true,
        ]);

        $record = ArchiveRecord::query()->create([
            'permanent_id' => 'AR-PRES-001',
            'title' => 'Doc préservation',
            'status' => ArchiveRecordStatus::Conserve,
            'confidentiality' => 'normal',
            'structure_id' => $structure->id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'checksum' => $version->checksum,
            'created_by' => $admin->id,
            'conserved_at' => now(),
        ]);

        $package = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/records/'.$record->id.'/aip', ['replicate' => true])
            ->assertCreated()
            ->json();

        $this->assertSame('aip', $package['package_type']);
        $this->assertSame('replicated', $package['status']);
        $this->assertNotEmpty($package['package_checksum']);

        Storage::disk('archives')->assertExists($package['path']);
        Storage::disk('archives_replica')->assertExists($package['replica_path']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/records/'.$record->id.'/verify-integrity')
            ->assertOk()
            ->assertJsonPath('result', 'ok');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/packages/'.$package['id'].'/verify')
            ->assertOk()
            ->assertJsonPath('result', 'ok');

        $dip = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/records/'.$record->id.'/dip')
            ->assertCreated()
            ->json();

        $this->assertSame('dip', $dip['package_type']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/preservation/supervision')
            ->assertOk()
            ->assertJsonStructure([
                'integrity_failures',
                'records_at_risk',
                'packages_sealed',
                'packages_replicated',
                'loans_overdue',
            ]);

        $this->assertTrue(ArchivePackage::query()->where('reference', $package['reference'])->exists());
    }

    public function test_integrity_detects_mismatch(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        Storage::disk('local')->put('documents/2026/01/altered.txt', 'contenu actuel');

        $document = Document::query()->create([
            'reference' => 'GED-PRES-002',
            'object' => 'Doc altéré',
            'title' => 'Doc altéré',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $admin->id,
            'status' => 'valide',
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => 'ged',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'verse',
        ]);

        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'disk' => 'local',
            'path' => 'documents/2026/01/altered.txt',
            'original_name' => 'altered.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'checksum' => hash('sha256', 'contenu original different'),
            'uploaded_by' => $admin->id,
            'is_official' => true,
        ]);

        $record = ArchiveRecord::query()->create([
            'permanent_id' => 'AR-PRES-002',
            'title' => 'Doc altéré',
            'status' => ArchiveRecordStatus::Conserve,
            'confidentiality' => 'normal',
            'structure_id' => $structure->id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'checksum' => $version->checksum,
            'created_by' => $admin->id,
            'conserved_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/records/'.$record->id.'/verify-integrity')
            ->assertOk()
            ->assertJsonPath('result', 'mismatch');

        $this->assertDatabaseHas('archive_records', [
            'id' => $record->id,
            'preservation_status' => 'at_risk',
            'last_integrity_result' => 'mismatch',
        ]);
    }
}
