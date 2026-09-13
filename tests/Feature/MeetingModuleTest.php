<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_create_meeting_with_agenda_and_participants(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $dsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();

        $response = $this->actingAs($secretariat, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Comité de Direction test',
            'meeting_date' => now()->addDays(2)->toDateString(),
            'meeting_time' => '10:00',
            'end_time' => '12:00',
            'location' => 'Salle du Conseil',
            'chair_id' => $dg->id,
            'secretary_id' => $secretariat->id,
            'participant_ids' => [$dg->id, $dsi->id, $secretariat->id],
            'agenda_items' => [
                ['title' => 'Adoption du compte rendu précédent'],
                ['title' => 'Projet E-Tresor'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'brouillon')
            ->assertJsonPath('object', 'Comité de Direction test');

        $this->assertNotEmpty($response->json('reference'));
        $this->assertCount(3, $response->json('participants'));
        $this->assertGreaterThanOrEqual(2, count($response->json('agenda_items')));
    }

    public function test_agent_cannot_access_confidential_meeting_by_id(): void
    {
        $this->seed(DatabaseSeeder::class);

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Réunion confidentielle',
            'meeting_date' => now()->addDay()->toDateString(),
            'chair_id' => $dg->id,
            'confidentiality' => 'tres_confidentiel',
            'participant_ids' => [$dg->id, $secretariat->id],
        ])->assertCreated()->json('id');

        $this->actingAs($agent, 'sanctum')
            ->getJson('/api/meetings/'.$id)
            ->assertForbidden();
    }

    public function test_status_transition_is_controlled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Réunion transitions',
            'meeting_date' => now()->addDays(3)->toDateString(),
            'chair_id' => $dg->id,
            'participant_ids' => [$dg->id, $secretariat->id],
        ])->json('id');

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/transition", ['status' => 'cr_valide'])
            ->assertStatus(422);

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/transition", ['status' => 'en_preparation'])
            ->assertOk()
            ->assertJsonPath('status', 'en_preparation');
    }

    public function test_invitation_confirmation_decision_and_minutes_flow(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $dsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Comité de Direction — flux complet',
            'meeting_date' => now()->toDateString(),
            'meeting_time' => '09:00',
            'location' => 'Salle du Conseil',
            'chair_id' => $dg->id,
            'secretary_id' => $secretariat->id,
            'participant_ids' => [$dg->id, $dsi->id, $secretariat->id],
            'agenda' => "1. Adoption du CR\n2. Projet E-Tresor",
        ])->assertCreated()->json('id');

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/convocation")
            ->assertOk()
            ->assertJsonStructure(['html', 'document']);

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/send-invitations")
            ->assertOk()
            ->assertJsonPath('status', 'convoquee');

        $this->actingAs($dsi, 'sanctum')
            ->postJson("/api/meetings/{$id}/confirm", ['status' => 'confirme'])
            ->assertOk()
            ->assertJsonPath('confirmation_status', 'confirme');

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/transition", ['status' => 'prete'])
            ->assertOk();

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/transition", ['status' => 'en_cours'])
            ->assertOk()
            ->assertJsonPath('status', 'en_cours');

        $participantId = Meeting::query()->findOrFail($id)->participants()->where('user_id', $dsi->id)->value('id');
        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/participants/{$participantId}/attendance", [
                'attendance_status' => 'present',
            ])->assertOk();

        $agendaId = Meeting::query()->findOrFail($id)->agendaItems()->value('id');
        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/notes", [
                'visibility' => 'officielle',
                'section' => 'resume',
                'agenda_item_id' => $agendaId,
                'body' => 'Échanges sur le prototype E-Tresor.',
            ])->assertCreated();

        $this->actingAs($dsi, 'sanctum')
            ->postJson("/api/meetings/{$id}/notes", [
                'visibility' => 'privee',
                'body' => 'Note personnelle de la DSI.',
            ])->assertCreated();

        $decision = $this->actingAs($secretariat, 'sanctum')->postJson("/api/meetings/{$id}/decisions", [
            'title' => 'Finaliser le prototype E-Tresor',
            'assignee_id' => $dsi->id,
            'due_date' => now()->addDays(10)->toDateString(),
            'create_instruction' => true,
            'agenda_item_id' => $agendaId,
        ])->assertCreated();

        $this->assertNotNull($decision->json('instruction'));

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/transition", ['status' => 'terminee'])
            ->assertOk();

        $minuteId = $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/minutes", ['kind' => 'cr_detaille'])
            ->assertCreated()
            ->json('id');

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/meetings/{$id}/minutes/{$minuteId}/submit")
            ->assertOk();

        $this->actingAs($dg, 'sanctum')
            ->postJson("/api/meetings/{$id}/minutes/{$minuteId}/validate")
            ->assertOk()
            ->assertJsonPath('status', 'valide');

        $this->assertDatabaseHas('meeting_decisions', [
            'id' => $decision->json('id'),
            'title' => 'Finaliser le prototype E-Tresor',
        ]);
        $this->assertSame(MeetingStatus::CrValide, Meeting::query()->find($id)->status);
    }

    public function test_private_notes_are_not_visible_to_other_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $dsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Notes privées',
            'meeting_date' => now()->toDateString(),
            'chair_id' => $dg->id,
            'participant_ids' => [$dg->id, $dsi->id, $secretariat->id],
        ])->json('id');

        $this->actingAs($dsi, 'sanctum')->postJson("/api/meetings/{$id}/notes", [
            'visibility' => 'privee',
            'body' => 'Secret DSI',
        ])->assertCreated();

        $payload = $this->actingAs($dg, 'sanctum')->getJson("/api/meetings/{$id}")->json('notes');
        $bodies = collect($payload)->pluck('body')->all();
        $this->assertNotContains('Secret DSI', $bodies);
    }

    public function test_cannot_delete_meeting_after_convocation(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Réunion à convoquer',
            'meeting_date' => now()->addDay()->toDateString(),
            'chair_id' => $dg->id,
            'participant_ids' => [$dg->id, $secretariat->id],
        ])->json('id');

        $this->actingAs($secretariat, 'sanctum')->postJson("/api/meetings/{$id}/send-invitations")->assertOk();
        $this->actingAs($secretariat, 'sanctum')->deleteJson("/api/meetings/{$id}")->assertStatus(422);
    }

    public function test_existing_document_can_be_attached_without_duplication(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $document = Document::query()->firstOrFail();

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Dossier existant',
            'meeting_date' => now()->addDay()->toDateString(),
            'chair_id' => $dg->id,
            'document_ids' => [$document->id],
        ])->assertCreated()->json('id');

        $this->assertDatabaseHas('meeting_documents', [
            'meeting_id' => $id,
            'document_id' => $document->id,
        ]);
        $this->assertEquals(1, Document::query()->whereKey($document->id)->count());
    }

    public function test_dashboard_dg_includes_meeting_indicators(): void
    {
        $this->seed(DatabaseSeeder::class);
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $this->actingAs($dg, 'sanctum')
            ->getJson('/api/dashboard/dg')
            ->assertOk()
            ->assertJsonStructure([
                'meetings_today',
                'meetings_this_week',
                'meeting_decisions_open',
                'meeting_decisions_late',
            ]);
    }

    public function test_recurring_follow_up_item_is_created(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertGreaterThan(0, Meeting::query()->count());
        $this->assertGreaterThan(0, MeetingDecision::query()->count());

        $followUp = Meeting::query()->whereNotNull('parent_meeting_id')->first();
        $this->assertNotNull($followUp);
        $this->assertTrue(
            $followUp->agendaItems()->where('is_follow_up', true)->exists()
        );
    }

    public function test_recurring_series_is_pregenerated_with_limit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/meetings', [
            'object' => 'Comité récurrent test',
            'meeting_date' => now()->addDay()->toDateString(),
            'meeting_time' => '10:00',
            'chair_id' => $dg->id,
            'participant_ids' => [$dg->id, $secretariat->id],
            'agenda_items' => [['title' => 'Point unique']],
            'is_recurring' => true,
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'occurrences_limit' => 3,
            ],
        ])->assertCreated()->json('id');

        $meeting = Meeting::query()->findOrFail($id);
        $this->assertNotNull($meeting->recurrence_id);
        $this->assertSame(3, Meeting::query()->where('recurrence_id', $meeting->recurrence_id)->count());
    }

    public function test_admin_can_manage_meeting_types_and_templates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();

        $typeId = $this->actingAs($admin, 'sanctum')->postJson('/api/meeting-types', [
            'code' => 'TEST_TYPE',
            'name' => 'Type test',
            'sort_order' => 99,
            'is_active' => true,
        ])->assertCreated()->json('id');

        $this->actingAs($admin, 'sanctum')->putJson("/api/meeting-types/{$typeId}", [
            'code' => 'TEST_TYPE',
            'name' => 'Type test modifié',
            'sort_order' => 99,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('name', 'Type test modifié');

        $templateId = $this->actingAs($admin, 'sanctum')->postJson('/api/meeting-templates', [
            'kind' => 'convocation',
            'name' => 'Modèle test',
            'body' => '<p>{{object}}</p>',
            'is_default' => false,
            'is_active' => true,
        ])->assertCreated()->json('id');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/meeting-templates')
            ->assertOk()
            ->assertJsonFragment(['id' => $templateId]);
    }

    public function test_meeting_export_html_is_available(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $meeting = Meeting::query()->whereNotNull('reference')->firstOrFail();

        $this->actingAs($secretariat, 'sanctum')
            ->get("/api/meetings/{$meeting->id}/export/agenda")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}