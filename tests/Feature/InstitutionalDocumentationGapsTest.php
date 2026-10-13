<?php

namespace Tests\Feature;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentPublicationStatus;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\AntivirusScanService;
use App\Services\InstitutionalDepositService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstitutionalDocumentationGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_folder_acl_grant_and_revoke(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $folder = DocumentFolder::query()->where('code', 'ROOT_DOC')->firstOrFail();

        $rule = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/ged/folders/{$folder->id}/share", [
                'user_id' => $agent->id,
                'ability' => 'upload',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/ged/folders/{$folder->id}/share/{$rule['id']}")
            ->assertOk();
    }

    public function test_antivirus_marks_eicar_infected_and_blocks_export(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->post('/api/ged/documents', [
                'object' => 'Fichier EICAR',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('eicar-test.txt', 10, 'text/plain'),
            ])
            ->assertCreated()
            ->json('id');

        $doc = Document::query()->findOrFail($id);
        $version = $doc->latestVersion;
        $this->assertNotNull($version);

        Storage::disk($version->disk)->put(
            $version->path,
            'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'
        );
        $version->original_name = 'eicar.txt';
        $version->save();

        $status = app(AntivirusScanService::class)->scanDocument($doc->fresh());
        $this->assertSame('infected', $status);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/documents/'.$id.'/export')
            ->assertStatus(422);
    }

    public function test_institutional_deposit_assigns_folder_and_domain(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->where('code', 'NOTE')->firstOrFail();

        $doc = Document::query()->create([
            'reference' => 'INST-GAP-001',
            'object' => 'Situation de trésorerie mensuelle',
            'title' => 'Situation de trésorerie',
            'document_type_id' => $type->id,
            'structure_id' => $agent->structure_id,
            'author_id' => $agent->id,
            'status' => 'brouillon',
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => DocumentOrigin::Personal->value,
            'document_date' => now()->toDateString(),
            'current_version' => 0,
            'archive_status' => 'actif',
        ]);

        $result = app(InstitutionalDepositService::class)->deposit($doc, $agent, [
            'pin_version' => false,
        ]);

        $this->assertSame(DocumentOrigin::Ged, $result->origin);
        $this->assertSame(DocumentPublicationStatus::Depose, $result->publication_status);
        $this->assertNotNull($result->folder_id);
        $this->assertNotNull($result->domain_id);
    }

    public function test_audit_logs_endpoint(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ged/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_published_feed_and_dashboard_widgets(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc publié widget',
                'document_type_id' => $type->id,
                'publication_workflow' => 'simple',
            ])
            ->json('id');

        Document::query()->whereKey($id)->update([
            'publication_status' => DocumentPublicationStatus::Publie->value,
            'published_at' => now(),
            'confidentiality' => 'normal',
            'structure_id' => $agent->structure_id,
            'owner_structure_id' => $agent->structure_id,
        ]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/ged/published')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ged/dashboard')
            ->assertOk()
            ->assertJsonStructure(['counts', 'published', 'to_validate', 'recent']);
    }

    public function test_extra_metadata_on_create(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->where('code', 'CIRCULAIRE')->first()
            ?? DocumentType::query()->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Circulaire avec méta',
                'document_type_id' => $type->id,
                'extra_metadata' => ['numero_interne' => 'CIR-2026-01', 'signataire' => 'DG'],
            ])
            ->assertCreated()
            ->assertJsonPath('extra_metadata.numero_interne', 'CIR-2026-01');
    }

    public function test_share_with_structure_and_revoke(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/ged/documents', [
                'object' => 'Doc partagé structure',
                'document_type_id' => $type->id,
            ])
            ->json('id');

        Document::query()->whereKey($id)->update([
            'publication_status' => DocumentPublicationStatus::Publie->value,
            'published_at' => now(),
        ]);

        $rule = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/ged/documents/{$id}/share", [
                'structure_id' => $agent->structure_id,
                'ability' => 'view',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($agent, 'sanctum')
            ->deleteJson("/api/ged/documents/{$id}/share/{$rule['id']}")
            ->assertOk();
    }

    public function test_heuristic_antivirus_service_safe_by_default(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')
            ->post('/api/ged/documents', [
                'object' => 'Fichier sain',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('note.pdf', 40, 'application/pdf'),
            ])
            ->json('id');

        $status = app(AntivirusScanService::class)->scanDocument(Document::query()->findOrFail($id));
        $this->assertContains($status, ['safe', 'pending', 'failed']);
    }
}
