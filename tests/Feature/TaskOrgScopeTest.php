<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Models\Instruction;
use App\Models\Structure;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskScopeResolver;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskOrgScopeTest extends TestCase
{
    use RefreshDatabase;

    private Structure $dsi;

    private Structure $drh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->dsi = Structure::query()->create([
            'code' => 'DSI',
            'name' => 'Direction des systèmes d\'information',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
        $this->drh = Structure::query()->create([
            'code' => 'DRH',
            'name' => 'Direction des ressources humaines',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
    }

    public function test_agent_dsi_does_not_list_drh_task(): void
    {
        $agentDsi = $this->user('Agent', $this->dsi);
        $agentDrh = $this->user('Agent', $this->drh);

        $task = Task::query()->create([
            'reference' => 'TSK/TEST/1',
            'created_by' => $agentDrh->id,
            'assignee_id' => $agentDrh->id,
            'structure_id' => $this->drh->id,
            'title' => 'Tâche DRH',
            'status' => 'imputee',
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'source_kind' => 'manual',
            'progress' => 0,
        ]);

        Sanctum::actingAs($agentDsi);
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonMissing(['id' => $task->id]);
    }

    public function test_chef_dsi_sees_team_tasks_in_structure(): void
    {
        $chef = $this->user('Chef de division', $this->dsi);
        $agent = $this->user('Agent', $this->dsi);

        $task = Task::query()->create([
            'reference' => 'TSK/TEST/2',
            'created_by' => $agent->id,
            'assignee_id' => $agent->id,
            'structure_id' => $this->dsi->id,
            'title' => 'Tâche équipe DSI',
            'status' => 'imputee',
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'source_kind' => 'manual',
            'progress' => 0,
        ]);

        Sanctum::actingAs($chef);
        $this->getJson('/api/tasks?team=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $task->id]);
    }

    public function test_agent_cannot_assign_outside_scope(): void
    {
        $agentDsi = $this->user('Agent', $this->dsi);
        $agentDrh = $this->user('Agent', $this->drh);

        Sanctum::actingAs($agentDsi);
        $this->postJson('/api/tasks', [
            'title' => 'Imputation hors périmètre',
            'assignee_id' => $agentDrh->id,
        ])->assertForbidden();
    }

    public function test_meta_users_context_tasks_is_scoped(): void
    {
        $agentDsi = $this->user('Agent', $this->dsi);
        $agentDrh = $this->user('Agent', $this->drh);

        Sanctum::actingAs($agentDsi);
        $ids = collect($this->getJson('/api/meta/users?context=tasks')->assertOk()->json())
            ->pluck('id')
            ->all();

        $this->assertContains($agentDsi->id, $ids);
        $this->assertNotContains($agentDrh->id, $ids);
    }

    public function test_view_all_keeps_global_access(): void
    {
        $dg = $this->user('Directeur Général', $this->dsi);
        $agentDrh = $this->user('Agent', $this->drh);

        $task = Task::query()->create([
            'reference' => 'TSK/TEST/3',
            'created_by' => $agentDrh->id,
            'assignee_id' => $agentDrh->id,
            'structure_id' => $this->drh->id,
            'title' => 'Tâche DRH visible DG',
            'status' => 'imputee',
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'source_kind' => 'manual',
            'progress' => 0,
        ]);

        Sanctum::actingAs($dg);
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonFragment(['id' => $task->id]);

        $ids = collect($this->getJson('/api/meta/users?context=tasks')->assertOk()->json())
            ->pluck('id')
            ->all();
        $this->assertContains($agentDrh->id, $ids);
    }

    public function test_confidential_task_hidden_from_team_listing(): void
    {
        $chef = $this->user('Chef de division', $this->dsi);
        $agent = $this->user('Agent', $this->dsi);

        $task = Task::query()->create([
            'reference' => 'TSK/TEST/4',
            'created_by' => $agent->id,
            'assignee_id' => $agent->id,
            'structure_id' => $this->dsi->id,
            'title' => 'Confidentiel',
            'status' => 'imputee',
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Confidentiel->value,
            'source_kind' => 'manual',
            'progress' => 0,
        ]);

        Sanctum::actingAs($chef);
        $this->getJson('/api/tasks?team=1')
            ->assertOk()
            ->assertJsonMissing(['id' => $task->id]);

        Sanctum::actingAs($agent);
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonFragment(['id' => $task->id]);
    }

    public function test_instruction_view_is_org_scoped_for_agent(): void
    {
        $agentDsi = $this->user('Agent', $this->dsi);
        $agentDrh = $this->user('Agent', $this->drh);

        $instruction = Instruction::query()->create([
            'reference' => 'INS/TEST/1',
            'kind' => 'imputation',
            'issuer_id' => $agentDrh->id,
            'assignee_id' => $agentDrh->id,
            'structure_id' => $this->drh->id,
            'title' => 'Instruction DRH',
            'body' => 'Corps',
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Normal->value,
            'source_kind' => 'manual',
            'status' => 'a_faire',
            'is_personal' => false,
        ]);

        Sanctum::actingAs($agentDsi);
        $this->getJson('/api/instructions')
            ->assertOk()
            ->assertJsonMissing(['id' => $instruction->id]);

        $this->assertFalse(
            app(\App\Policies\InstructionPolicy::class)->view($agentDsi, $instruction)
        );
    }

    public function test_scope_resolver_collaboration(): void
    {
        $agentDsi = $this->user('Agent', $this->dsi);
        $agentDrh = $this->user('Agent', $this->drh);
        $resolver = app(TaskScopeResolver::class);

        $this->assertTrue($resolver->canCollaborateWith($agentDsi, $agentDsi));
        $this->assertFalse($resolver->canCollaborateWith($agentDsi, $agentDrh));
        $this->assertContains($this->dsi->id, $resolver->structureIds($agentDsi));
        $this->assertNotContains($this->drh->id, $resolver->structureIds($agentDsi));
    }

    private function user(string $role, Structure $structure): User
    {
        $user = User::factory()->create(['structure_id' => $structure->id]);
        $user->syncRoles([$role]);

        return $user->fresh();
    }
}
