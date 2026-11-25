<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_task_lifecycle_create_take_charge_complete_validate(): void
    {
        $chef = User::query()->where('email', 'like', '%')->role('Chef de division')->first()
            ?? User::query()->role('Directeur')->first()
            ?? User::factory()->create();

        if (! $chef->hasAnyRole(['Chef de division', 'Directeur', 'Administrateur', 'Directeur Général'])) {
            $chef->assignRole('Chef de division');
        }

        $agent = User::factory()->create(['structure_id' => $chef->structure_id]);
        $agent->assignRole('Agent');

        Sanctum::actingAs($chef);

        $create = $this->postJson('/api/tasks', [
            'title' => 'Préparer statistiques',
            'description' => 'Collecte mensuelle',
            'assignee_id' => $agent->id,
            'priority' => 'importante',
            'due_at' => now()->addDays(3)->toISOString(),
        ])->assertCreated();

        $taskId = $create->json('id');
        $this->assertNotEmpty($create->json('reference'));
        $this->assertSame(TaskStatus::Imputee->value, $create->json('status'));

        Sanctum::actingAs($agent);

        $this->postJson("/api/tasks/{$taskId}/take-charge")
            ->assertOk()
            ->assertJsonPath('status', TaskStatus::PriseEnCharge->value);

        $this->postJson("/api/tasks/{$taskId}/start")
            ->assertOk()
            ->assertJsonPath('status', TaskStatus::EnCours->value);

        $this->postJson("/api/tasks/{$taskId}/complete", [
            'summary' => 'Fichier transmis',
        ])->assertOk()
            ->assertJsonPath('status', TaskStatus::AValider->value);

        Sanctum::actingAs($chef);

        $this->postJson("/api/tasks/{$taskId}/validate")
            ->assertOk()
            ->assertJsonPath('status', TaskStatus::Validee->value);

        $this->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonStructure(['counts', 'tasks', 'instructions', 'other']);
    }

    public function test_creator_does_not_get_take_charge_action_when_not_assignee(): void
    {
        [$chef, $agent] = $this->chefAndAgent();

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Imputation à un agent',
            'assignee_id' => $agent->id,
            'priority' => 'normale',
        ])->assertCreated()->json('id');

        $show = $this->getJson("/api/tasks/{$taskId}")->assertOk();
        $actions = $show->json('available_actions') ?? [];
        $this->assertNotContains('take_charge', $actions);
        $this->assertContains('reassign', $actions);
        $this->assertContains('cancel', $actions);

        Sanctum::actingAs($agent);
        $agentShow = $this->getJson("/api/tasks/{$taskId}")->assertOk();
        $this->assertContains('take_charge', $agentShow->json('available_actions') ?? []);
    }

    public function test_created_tasks_appear_in_assigned_by_me_not_mine(): void
    {
        [$chef, $agent] = $this->chefAndAgent();

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Suivi créateur',
            'assignee_id' => $agent->id,
        ])->assertCreated()->json('id');

        $mine = collect($this->getJson('/api/tasks?mine=1')->assertOk()->json('data') ?? []);
        $this->assertFalse($mine->contains(fn ($t) => (int) $t['id'] === (int) $taskId));

        $assigned = collect($this->getJson('/api/tasks?assigned_by_me=1')->assertOk()->json('data') ?? []);
        $this->assertTrue($assigned->contains(fn ($t) => (int) $t['id'] === (int) $taskId));
    }

    /** @return array{0: User, 1: User} */
    private function chefAndAgent(): array
    {
        $chef = User::query()->role('Chef de division')->first()
            ?? User::query()->role('Directeur')->first()
            ?? User::factory()->create();

        if (! $chef->hasAnyRole(['Chef de division', 'Directeur', 'Administrateur', 'Directeur Général'])) {
            $chef->assignRole('Chef de division');
        }

        $agent = User::factory()->create(['structure_id' => $chef->structure_id]);
        $agent->assignRole('Agent');

        return [$chef, $agent];
    }

    public function test_draft_not_visible_to_assignee(): void
    {
        [$chef, $agent] = $this->chefAndAgent();

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Brouillon secret',
            'assignee_id' => $agent->id,
            'as_draft' => true,
        ])->assertCreated()->json('id');

        Sanctum::actingAs($agent);
        $this->getJson("/api/tasks/{$taskId}")->assertForbidden();
    }

    public function test_instruction_creates_execution_task(): void
    {
        $dg = User::factory()->create();
        $dg->assignRole('Directeur Général');
        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        Sanctum::actingAs($dg);
        $res = $this->postJson('/api/instructions', [
            'title' => 'Situation consolidée des régies',
            'body' => 'Avant vendredi',
            'assignee_id' => $agent->id,
            'create_execution_task' => true,
        ])->assertCreated();

        $this->assertNotEmpty($res->json('reference'));
        $this->assertNotEmpty($res->json('tasks'));
    }
}
