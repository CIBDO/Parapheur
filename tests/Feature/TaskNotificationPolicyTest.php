<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskNotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Structure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->structure = Structure::query()->create([
            'code' => 'TST',
            'name' => 'Structure test',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
    }

    public function test_assignment_sends_mail_to_assignee_only(): void
    {
        Notification::fake();

        [$chef, $agent] = $this->chefAndAgent();

        Sanctum::actingAs($chef);
        $this->postJson('/api/tasks', [
            'title' => 'Note de synthèse',
            'assignee_id' => $agent->id,
        ])->assertCreated();

        Notification::assertSentTo($agent, TaskAssignedNotification::class, function ($n, $channels) {
            return in_array('mail', $channels, true) && in_array('database', $channels, true);
        });
        Notification::assertNotSentTo($chef, TaskAssignedNotification::class);
    }

    public function test_take_charge_is_app_only_and_skips_actor(): void
    {
        Notification::fake();

        [$chef, $agent] = $this->chefAndAgent();

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Collecte',
            'assignee_id' => $agent->id,
            'validator_id' => $chef->id,
        ])->assertCreated()->json('id');

        Notification::fake();

        Sanctum::actingAs($agent);
        $this->postJson("/api/tasks/{$taskId}/take-charge")->assertOk();

        Notification::assertSentTo($chef, TaskStatusNotification::class, function (TaskStatusNotification $n, $channels) {
            return $n->event === 'taken_charge'
                && $channels === ['database'];
        });
        Notification::assertNotSentTo($agent, TaskStatusNotification::class);
    }

    public function test_complete_sends_single_validation_mail(): void
    {
        Notification::fake();

        [$chef, $agent] = $this->chefAndAgent();

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Rapport',
            'assignee_id' => $agent->id,
            'validator_id' => $chef->id,
        ])->assertCreated()->json('id');

        Sanctum::actingAs($agent);
        $this->postJson("/api/tasks/{$taskId}/take-charge")->assertOk();
        $this->postJson("/api/tasks/{$taskId}/start")->assertOk();

        Notification::fake();
        $this->postJson("/api/tasks/{$taskId}/complete", ['summary' => 'Fait'])->assertOk();

        Notification::assertSentTo($chef, TaskStatusNotification::class, function (TaskStatusNotification $n, $channels) {
            return $n->event === 'validation_requested'
                && in_array('mail', $channels, true);
        });

        Notification::assertNotSentTo($chef, TaskStatusNotification::class, function (TaskStatusNotification $n) {
            return $n->event === 'completed';
        });
        Notification::assertNotSentTo($agent, TaskStatusNotification::class);
    }

    public function test_return_sends_mail_to_assignee_only(): void
    {
        Notification::fake();

        [$chef, $agent] = $this->chefAndAgent();
        $other = User::factory()->create(['structure_id' => $this->structure->id]);
        $other->assignRole('Agent');

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Dossier',
            'assignee_id' => $agent->id,
            'validator_id' => $chef->id,
            'contributor_ids' => [$other->id],
        ])->assertCreated()->json('id');

        Sanctum::actingAs($agent);
        $this->postJson("/api/tasks/{$taskId}/take-charge")->assertOk();
        $this->postJson("/api/tasks/{$taskId}/start")->assertOk();
        $this->postJson("/api/tasks/{$taskId}/complete", ['summary' => 'Fait'])->assertOk();

        Notification::fake();
        Sanctum::actingAs($chef);
        $this->postJson("/api/tasks/{$taskId}/return", [
            'motif' => 'Pièce manquante',
        ])->assertOk();

        Notification::assertSentTo($agent, TaskStatusNotification::class, function (TaskStatusNotification $n, $channels) {
            return $n->event === 'returned' && in_array('mail', $channels, true);
        });
        Notification::assertNotSentTo($other, TaskStatusNotification::class);
        Notification::assertNotSentTo($chef, TaskStatusNotification::class);
    }

    public function test_comment_is_app_only(): void
    {
        Notification::fake();

        [$chef, $agent] = $this->chefAndAgent();

        Sanctum::actingAs($chef);
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Commentaire',
            'assignee_id' => $agent->id,
            'validator_id' => $chef->id,
        ])->assertCreated()->json('id');

        Notification::fake();
        Sanctum::actingAs($agent);
        $this->postJson("/api/tasks/{$taskId}/comments", [
            'body' => 'Point d’étape',
        ])->assertCreated();

        Notification::assertSentTo($chef, TaskStatusNotification::class, function (TaskStatusNotification $n, $channels) {
            return $n->event === 'comment' && $channels === ['database'];
        });
        Notification::assertNotSentTo($agent, TaskStatusNotification::class);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function chefAndAgent(): array
    {
        $chef = User::factory()->create(['structure_id' => $this->structure->id]);
        $chef->assignRole('Chef de division');
        $agent = User::factory()->create(['structure_id' => $this->structure->id]);
        $agent->assignRole('Agent');

        return [$chef, $agent];
    }
}
