<?php

namespace Tests\Feature;

use App\Enums\CorrespondenceAssignmentStatus;
use App\Enums\CorrespondenceStatus;
use App\Models\Correspondence;
use App\Models\CorrespondenceAssignment;
use App\Models\CorrespondenceChannel;
use App\Models\CorrespondenceReminder;
use App\Models\DocumentPrintLog;
use App\Models\DocumentType;
use App\Models\TransmissionSlip;
use App\Models\User;
use App\Notifications\MailCorrespondenceNotification;
use App\Services\CirculationSheetService;
use App\Services\CorrespondenceAssignmentService;
use App\Services\CorrespondenceDispatchService;
use App\Services\CorrespondenceReminderService;
use App\Services\CorrespondenceService;
use App\Services\DocumentService;
use App\Services\TransmissionSlipService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailModuleCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $agent;

    protected Correspondence $correspondence;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $this->agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();

        $this->correspondence = app(CorrespondenceService::class)->createIncoming($this->admin, [
            'subject' => 'Test courrier complet',
            'medium' => 'physique',
            'received_at' => now(),
            'structure_id' => $this->admin->structure_id,
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);
    }

    public function test_it_can_request_complement_on_assignment(): void
    {
        $assignmentService = app(CorrespondenceAssignmentService::class);
        $assignments = $assignmentService->assign($this->correspondence, $this->admin, [[
            'to_user_id' => $this->agent->id,
            'instruction_text' => 'Traiter',
        ]]);

        $updated = $assignmentService->requestComplement($assignments[0], $this->agent, 'Besoin de plus d\'informations');

        $this->assertNotNull($updated);
        $this->assertEquals(
            CorrespondenceStatus::EnAttenteComplement,
            $this->correspondence->fresh()->status
        );
    }

    public function test_it_can_create_manual_reminder(): void
    {
        $service = app(CorrespondenceReminderService::class);

        $reminder = $service->createManual($this->correspondence, $this->admin, [
            'reminder_date' => Carbon::today()->addDays(2)->toDateString(),
            'type' => 'manual',
            'note' => 'Rappel important',
        ]);

        $this->assertDatabaseHas('correspondence_reminders', [
            'id' => $reminder->id,
            'correspondence_id' => $this->correspondence->id,
            'user_id' => $this->admin->id,
            'is_sent' => false,
        ]);
    }

    public function test_it_processes_due_reminders(): void
    {
        Notification::fake();

        CorrespondenceReminder::query()->create([
            'correspondence_id' => $this->correspondence->id,
            'user_id' => $this->agent->id,
            'reminder_date' => Carbon::yesterday(),
            'type' => 'deadline',
            'is_sent' => false,
        ]);

        $count = app(CorrespondenceReminderService::class)->processDueReminders();

        $this->assertEquals(1, $count);
        $this->assertEquals(1, $this->correspondence->reminders()->where('is_sent', true)->count());
        Notification::assertSentTo($this->agent, MailCorrespondenceNotification::class);
    }

    public function test_it_schedules_automatic_reminders(): void
    {
        CorrespondenceAssignment::query()->create([
            'correspondence_id' => $this->correspondence->id,
            'from_user_id' => $this->admin->id,
            'to_user_id' => $this->agent->id,
            'status' => CorrespondenceAssignmentStatus::Transmis,
        ]);

        $count = app(CorrespondenceReminderService::class)->scheduleAutomatic($this->correspondence);

        $this->assertGreaterThan(0, $count);
        $this->assertGreaterThan(0, $this->correspondence->reminders()->where('type', 'deadline')->count());
    }

    public function test_it_can_sync_parties(): void
    {
        app(CorrespondenceService::class)->syncParties($this->correspondence, $this->admin, [
            ['role' => 'from', 'name' => 'Expéditeur Test', 'organization' => 'Org Test'],
            ['role' => 'to', 'name' => 'Destinataire Test'],
            ['role' => 'ampliation', 'name' => 'Ampliation Test'],
        ]);

        $this->assertEquals(3, $this->correspondence->parties()->count());
        $this->assertDatabaseHas('correspondence_parties', [
            'correspondence_id' => $this->correspondence->id,
            'role' => 'from',
            'name' => 'Expéditeur Test',
        ]);
    }

    public function test_it_can_create_circulation_sheet(): void
    {
        $sheet = app(CirculationSheetService::class)->create($this->correspondence, $this->admin);

        $this->assertNotNull($sheet->id);
        $this->assertNotNull($sheet->number);
        $this->assertStringStartsWith('FC/', $sheet->number);
        $this->assertNull($sheet->document_id);
    }

    public function test_it_can_attach_signed_version(): void
    {
        $documentService = app(DocumentService::class);
        $type = DocumentType::query()->firstOrFail();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $document = $documentService->create($this->admin, [
            'origin' => 'courrier',
            'object' => 'Test doc',
            'title' => 'Test',
            'document_type_id' => $type->id,
        ], $file);

        $this->correspondence->update(['document_id' => $document->id]);

        $signedFile = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');
        $documentService->addVersion($document, $this->admin, $signedFile, 'Version signée physiquement');

        $this->assertGreaterThan(1, $document->versions()->count());
    }

    public function test_it_can_log_print_with_reason_and_reprint_flag(): void
    {
        $documentService = app(DocumentService::class);
        $type = DocumentType::query()->firstOrFail();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $document = $documentService->create($this->admin, [
            'origin' => 'courrier',
            'object' => 'Test doc',
            'title' => 'Test',
            'document_type_id' => $type->id,
        ], $file);

        $this->correspondence->update(['document_id' => $document->id]);

        DocumentPrintLog::query()->create([
            'document_id' => $document->id,
            'correspondence_id' => $this->correspondence->id,
            'user_id' => $this->admin->id,
            'page_count' => 2,
            'reason' => 'Réimpression pour signature',
            'is_reprint' => true,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('document_print_logs', [
            'document_id' => $document->id,
            'reason' => 'Réimpression pour signature',
            'is_reprint' => 1,
        ]);
    }

    public function test_it_can_acknowledge_transmission_slip_with_proof(): void
    {
        $incoming = app(CorrespondenceService::class)->createIncoming($this->admin, [
            'subject' => 'Pour bordereau preuve',
            'medium' => 'physique',
            'received_at' => now(),
            'structure_id' => $this->admin->structure_id,
        ]);

        $slip = app(TransmissionSlipService::class)->create([
            'from_structure_id' => $this->admin->structure_id,
            'to_structure_id' => $this->admin->structure_id,
            'nature' => 'pour_traitement',
        ], [[
            'correspondence_id' => $incoming->id,
            'reference' => $incoming->arrival_number,
            'object' => $incoming->subject,
            'piece_count' => 1,
        ]]);

        app(TransmissionSlipService::class)->validate($slip);
        $slip->update(['status' => 'transmis']);

        $proof = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');
        $updated = app(TransmissionSlipService::class)->acknowledge($slip->fresh(), [
            'received_at' => now(),
            'observations' => 'Reçu avec preuve',
        ], $proof, $this->admin);

        $this->assertEquals('recu', $updated->status->value);
    }

    public function test_reminder_command_runs_successfully(): void
    {
        $this->artisan('parapheur:remind-mail')->assertExitCode(0);
    }

    public function test_it_enriches_dashboard_with_new_stats(): void
    {
        $this->actingAs($this->admin);

        $this->getJson('/api/mail/dashboard/order-office')
            ->assertOk()
            ->assertJsonStructure([
                'received_today',
                'slips_in_progress',
                'circulation_sheets_open',
                'reminders_due_today',
            ]);
    }

    public function test_admin_can_crud_channels(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/mail/admin/channels', [
            'code' => 'TEST_CH',
            'name' => 'Canal Test',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('correspondence_channels', ['code' => 'TEST_CH']);

        $channelId = $response->json('id');

        $this->putJson("/api/mail/admin/channels/{$channelId}", [
            'name' => 'Canal Test Modifié',
        ])->assertOk();

        $this->assertDatabaseHas('correspondence_channels', ['name' => 'Canal Test Modifié']);

        CorrespondenceChannel::query()->whereKey($channelId)->delete();
    }

    public function test_it_notifies_on_dispatch(): void
    {
        Notification::fake();

        $outgoing = app(CorrespondenceService::class)->createOutgoing($this->admin, [
            'subject' => 'Sortant à expédier',
            'medium' => 'physique',
            'structure_id' => $this->admin->structure_id,
        ]);

        // Passer à un statut permettant l'expédition si nécessaire
        if ($outgoing->status !== CorrespondenceStatus::AExpedier
            && $outgoing->status !== CorrespondenceStatus::Expedie) {
            $outgoing->status = CorrespondenceStatus::AExpedier;
            $outgoing->save();
        }

        app(CorrespondenceDispatchService::class)->recordDispatch($outgoing, $this->agent, [
            'method' => 'courrier',
            'dispatched_at' => now(),
        ]);

        Notification::assertSentTo(
            $outgoing->registeredBy,
            MailCorrespondenceNotification::class
        );
    }

    public function test_reminder_api_endpoints(): void
    {
        $this->actingAs($this->admin);

        $this->postJson("/api/mail/correspondences/{$this->correspondence->id}/reminders", [
            'reminder_date' => Carbon::today()->addDay()->toDateString(),
            'note' => 'Via API',
        ])->assertCreated();

        $this->getJson("/api/mail/correspondences/{$this->correspondence->id}/reminders")
            ->assertOk()
            ->assertJsonCount(1);
    }
}
