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

    public function test_draft_not_visible_to_assignee(): void
    {
        $chef = User::factory()->create();
        $chef->assignRole('Chef de division');
        $agent = User::factory()->create();
        $agent->assignRole('Agent');

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
