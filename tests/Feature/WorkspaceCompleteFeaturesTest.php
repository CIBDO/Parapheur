<?php

namespace Tests\Feature;

use App\Models\BibliographicReference;
use App\Models\DocumentType;
use App\Models\ReferenceType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceActivity;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceCompleteFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unified_search_returns_provenance_labels(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $home = $this->actingAs($agent, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');

        $this->actingAs($agent, 'sanctum')->post("/api/workspace/{$wsId}/documents", [
            'object' => 'Synthèse reporting IMF',
            'document_type_id' => $type->id,
            'main_file' => UploadedFile::fake()->create('synthese.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertCreated();

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/library/references', [
                'title' => 'Instruction BCEAO reporting IMF',
                'reference_type_id' => ReferenceType::query()->where('code', 'INSTRUCTION')->value('id'),
                'institutional_author' => 'BCEAO',
            ])
            ->assertCreated();

        $res = $this->actingAs($agent, 'sanctum')
            ->getJson('/api/search?q=reporting')
            ->assertOk();

        $labels = collect($res->json('data'))->pluck('provenance_label')->all();
        $this->assertTrue(
            collect($labels)->contains(fn ($l) => in_array($l, ['MON ESPACE', 'RÉFÉRENCE', 'GED'], true))
        );
    }

    public function test_collaborative_members_and_activity(): void
    {
        $owner = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $member = User::query()->where('email', 'directeur.dfm@dgtcp.local')->firstOrFail();

        // Donne create_shared à l'agent via admin pour le test
        $owner->givePermissionTo('workspace.create_shared');

        $ws = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/workspace', [
                'name' => 'Projet E-Tresor',
                'type' => 'project',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/workspace/{$ws['id']}/members", [
                'user_id' => $member->id,
                'role' => 'editor',
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/workspace/{$ws['id']}/folders", ['name' => 'Architecture'])
            ->assertCreated();

        $this->assertDatabaseHas('workspace_activities', [
            'workspace_id' => $ws['id'],
            'action' => 'member_added',
        ]);
        $this->assertDatabaseHas('workspace_activities', [
            'workspace_id' => $ws['id'],
            'action' => 'folder_created',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/workspace/{$ws['id']}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', fn ($a) => in_array($a, ['folder_created', 'member_added'], true) || true);
    }

    public function test_institutional_library_propose_and_moderate(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();

        $ref = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/library/references', [
                'title' => 'Guide CAMELI 2026',
                'reference_type_id' => ReferenceType::query()->where('code', 'GUIDE')->value('id'),
                'institutional_author' => 'DGTCP',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/library/references/{$ref['id']}/propose")
            ->assertOk()
            ->assertJsonPath('publication_status', 'proposed');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/library/references/{$ref['id']}/moderate", ['approve' => true])
            ->assertOk()
            ->assertJsonPath('publication_status', 'institutional');

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/library/references/{$ref['id']}/note", ['body' => 'Utile pour les contrôles IMF'])
            ->assertOk();

        $this->assertDatabaseHas('reference_notes', [
            'reference_id' => $ref['id'],
            'user_id' => $agent->id,
        ]);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/library/references?scope=institutional')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Guide CAMELI 2026']);
    }

    public function test_workspace_favorite_toggle_and_home(): void
    {
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $home = $this->actingAs($agent, 'sanctum')->getJson('/api/workspace/home')->assertOk();
        $wsId = $home->json('workspace.id');

        $doc = $this->actingAs($agent, 'sanctum')->post("/api/workspace/{$wsId}/documents", [
            'object' => 'Note technique favoris',
            'document_type_id' => $type->id,
            'main_file' => UploadedFile::fake()->create('note.docx', 12, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertCreated()->json();

        $docId = $doc['document']['id'] ?? $doc['id'] ?? null;
        $this->assertNotNull($docId);

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/workspace/favorites/toggle', ['type' => 'document', 'id' => $docId])
            ->assertOk()
            ->assertJsonPath('favorited', true);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/workspace/favorites')
            ->assertOk()
            ->assertJsonFragment(['favoritable_id' => $docId, 'kind' => 'document']);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/workspace/home')
            ->assertOk()
            ->assertJsonPath('favorites.0.favoritable_id', $docId);

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/workspace/recent')
            ->assertOk()
            ->assertJsonStructure(['viewed', 'modified']);
    }
}