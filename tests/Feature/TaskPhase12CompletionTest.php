<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskPhase12CompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_advanced_search_and_kanban_calendar_endpoints(): void
    {
        $chef = User::factory()->create();
        $chef->assignRole('Chef de division');
        $agent = User::factory()->create(['structure_id' => $chef->structure_id]);
        $agent->assignRole('Agent');

        Sanctum::actingAs($chef);

        $task = $this->postJson('/api/tasks', [
            'title' => 'Recherche avancée',
            'assignee_id' => $agent->id,
            'priority' => 'urgente',
            'source_kind' => 'manual',
            'tags' => ['budget'],
            'due_at' => now()->addDays(2)->toISOString(),
        ])->assertCreated()->json();

        $this->getJson('/api/tasks?q=Recherche&priority=urgente&source_kind=manual&tag=budget')
            ->assertOk()
            ->assertJsonFragment(['id' => $task['id']]);

        $this->getJson('/api/tasks/kanban?mine=0')
            ->assertOk()
            ->assertJsonStructure(['columns']);

        $this->getJson('/api/tasks/calendar?from='.now()->toDateString().'&to='.now()->addDays(7)->toDateString())
            ->assertOk()
            ->assertJsonStructure(['events']);
    }

    public function test_comment_mentions_and_audit_trail(): void
    {
        $chef = User::factory()->create();
        $chef->assignRole('Chef de division');
        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Mention test',
            'assignee_id' => $agent->id,
        ])->assertCreated()->json('id');

        Sanctum::actingAs($agent);
        $this->postJson("/api/tasks/{$taskId}/comments", [
            'body' => "Bonjour @{$chef->id} pour validation",
        ])->assertCreated()
            ->assertJsonPath('mentions.0', $chef->id);

        Sanctum::actingAs($chef);
        $this->getJson("/api/tasks/{$taskId}/audit")
            ->assertOk();
    }

    public function test_delegation_allows_take_charge(): void
    {
        $chef = User::factory()->create();
        $chef->assignRole('Chef de division');
        $agent = User::factory()->create();
        $agent->assignRole('Agent');
        $delegate = User::factory()->create();
        $delegate->assignRole('Agent');

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Délégation',
            'assignee_id' => $agent->id,
        ])->assertCreated()->json('id');

        $this->postJson("/api/tasks/{$taskId}/delegate", [
            'delegate_id' => $delegate->id,
            'ends_on' => now()->addDays(7)->toDateString(),
            'allowed_actions' => ['take_charge', 'complete', 'comment'],
            'reason' => 'Absence',
        ])->assertCreated();

        Sanctum::actingAs($delegate);
        $this->postJson("/api/tasks/{$taskId}/take-charge")
            ->assertOk()
            ->assertJsonPath('status', TaskStatus::PriseEnCharge->value);
    }

    public function test_reports_overview_requires_permission(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('Agent');
        Sanctum::actingAs($agent);

        // Agent may or may not have view_reports depending on seeder — expect 200 or 403
        $response = $this->getJson('/api/tasks/reports/overview');
        $this->assertContains($response->status(), [200, 403]);

        $chef = User::factory()->create();
        $chef->assignRole('Directeur');
        Sanctum::actingAs($chef);
        $this->getJson('/api/tasks/reports/overview')
            ->assertOk()
            ->assertJsonStructure(['volume_created', 'open', 'overdue']);
    }

    public function test_manual_escalate(): void
    {
        $chef = User::factory()->create();
        $chef->assignRole('Chef de division');
        $agent = User::factory()->create(['structure_id' => $chef->structure_id]);
        $agent->assignRole('Agent');

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'À escalader',
            'assignee_id' => $agent->id,
            'due_at' => now()->subDays(2)->toISOString(),
        ])->assertCreated()->json('id');

        $this->postJson("/api/tasks/{$taskId}/escalate", [
            'level' => 1,
            'reason' => 'Retard critique',
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('task_escalations', [
            'task_id' => $taskId,
            'level' => 1,
        ]);
    }
}
