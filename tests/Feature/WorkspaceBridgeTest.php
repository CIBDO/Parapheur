<?php

namespace Tests\Feature;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocumentLink;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    private function uploadPersonalDoc(User $agent): array
    {
        $home = $this->actingAs($agent, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');
        $type = DocumentType::query()->firstOrFail();

        $doc = $this->actingAs($agent, 'sanctum')
            ->post("/api/workspace/{$wsId}/documents", [
                'object' => 'Note de travail SIGRAC',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('note.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertCreated()
            ->json();

        return ['workspace_id' => $wsId, 'document' => $doc];
    }

    public function test_submit_to_ged_changes_origin_without_duplicating_file(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $ctx = $this->uploadPersonalDoc($agent);
        $docId = $ctx['document']['id'];
        $wsId = $ctx['workspace_id'];

        $before = DocumentVersion::query()->where('document_id', $docId)->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/workspace/{$wsId}/documents/{$docId}/submit-ged")
            ->assertOk()
            ->assertJsonPath('origin', DocumentOrigin::Ged->value);

        $after = DocumentVersion::query()->where('document_id', $docId)->firstOrFail();
        $this->assertSame($before->path, $after->path);
        $this->assertSame($before->checksum, $after->checksum);
        $this->assertTrue((bool) $after->is_official);
        $this->assertDatabaseHas('workspace_document_links', [
            'workspace_id' => $wsId,
            'document_id' => $docId,
        ]);
    }

    public function test_submit_to_parapheur_pins_version(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $destinataire = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $ctx = $this->uploadPersonalDoc($agent);
        $docId = $ctx['document']['id'];
        $wsId = $ctx['workspace_id'];

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/workspace/{$wsId}/documents/{$docId}/submit-parapheur", [
                'recipient_ids' => [$destinataire->id],
                'expected_action' => 'visa',
                'object' => 'Note SIGRAC pour visa',
                'message' => 'Merci de viser',
            ]);

        $response->assertOk()
            ->assertJsonPath('document.origin', DocumentOrigin::Parapheur->value);

        $this->assertNotNull($response->json('pinned_version_id'));

        $version = DocumentVersion::query()->findOrFail($response->json('pinned_version_id'));
        $this->assertTrue((bool) $version->is_official);

        $doc = Document::query()->findOrFail($docId);
        $this->assertNotEquals(DocumentStatus::Brouillon, $doc->status);
    }

    public function test_share_document_with_user(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $other = User::query()->where('email', 'directeur.dfm@dgtcp.local')->firstOrFail();
        $ctx = $this->uploadPersonalDoc($agent);
        $docId = $ctx['document']['id'];
        $wsId = $ctx['workspace_id'];

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/workspace/{$wsId}/shares", [
                'grantee_user_id' => $other->id,
                'ability' => 'view',
                'document_id' => $docId,
            ])
            ->assertCreated();

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/workspace/shared-with-me?filter=documents')
            ->assertOk()
            ->assertJsonFragment(['kind' => 'document']);
    }

    public function test_working_copy_creates_derived_document(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $ctx = $this->uploadPersonalDoc($agent);
        $docId = $ctx['document']['id'];
        $wsId = $ctx['workspace_id'];

        // Passer en GED puis créer une copie de travail
        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/workspace/{$wsId}/documents/{$docId}/submit-ged")
            ->assertOk();

        $copy = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/workspace/{$wsId}/documents/{$docId}/working-copy")
            ->assertCreated()
            ->json();

        $this->assertSame(DocumentOrigin::Personal->value, $copy['origin']);
        $this->assertNotEquals($docId, $copy['id']);
        $this->assertDatabaseHas('document_links', [
            'source_document_id' => $copy['id'],
            'target_document_id' => $docId,
            'relation_type' => 'derived_from',
        ]);
        $this->assertDatabaseHas('workspace_document_links', [
            'workspace_id' => $wsId,
            'document_id' => $copy['id'],
        ]);
    }
}
