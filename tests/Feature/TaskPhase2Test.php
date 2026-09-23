<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dependencies_block_completion_until_related_done(): void
    {
        $chef = User::factory()->create();
        $chef->assignRole('Chef de division');
        $agent = User::factory()->create(['structure_id' => $chef->structure_id]);
        $agent->assignRole('Agent');

        Sanctum::actingAs($chef);

        $blockerId = $this->postJson('/api/tasks', [
            'title' => 'Collecter données',
            'assignee_id' => $agent->id,
        ])->assertCreated()->json('id');

        $mainId = $this->postJson('/api/tasks', [
            'title' => 'Rédiger synthèse',
            'assignee_id' => $agent->id,
        ])->assertCreated()->json('id');

        $this->postJson("/api/tasks/{$mainId}/dependencies", [
            'related_task_id' => $blockerId,
            'relation' => 'depends_on',
        ])->assertCreated();

        Sanctum::actingAs($agent);
        $this->postJson("/api/tasks/{$mainId}/take-charge")->assertOk();
        $this->postJson("/api/tasks/{$mainId}/start")->assertOk();
        $this->postJson("/api/tasks/{$mainId}/complete", ['summary' => 'trop tôt'])
            ->assertStatus(422);

        // Complete blocker first
        $this->postJson("/api/tasks/{$blockerId}/take-charge")->assertOk();
        $this->postJson("/api/tasks/{$blockerId}/start")->assertOk();
        $this->postJson("/api/tasks/{$blockerId}/complete", ['summary' => 'ok'])->assertOk();

        Sanctum::actingAs($chef);
        $this->postJson("/api/tasks/{$blockerId}/validate")->assertOk();

        Sanctum::actingAs($agent);
        $this->postJson("/api/tasks/{$mainId}/complete", ['summary' => 'synthèse prête'])
            ->assertOk()
            ->assertJsonPath('status', TaskStatus::AValider->value);
    }

    public function test_create_office_document_and_submit_to_ged(): void
    {
        DocumentType::query()->firstOrCreate(
            ['code' => 'NOTE'],
            ['name' => 'Note', 'is_active' => true]
        );

        $structureId = \App\Models\Structure::query()->value('id');
        $chef = User::factory()->create(['structure_id' => $structureId]);
        $chef->assignRole('Chef de division');

        Sanctum::actingAs($chef);

        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Préparer note',
            'assignee_id' => $chef->id,
            'structure_id' => $structureId,
        ])->assertCreated()->json('id');

        $link = $this->postJson("/api/tasks/{$taskId}/documents/office", [
            'title' => 'Note de synthèse',
            'format' => 'docx',
        ])->assertCreated()->json();

        $this->assertNotEmpty($link['document']['id'] ?? null);
        $this->assertSame('working', $link['role']);

        $this->postJson("/api/tasks/{$taskId}/documents/{$link['id']}/submit-ged")
            ->assertOk()
            ->assertJsonPath('submitted_to_ged', true)
            ->assertJsonPath('role', 'final');

        $this->getJson("/api/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('document_links.0.submitted_to_ged', true);
    }

    public function test_dependency_cycle_rejected(): void
    {
        $chef = User::factory()->create();
        $chef->assignRole('Chef de division');
        Sanctum::actingAs($chef);

        $a = $this->postJson('/api/tasks', ['title' => 'A', 'assignee_id' => $chef->id])->json('id');
        $b = $this->postJson('/api/tasks', ['title' => 'B', 'assignee_id' => $chef->id])->json('id');

        $this->postJson("/api/tasks/{$a}/dependencies", [
            'related_task_id' => $b,
            'relation' => 'blocks',
        ])->assertCreated();

        $this->postJson("/api/tasks/{$b}/dependencies", [
            'related_task_id' => $a,
            'relation' => 'blocks',
        ])->assertStatus(422);
    }
}
