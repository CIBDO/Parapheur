<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Instruction;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ParapheurPilotageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dg_can_classify_from_en_consultation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $id = $this->actingAs($agent, 'sanctum')->postJson('/api/parapheur/documents', [
            'object' => 'Note à classer après consultation',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'consultation',
            'transmit_to' => $dg->id,
        ])->assertCreated()->json('id');

        $this->actingAs($dg, 'sanctum')
            ->postJson("/api/parapheur/documents/{$id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('status', 'en_consultation');

        $this->actingAs($dg, 'sanctum')
            ->postJson("/api/parapheur/documents/{$id}/classify", [
                'comment' => 'Classement après consultation',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'classe');
    }

    public function test_dg_dashboard_returns_stats(): void
    {
        $this->seed(DatabaseSeeder::class);
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $response = $this->actingAs($dg, 'sanctum')
            ->getJson('/api/dashboard/dg')
            ->assertOk()
            ->assertJsonStructure([
                'received',
                'to_process',
                'urgent',
                'overdue',
                'validated',
                'returned',
                'by_status',
                'by_structure',
                'instructions_open',
                'instructions_late',
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('received'));
        $this->assertNotEmpty($response->json('by_structure'));
        $this->assertNotEmpty($response->json('by_status'));
    }

    public function test_archive_pack_download_and_frozen_comments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $id = $this->actingAs($agent)->postJson('/api/parapheur/documents', [
            'object' => 'Note archive pack',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'validation',
            'transmit_to' => $dg->id,
        ])->assertCreated()->json('id');

        $this->actingAs($dg)
            ->postJson("/api/parapheur/documents/{$id}/validate", ['comment' => 'OK'])
            ->assertOk();

        $this->actingAs($dg)
            ->postJson("/api/parapheur/documents/{$id}/archive", ['comment' => 'Classement'])
            ->assertOk();

        $this->actingAs($dg)
            ->postJson("/api/parapheur/documents/{$id}/comments", ['body' => 'Trop tard'])
            ->assertStatus(422);

        $this->actingAs($dg)
            ->get("/api/parapheur/documents/{$id}/archive-pack")
            ->assertOk();
    }

    public function test_recipient_is_notified_on_transmit(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $this->actingAs($agent, 'sanctum')->postJson('/api/parapheur/documents', [
            'object' => 'Note avec notification mail',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'validation',
            'transmit_to' => $dg->id,
        ])->assertCreated();

        Notification::assertSentTo(
            $dg,
            \App\Notifications\DocumentWorkflowNotification::class,
            function ($notification, $channels) {
                return $notification->event === 'transmitted'
                    && in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            }
        );
    }

    public function test_instruction_reminder_command(): void
    {
        Notification::fake();
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        Instruction::query()->create([
            'issuer_id' => $dg->id,
            'assignee_id' => $agent->id,
            'title' => 'Instruction en retard',
            'body' => 'À faire hier',
            'status' => 'a_faire',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->artisan('parapheur:remind-overdue-instructions')
            ->assertSuccessful();

        Notification::assertSentTo($agent, \App\Notifications\InstructionReminderNotification::class);
    }

    public function test_reporting_export_csv(): void
    {
        $this->seed(DatabaseSeeder::class);
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $this->actingAs($dg)
            ->get('/api/reporting/export?format=csv&scope=dg')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_meeting_decision_creates_instruction(): void
    {
        $this->seed(DatabaseSeeder::class);
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $meetingId = $this->actingAs($dg)->postJson('/api/meetings', [
            'title' => 'Comité DSI',
            'meeting_date' => now()->toDateString(),
            'participant_ids' => [$agent->id],
        ])->assertCreated()->json('id');

        $this->actingAs($dg)->postJson("/api/meetings/{$meetingId}/decisions", [
            'title' => 'Livrer le reporting',
            'body' => 'Avant vendredi',
            'assignee_id' => $agent->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'create_instruction' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('instructions', [
            'title' => 'Livrer le reporting',
            'assignee_id' => $agent->id,
        ]);
    }
}
