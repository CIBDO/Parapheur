<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\CalendarUnavailability;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_register_external_audience_request(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $type = AppointmentType::query()->where('code', 'AUDIENCE')->firstOrFail();

        $response = $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments', [
            'subject' => 'Audience partenaire test',
            'reason' => 'Présentation dossier',
            'appointment_type_id' => $type->id,
            'requester_name' => 'M. Koné',
            'requester_organization' => 'Partenaire XYZ',
            'origin_type' => 'partenaire',
            'intake_channel' => 'telephone',
            'director_id' => $dg->id,
            'priority' => 'importante',
        ]);

        $response->assertCreated()
            ->assertJsonPath('subject', 'Audience partenaire test');
        $this->assertNotEmpty($response->json('reference'));
        $this->assertStringStartsWith('RDV-', $response->json('reference'));
    }

    public function test_internal_request_propose_validate_confirm_flow(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $dsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($dsi, 'sanctum')->postJson('/api/appointments', [
            'subject' => 'Point DSI / DG',
            'reason' => 'Arbitrage technique',
            'as_request' => true,
            'proposed_availabilities' => [
                ['date' => now()->addDays(3)->toDateString(), 'start' => '10:00'],
            ],
            'director_id' => $dg->id,
        ])->assertCreated()->json('id');

        $start = now()->addDays(3)->setTime(10, 30)->toIso8601String();
        $this->actingAs($secretariat, 'sanctum')->postJson("/api/appointments/{$id}/propose-slot", [
            'start_at' => $start,
            'duration_minutes' => 30,
            'location' => 'Cabinet DG',
            'submit_to_dg' => true,
        ])->assertOk()->assertJsonPath('status', 'a_valider');

        $this->actingAs($dg, 'sanctum')->postJson("/api/appointments/{$id}/validate", [
            'confirm' => true,
        ])->assertOk()->assertJsonPath('status', 'confirme');
    }

    public function test_conflict_with_meeting_is_detected(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        Meeting::query()->create([
            'reference' => 'REU-TEST-001',
            'title' => 'Comité de Direction',
            'object' => 'Comité de Direction',
            'meeting_date' => now()->addDays(4)->toDateString(),
            'meeting_time' => '10:00:00',
            'end_time' => '11:30:00',
            'chair_id' => $dg->id,
            'secretary_id' => $secretariat->id,
            'created_by' => $secretariat->id,
            'status' => 'planifiee',
            'confidentiality' => 'normal',
            'priority' => 'normale',
        ]);

        $appointment = $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments', [
            'subject' => 'RDV en conflit',
            'director_id' => $dg->id,
            'status' => 'a_examiner',
        ])->assertCreated()->json();

        $response = $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments/'.$appointment['id'].'/propose-slot', [
            'start_at' => now()->addDays(4)->setTime(10, 30)->toIso8601String(),
            'duration_minutes' => 30,
            'submit_to_dg' => true,
        ]);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('conflicts'));
    }

    public function test_confidential_appointment_is_masked_for_agent(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments', [
            'subject' => 'Audience très confidentielle',
            'director_id' => $dg->id,
            'confidentiality' => 'tres_confidentiel',
            'start_at' => now()->addDays(1)->setTime(9, 0)->toIso8601String(),
            'duration_minutes' => 30,
            'direct_schedule' => true,
        ])->assertCreated()->json('id');

        $calendar = $this->actingAs($agent, 'sanctum')
            ->getJson('/api/appointments/calendar?from='.now()->toDateString().'&to='.now()->addDays(2)->toDateString())
            ->assertOk()
            ->json();

        $match = collect($calendar)->firstWhere('source_id', $id);
        if ($match) {
            $this->assertTrue($match['masked'] ?? false);
            $this->assertSame('Indisponible', $match['title']);
        }

        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/appointments/{$id}")
            ->assertForbidden();
    }

    public function test_convert_to_meeting_keeps_appointment_link(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments', [
            'subject' => 'RDV à transformer',
            'director_id' => $dg->id,
            'start_at' => now()->addDays(6)->setTime(14, 0)->toIso8601String(),
            'duration_minutes' => 45,
            'direct_schedule' => true,
            'participant_ids' => [$dg->id, $secretariat->id],
        ])->assertCreated()->json('id');

        $response = $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/appointments/{$id}/convert-to-meeting", [])
            ->assertCreated();

        $this->assertNotEmpty($response->json('meeting.id'));
        $appointment = Appointment::query()->findOrFail($id);
        $this->assertSame($response->json('meeting.id'), $appointment->converted_meeting_id);
    }

    public function test_followup_creates_instruction(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $dsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();

        $id = $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments', [
            'subject' => 'Audience avec suite',
            'director_id' => $dg->id,
            'start_at' => now()->addHours(2)->toIso8601String(),
            'duration_minutes' => 30,
            'direct_schedule' => true,
        ])->assertCreated()->json('id');

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/appointments/{$id}/start")
            ->assertOk();

        $this->actingAs($secretariat, 'sanctum')
            ->postJson("/api/appointments/{$id}/finish", ['has_followup' => true])
            ->assertOk()
            ->assertJsonPath('status', 'suite_a_donner');

        $this->actingAs($secretariat, 'sanctum')->postJson("/api/appointments/{$id}/followups", [
            'kind' => 'instruction',
            'title' => 'La DSI doit produire une note sous 7 jours',
            'description' => 'Note de synthèse demandée en audience',
            'assignee_id' => $dsi->id,
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertCreated()->assertJsonPath('instruction_id', fn ($v) => ! empty($v));
    }

    public function test_unavailability_blocks_calendar(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $secretariat = User::query()->where('email', 'secretariat@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();

        CalendarUnavailability::query()->create([
            'user_id' => $dg->id,
            'kind' => 'mission',
            'title' => 'Mission test',
            'start_at' => now()->addDays(8)->setTime(8, 0),
            'end_at' => now()->addDays(8)->setTime(18, 0),
            'blocks_calendar' => true,
            'created_by' => $secretariat->id,
        ]);

        $appointment = $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments', [
            'subject' => 'RDV pendant mission',
            'director_id' => $dg->id,
        ])->assertCreated()->json();

        $this->actingAs($secretariat, 'sanctum')->postJson('/api/appointments/'.$appointment['id'].'/propose-slot', [
            'start_at' => now()->addDays(8)->setTime(10, 0)->toIso8601String(),
            'duration_minutes' => 30,
        ])->assertStatus(422);
    }
}
