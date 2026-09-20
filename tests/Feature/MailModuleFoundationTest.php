<?php

namespace Tests\Feature;

use App\Enums\CorrespondenceDirection;
use App\Enums\CorrespondenceStatus;
use App\Enums\DocumentTemplateKind;
use App\Enums\DocumentTemplateVersionStatus;
use App\Models\Correspondence;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\NumberingSequence;
use App\Models\TransmissionSlip;
use App\Models\User;
use App\Services\CorrespondenceReplyService;
use App\Services\CorrespondenceService;
use App\Services\DocumentTemplateService;
use App\Services\NumberingService;
use App\Services\TransmissionSlipService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailModuleFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_numbering_uniqueness_under_sequential_calls(): void
    {
        $numberingService = app(NumberingService::class);

        NumberingSequence::query()->create([
            'code' => 'TEST',
            'year' => now()->year,
            'prefix' => 'TEST',
            'padding' => 6,
            'last_value' => 0,
            'reset_yearly' => true,
        ]);

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $numberingService->nextNumber('TEST');
        }

        $this->assertCount(5, array_unique($numbers));
        $this->assertEquals('TEST/'.now()->year.'/000001', $numbers[0]);
        $this->assertEquals('TEST/'.now()->year.'/000005', $numbers[4]);
    }

    public function test_departure_numbering_resyncs_when_sequence_lags(): void
    {
        $year = (int) now()->format('Y');

        NumberingSequence::query()->updateOrCreate(
            ['code' => 'DEP', 'year' => $year, 'structure_id' => null],
            [
                'prefix' => 'DEP',
                'padding' => 6,
                'last_value' => 0,
                'reset_yearly' => true,
            ]
        );

        Correspondence::query()->create([
            'direction' => CorrespondenceDirection::Sortant->value,
            'medium' => 'physique',
            'status' => CorrespondenceStatus::AExpedier->value,
            'subject' => 'Déjà numéroté',
            'correspondence_date' => now()->toDateString(),
            'departure_number' => "DEP/{$year}/000001",
            'is_registered' => true,
            'registered_at' => now(),
            'registered_by' => User::query()->where('email', 'agent.dsi@dgtcp.local')->value('id'),
        ]);

        $next = app(NumberingService::class)->generateDepartureNumber();

        $this->assertSame("DEP/{$year}/000002", $next);
    }

    public function test_create_incoming_correspondence_gets_arrival_number(): void
    {
        $user = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $correspondenceService = app(CorrespondenceService::class);

        $correspondence = $correspondenceService->createIncoming($user, [
            'subject' => 'Test courrier entrant',
            'correspondence_date' => now()->toDateString(),
            'received_at' => now(),
            'medium' => 'physique',
            'structure_id' => $user->structure_id,
        ]);

        $this->assertNotNull($correspondence->arrival_number);
        $this->assertStringStartsWith('ARR/', $correspondence->arrival_number);
        $this->assertEquals(CorrespondenceDirection::Entrant, $correspondence->direction);
        $this->assertEquals(CorrespondenceStatus::Enregistre, $correspondence->status);
        $this->assertTrue($correspondence->is_registered);
    }

    public function test_state_machine_rejects_invalid_transition(): void
    {
        $stateMachine = app(\App\Services\CorrespondenceStateMachine::class);
        $this->expectException(\InvalidArgumentException::class);
        $stateMachine->assertCanTransition(
            CorrespondenceStatus::Enregistre,
            CorrespondenceStatus::Expedie
        );
    }

    public function test_assignment_and_take_charge_flow(): void
    {
        $fromUser = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $toUser = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $correspondence = Correspondence::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'direction' => 'entrant',
            'medium' => 'physique',
            'status' => 'enregistre',
            'subject' => 'Test affectation',
            'correspondence_date' => now()->toDateString(),
            'received_at' => now(),
            'registered_by' => $fromUser->id,
            'structure_id' => $fromUser->structure_id,
        ]);

        $assignmentService = app(\App\Services\CorrespondenceAssignmentService::class);
        $assignments = $assignmentService->assign($correspondence, $fromUser, [[
            'to_user_id' => $toUser->id,
            'instruction_text' => 'Merci de traiter ce courrier rapidement',
            'due_date' => now()->addDays(3)->toDateString(),
        ]]);

        $this->assertCount(1, $assignments);
        $correspondence->refresh();
        $this->assertEquals('affecte', $correspondence->status->value);

        $updated = $assignmentService->takeCharge($assignments[0], $toUser);
        $this->assertEquals('pris_en_charge', $updated->status->value);
        $this->assertNotNull($assignments[0]->fresh()->instruction_id);
    }

    public function test_confidential_access_denied_without_permission(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $correspondence = Correspondence::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'direction' => 'entrant',
            'medium' => 'physique',
            'status' => 'enregistre',
            'subject' => 'Courrier confidentiel',
            'correspondence_date' => now()->toDateString(),
            'received_at' => now(),
            'registered_by' => $admin->id,
            'structure_id' => $admin->structure_id,
            'confidentiality' => 'confidentiel',
        ]);

        $accessService = app(\App\Services\CorrespondenceAccessService::class);
        $this->assertTrue($accessService->canView($admin, $correspondence));
        $this->assertFalse($accessService->canView($agent, $correspondence));
    }

    public function test_mail_view_all_can_open_other_structure_correspondence(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $directeur = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();

        $correspondence = Correspondence::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'direction' => 'sortant',
            'medium' => 'physique',
            'status' => 'expedie',
            'subject' => 'Sortant autre structure',
            'correspondence_date' => now()->toDateString(),
            'registered_by' => $admin->id,
            'owner_user_id' => $admin->id,
            'structure_id' => $admin->structure_id,
            'departure_number' => 'DEP/'.now()->year.'/000099',
            'is_registered' => true,
        ]);

        $this->assertNotEquals($admin->structure_id, $directeur->structure_id);
        $this->assertTrue($directeur->can('mail.view_all'));

        $this->actingAs($directeur, 'sanctum')
            ->getJson("/api/mail/correspondences/{$correspondence->id}")
            ->assertOk()
            ->assertJsonPath('id', $correspondence->id);
    }

    public function test_prepare_reply_creates_linked_outgoing(): void
    {
        $user = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $incoming = app(CorrespondenceService::class)->createIncoming($user, [
            'subject' => 'Demande initiale',
            'medium' => 'physique',
            'received_at' => now(),
            'structure_id' => $user->structure_id,
        ]);

        $reply = app(CorrespondenceReplyService::class)->prepareReply($incoming, $user);
        $this->assertEquals(CorrespondenceDirection::Sortant, $reply->direction);
        $this->assertEquals($incoming->id, $reply->reply_to_correspondence_id);
        $this->assertDatabaseHas('correspondence_links', [
            'source_correspondence_id' => $reply->id,
            'target_correspondence_id' => $incoming->id,
            'link_type' => 'reponse',
        ]);
    }

    public function test_transmission_slip_number_and_validate(): void
    {
        $user = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $this->actingAs($user);

        $incoming = app(CorrespondenceService::class)->createIncoming($user, [
            'subject' => 'Pour bordereau',
            'medium' => 'physique',
            'received_at' => now(),
            'structure_id' => $user->structure_id,
        ]);

        $slip = app(TransmissionSlipService::class)->create([
            'from_structure_id' => $user->structure_id,
            'to_structure_id' => $user->structure_id,
            'nature' => 'pour_traitement',
        ], [[
            'correspondence_id' => $incoming->id,
            'reference' => $incoming->arrival_number,
            'object' => $incoming->subject,
            'piece_count' => 1,
        ]]);

        $validated = app(TransmissionSlipService::class)->validate($slip);
        $this->assertNotNull($validated->number);
        $this->assertStringStartsWith('BT/', $validated->number);
        $this->assertEquals('valide', $validated->status->value);
    }

    public function test_template_publish_keeps_previous_version(): void
    {
        $user = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $service = app(DocumentTemplateService::class);

        $docx = UploadedFile::fake()->create('bordereau.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $template = $service->create($user, [
            'code' => 'BT-STD',
            'name' => 'Bordereau standard',
            'kind' => DocumentTemplateKind::BordereauTransmission->value,
        ], $docx);

        $v1 = $template->versions()->first();
        $service->publish($template, $v1, $user);

        $docx2 = UploadedFile::fake()->create('bordereau-v2.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $v2 = $service->addVersion($template, $user, $docx2, 'Mise à jour');
        $service->publish($template->fresh(), $v2, $user);

        $v1->refresh();
        $template->refresh();

        $this->assertEquals(DocumentTemplateVersionStatus::Remplace, $v1->status);
        $this->assertEquals(DocumentTemplateVersionStatus::Publie, $v2->fresh()->status);
        $this->assertEquals($v2->id, $template->current_published_version_id);
        $this->assertEquals(2, $template->versions()->count());
    }

    public function test_list_filters_unassigned_and_overdue(): void
    {
        $user = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $service = app(CorrespondenceService::class);

        $open = $service->createIncoming($user, [
            'subject' => 'Sans affectation',
            'medium' => 'physique',
            'received_at' => now(),
            'structure_id' => $user->structure_id,
        ]);

        $late = $service->createIncoming($user, [
            'subject' => 'En retard',
            'medium' => 'physique',
            'received_at' => now(),
            'due_date' => now()->subDay()->toDateString(),
            'structure_id' => $user->structure_id,
        ]);

        $unassigned = $service->list($user, ['unassigned' => 1]);
        $this->assertTrue(collect($unassigned->items())->contains(fn ($c) => $c->id === $open->id));

        $overdue = $service->list($user, ['overdue' => 1]);
        $this->assertTrue(collect($overdue->items())->contains(fn ($c) => $c->id === $late->id));
    }

    public function test_search_endpoint_returns_results(): void
    {
        $user = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $this->actingAs($user);

        app(CorrespondenceService::class)->createIncoming($user, [
            'subject' => 'Banque mondiale interconnexion',
            'medium' => 'physique',
            'received_at' => now(),
            'structure_id' => $user->structure_id,
        ]);

        $response = $this->getJson('/api/mail/search?q=Banque');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data') ?? []));
    }
}
