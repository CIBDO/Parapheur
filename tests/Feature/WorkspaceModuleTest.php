<?php

namespace Tests\Feature;

use App\Enums\QuotaScopeType;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceType;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceQuotaOverride;
use App\Models\WorkspaceStoragePolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_agent_gets_personal_workspace_on_home(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson('/api/workspace/home');

        $response->assertOk()
            ->assertJsonPath('workspace.type', WorkspaceType::Personal->value);

        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $agent->id,
            'type' => WorkspaceType::Personal->value,
        ]);

        $this->assertGreaterThanOrEqual(4, count($response->json('folders') ?? []));
    }

    public function test_agent_cannot_see_other_personal_workspace(): void
    {
        $agentA = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $agentB = User::query()->where('email', 'directeur.dfm@dgtcp.local')->firstOrFail();

        $this->actingAs($agentA, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsA = Workspace::query()->where('owner_id', $agentA->id)->where('type', 'personal')->firstOrFail();

        $this->actingAs($agentB, 'sanctum')
            ->getJson('/api/workspace/'.$wsA->id)
            ->assertForbidden();
    }

    public function test_create_folder_and_upload_document(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $home = $this->actingAs($agent, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');

        $folder = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/workspace/{$wsId}/folders", ['name' => 'SIGRAC'])
            ->assertCreated()
            ->json();

        $this->actingAs($agent, 'sanctum')
            ->post("/api/workspace/{$wsId}/documents", [
                'object' => 'Architecture SIGRAC',
                'document_type_id' => $type->id,
                'folder_id' => $folder['id'],
                'main_file' => UploadedFile::fake()->create('archi.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertCreated()
            ->assertJsonPath('origin', 'personal');

        $browse = $this->actingAs($agent, 'sanctum')
            ->getJson("/api/workspace/{$wsId}/browse?folder_id={$folder['id']}")
            ->assertOk();

        $this->assertNotEmpty($browse->json('documents'));
    }

    public function test_upload_blocked_when_quota_exceeded(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $home = $this->actingAs($agent, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');

        WorkspaceQuotaOverride::query()->updateOrCreate(
            ['scope_type' => QuotaScopeType::Workspace->value, 'scope_id' => $wsId],
            ['quota_bytes' => 1024, 'created_by' => $agent->id, 'note' => 'test tiny']
        );

        WorkspaceStoragePolicy::query()->first()?->update(['block_on_exceed' => true, 'max_upload_bytes' => 10 * 1024 * 1024]);

        $this->actingAs($agent, 'sanctum')
            ->post("/api/workspace/{$wsId}/documents", [
                'object' => 'Trop gros',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('big.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_viewer_cannot_upload(): void
    {
        $owner = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $viewer = User::query()->where('email', 'directeur.dfm@dgtcp.local')->firstOrFail();

        $home = $this->actingAs($owner, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');

        WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $wsId, 'user_id' => $viewer->id],
            ['role' => WorkspaceMemberRole::Viewer->value, 'joined_at' => now()]
        );

        $type = DocumentType::query()->firstOrFail();

        $this->actingAs($viewer, 'sanctum')
            ->post("/api/workspace/{$wsId}/documents", [
                'object' => 'Interdit',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_storage_policy(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/workspace/admin/storage-policy', [
                'default_personal_quota_bytes' => 5 * 1024 * 1024 * 1024,
                'warn_threshold_percent' => 75,
                'block_on_exceed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('warn_threshold_percent', 75);
    }

    public function test_upload_rejects_extension_outside_policy(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        WorkspaceStoragePolicy::query()->first()?->update([
            'allowed_extensions' => ['pdf', 'docx'],
            'denied_extensions' => [],
            'max_upload_bytes' => 10 * 1024 * 1024,
            'block_on_exceed' => false,
        ]);

        $home = $this->actingAs($agent, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');

        $this->actingAs($agent, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post("/api/workspace/{$wsId}/documents", [
                'object' => 'Exe interdit',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['main_file']);

        $this->actingAs($agent, 'sanctum')
            ->withHeader('Accept', 'application/json')
            ->post("/api/workspace/{$wsId}/documents", [
                'object' => 'Pdf ok',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf'),
            ])
            ->assertCreated();
    }

    public function test_purge_workspace_trash_respects_retention(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        WorkspaceStoragePolicy::query()->first()?->update([
            'trash_retention_days' => 7,
            'allowed_extensions' => ['pdf'],
            'max_upload_bytes' => 10 * 1024 * 1024,
            'block_on_exceed' => false,
        ]);

        $home = $this->actingAs($agent, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');

        $created = $this->actingAs($agent, 'sanctum')
            ->post("/api/workspace/{$wsId}/documents", [
                'object' => 'À purger',
                'document_type_id' => $type->id,
                'main_file' => UploadedFile::fake()->create('purge.pdf', 10, 'application/pdf'),
            ])
            ->assertCreated();

        $docId = (int) $created->json('id');

        $this->actingAs($agent, 'sanctum')
            ->deleteJson("/api/workspace/{$wsId}/documents/{$docId}")
            ->assertOk();

        $this->assertSoftDeleted('documents', ['id' => $docId]);
        $this->assertSoftDeleted('workspace_document_links', ['document_id' => $docId]);

        // Encore dans la fenêtre de rétention → rien à purger
        $this->artisan('parapheur:purge-workspace-trash')->assertSuccessful();
        $this->assertSoftDeleted('documents', ['id' => $docId]);

        \App\Models\Document::withTrashed()->whereKey($docId)->update(['deleted_at' => now()->subDays(10)]);
        \App\Models\WorkspaceDocumentLink::withTrashed()
            ->where('document_id', $docId)
            ->update(['deleted_at' => now()->subDays(10)]);

        $this->artisan('parapheur:purge-workspace-trash')->assertSuccessful();
        $this->assertDatabaseMissing('documents', ['id' => $docId]);
        $this->assertDatabaseMissing('workspace_document_links', ['document_id' => $docId]);
    }

    public function test_library_reference_create(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $typeId = \App\Models\ReferenceType::query()->where('code', 'INSTRUCTION')->value('id');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/library/references', [
                'title' => 'Instruction BCEAO reporting IMF',
                'reference_type_id' => $typeId,
                'institutional_author' => 'BCEAO',
                'publication_year' => 2026,
                'tags' => ['Microfinance', 'Reporting'],
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'Instruction BCEAO reporting IMF');
    }
}
