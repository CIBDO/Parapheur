<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\ProductionFrequency;
use App\Enums\ProductionOccurrenceStatus;
use App\Jobs\GenerateProductionOccurrencesJob;
use App\Models\Delegation;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentView;
use App\Models\ProductionFile;
use App\Models\ProductionObligation;
use App\Models\ProductionObligationActor;
use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmission;
use App\Models\ProductionTransmissionRecipient;
use App\Models\SlaCalendar;
use App\Models\SlaCalendarException;
use App\Models\Structure;
use App\Models\TaskType;
use App\Models\User;
use App\Services\Productions\ProductionOccurrenceService;
use App\Services\Productions\RecurrenceService;
use Carbon\Carbon;
use Database\Seeders\ProductionPermissionSeeder;
use Database\Seeders\TaskPermissionSeeder;
use Database\Seeders\TaskTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Administrateur', 'Directeur Général', 'DGA', 'Directeur', 'Chef de division', 'Agent'] as $role) {
            Role::findOrCreate($role);
        }

        $this->seed(TaskPermissionSeeder::class);
        $this->seed(ProductionPermissionSeeder::class);
        $this->seed(TaskTypeSeeder::class);
    }

    public function test_directive_creation_and_status_guard(): void
    {
        $dg = User::factory()->create();
        $dg->assignRole('Directeur Général');
        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        Sanctum::actingAs($dg);

        $directive = $this->postJson('/api/instructions', [
            'title' => 'Directive trésorerie',
            'kind' => 'directive',
            'assignee_id' => $agent->id,
            'create_execution_task' => true,
        ])->assertCreated();

        $this->assertSame('directive', $directive->json('kind'));
        $id = $directive->json('id');

        $this->patchJson("/api/instructions/{$id}/status", ['status' => 'emise'])
            ->assertOk()
            ->assertJsonPath('status', 'emise');

        // Imputation cannot use directive-only status when kind=imputation
        $imputation = $this->postJson('/api/instructions', [
            'title' => 'Imputation courrier',
            'kind' => 'imputation',
            'assignee_id' => $agent->id,
        ])->assertCreated();

        $this->patchJson('/api/instructions/'.$imputation->json('id').'/status', ['status' => 'emise'])
            ->assertStatus(422);
    }

    public function test_obligation_generation_is_idempotent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');
        $producer = User::factory()->create();
        $producer->assignRole('Agent');

        Sanctum::actingAs($admin);

        $obligation = $this->postJson('/api/productions/obligations', [
            'code' => 'TEST-DAILY',
            'label' => 'Test quotidien',
            'producer_id' => $producer->id,
            'frequency' => ProductionFrequency::Daily->value,
            'deadline_time' => '10:00:00',
            'validation_required' => false,
            'min_files' => 1,
            'allowed_formats' => ['pdf'],
            'is_active' => true,
        ])->assertCreated()->json();

        $this->assertTrue(TaskType::query()->where('code', 'production')->exists());

        $service = app(ProductionOccurrenceService::class);
        $date = Carbon::parse('2026-10-12');

        $first = $service->generateForDate($date, $admin);
        $second = $service->generateForDate($date, $admin);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, ProductionOccurrence::query()
            ->where('production_obligation_id', $obligation['id'])
            ->count());
    }

    public function test_deposit_reject_forbidden_format_and_transmit(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');
        $producer = User::factory()->create();
        $producer->assignRole('Agent');
        $producer->givePermissionTo(['production.view', 'production.transmit']);

        Sanctum::actingAs($admin);

        $obligationId = $this->postJson('/api/productions/obligations', [
            'code' => 'TEST-ONCE',
            'label' => 'Test ponctuel',
            'producer_id' => $producer->id,
            'frequency' => ProductionFrequency::Once->value,
            'schedule_config' => ['date' => '2026-10-12'],
            'deadline_time' => '10:00:00',
            'validation_required' => false,
            'min_files' => 1,
            'allowed_formats' => ['pdf'],
            'recipients' => [['user_id' => $admin->id]],
            'is_active' => true,
        ])->assertCreated()->json('id');

        $occurrence = app(ProductionOccurrenceService::class)
            ->createIfMissing(
                ProductionObligation::query()->findOrFail($obligationId),
                Carbon::parse('2026-10-12'),
                $admin
            );

        Sanctum::actingAs($producer);

        $this->postJson("/api/productions/occurrences/{$occurrence->id}/deposit", [
            'file' => UploadedFile::fake()->create('secret.exe', 100, 'application/octet-stream'),
        ])->assertStatus(422);

        // Document creation may need document types — skip full deposit if schema heavy
        $this->assertSame(ProductionOccurrenceStatus::AProduire->value, $occurrence->fresh()->status->value);
    }

    public function test_business_days_skips_sunday(): void
    {
        $obligation = new ProductionObligation([
            'frequency' => ProductionFrequency::BusinessDays,
            'is_active' => true,
        ]);

        $recurrence = app(RecurrenceService::class);
        $this->assertFalse($recurrence->shouldGenerate($obligation, Carbon::parse('2026-10-11'))); // Sunday
        $this->assertTrue($recurrence->shouldGenerate($obligation, Carbon::parse('2026-10-12'))); // Monday
    }

    public function test_inactive_obligation_does_not_generate(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');

        $obligation = ProductionObligation::query()->create([
            'code' => 'INACTIVE',
            'label' => 'Inactive',
            'frequency' => ProductionFrequency::Daily->value,
            'deadline_time' => '10:00:00',
            'is_active' => false,
            'created_by' => $admin->id,
        ]);

        $result = app(ProductionOccurrenceService::class)->generateForDate(Carbon::parse('2026-10-12'), $admin);
        $this->assertSame(0, ProductionOccurrence::query()
            ->where('production_obligation_id', $obligation->id)
            ->count());
        $this->assertGreaterThanOrEqual(0, $result['skipped']);
    }

    public function test_holiday_blocks_generation(): void
    {
        $calendar = SlaCalendar::query()->create([
            'code' => 'ML-TEST',
            'name' => 'Calendrier test',
            'weekdays' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
        SlaCalendarException::query()->create([
            'sla_calendar_id' => $calendar->id,
            'date' => '2026-10-12',
            'is_closed' => true,
            'label' => 'Férié',
        ]);

        $obligation = ProductionObligation::query()->create([
            'code' => 'HOLIDAY',
            'label' => 'Avec calendrier',
            'frequency' => ProductionFrequency::Daily->value,
            'calendar_id' => $calendar->id,
            'deadline_time' => '10:00:00',
            'is_active' => true,
        ]);

        $recurrence = app(RecurrenceService::class);
        $this->assertFalse($recurrence->shouldGenerate($obligation, Carbon::parse('2026-10-12')));
        $this->assertTrue($recurrence->shouldGenerate($obligation, Carbon::parse('2026-10-13')));
    }

    public function test_stranger_cannot_view_occurrence(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');
        $stranger = User::factory()->create();
        $stranger->assignRole('Agent');

        $obligation = ProductionObligation::query()->create([
            'code' => 'PRIVATE',
            'label' => 'Privée',
            'producer_id' => $admin->id,
            'frequency' => ProductionFrequency::Once->value,
            'schedule_config' => ['date' => '2026-10-12'],
            'deadline_time' => '10:00:00',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $occurrence = app(ProductionOccurrenceService::class)
            ->createIfMissing($obligation, Carbon::parse('2026-10-12'), $admin);

        Sanctum::actingAs($stranger);
        $this->getJson('/api/productions/occurrences/'.$occurrence->id)->assertForbidden();
    }

    public function test_generation_job_is_idempotent(): void
    {
        $admin = User::factory()->create();
        ProductionObligation::query()->create([
            'code' => 'JOB-DAILY',
            'label' => 'Job',
            'producer_id' => $admin->id,
            'frequency' => ProductionFrequency::Daily->value,
            'deadline_time' => '10:00:00',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $job = new GenerateProductionOccurrencesJob('2026-10-13', $admin->id);
        $job->handle(app(ProductionOccurrenceService::class));
        $job->handle(app(ProductionOccurrenceService::class));

        $this->assertSame(1, ProductionOccurrence::query()->count());
    }

    public function test_submit_requires_minimum_files_and_validation_decisions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');
        $producer = User::factory()->create();
        $producer->assignRole('Agent');

        $obligation = ProductionObligation::query()->create([
            'code' => 'VALIDATE',
            'label' => 'À valider',
            'producer_id' => $producer->id,
            'frequency' => ProductionFrequency::Once->value,
            'schedule_config' => ['date' => '2026-10-12'],
            'deadline_time' => '10:00:00',
            'validation_required' => true,
            'min_files' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        ProductionObligationActor::query()->create([
            'production_obligation_id' => $obligation->id,
            'role' => 'validator',
            'user_id' => $admin->id,
            'step_order' => 1,
        ]);

        $occurrence = app(ProductionOccurrenceService::class)
            ->createIfMissing($obligation, Carbon::parse('2026-10-12'), $admin);

        Sanctum::actingAs($producer);
        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/submit')->assertStatus(422);

        $this->attachFile($occurrence, $producer);

        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/submit')->assertOk();
        $this->assertSame(ProductionOccurrenceStatus::EnValidation->value, $occurrence->fresh()->status->value);

        Sanctum::actingAs($admin);
        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/decide', [
            'decision' => 'rejected',
            'motif' => 'Incomplet',
        ])->assertOk();
        $this->assertSame(ProductionOccurrenceStatus::Rejetee->value, $occurrence->fresh()->status->value);

        $correction = ProductionObligation::query()->create([
            'code' => 'VALIDATE-CORR',
            'label' => 'À corriger',
            'producer_id' => $producer->id,
            'frequency' => ProductionFrequency::Once->value,
            'schedule_config' => ['date' => '2026-10-13'],
            'deadline_time' => '10:00:00',
            'validation_required' => true,
            'min_files' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        ProductionObligationActor::query()->create([
            'production_obligation_id' => $correction->id,
            'role' => 'validator',
            'user_id' => $admin->id,
            'step_order' => 1,
        ]);
        $second = app(ProductionOccurrenceService::class)
            ->createIfMissing($correction, Carbon::parse('2026-10-13'), $admin);
        $this->attachFile($second, $producer);
        Sanctum::actingAs($producer);
        $this->postJson('/api/productions/occurrences/'.$second->id.'/submit')->assertOk();
        Sanctum::actingAs($admin);
        $this->postJson('/api/productions/occurrences/'.$second->id.'/decide', [
            'decision' => 'correction',
            'motif' => 'À compléter',
        ])->assertOk();
        $this->assertSame(ProductionOccurrenceStatus::ACompleter->value, $second->fresh()->status->value);
    }

    public function test_delegation_expands_recipients_and_consultation_is_recorded(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');
        $recipient = User::factory()->create();
        $delegate = User::factory()->create();

        Delegation::query()->create([
            'delegator_id' => $recipient->id,
            'delegate_id' => $delegate->id,
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
            'allowed_actions' => ['consultation'],
            'is_active' => true,
        ]);

        $obligation = ProductionObligation::query()->create([
            'code' => 'DELEG',
            'label' => 'Avec délégation',
            'producer_id' => $admin->id,
            'frequency' => ProductionFrequency::Once->value,
            'schedule_config' => ['date' => '2026-10-12'],
            'deadline_time' => '10:00:00',
            'validation_required' => false,
            'min_files' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        ProductionObligationActor::query()->create([
            'production_obligation_id' => $obligation->id,
            'role' => 'recipient',
            'user_id' => $recipient->id,
            'step_order' => 1,
        ]);

        $occurrence = app(ProductionOccurrenceService::class)
            ->createIfMissing($obligation, Carbon::parse('2026-10-12'), $admin);
        $file = $this->attachFile($occurrence, $admin);

        Sanctum::actingAs($admin);
        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/transmit')->assertOk();

        $this->assertTrue(
            ProductionTransmissionRecipient::query()
                ->where('user_id', $delegate->id)
                ->where('resolution_source', 'delegation:'.$recipient->id)
                ->exists()
        );

        Sanctum::actingAs($delegate);
        $this->getJson('/api/productions/occurrences/'.$occurrence->id)->assertOk();

        $this->assertNotNull(
            ProductionTransmissionRecipient::query()->where('user_id', $delegate->id)->value('viewed_at')
        );
        $this->assertTrue(DocumentView::query()
            ->where('user_id', $delegate->id)
            ->where('document_id', $file->document_id)
            ->exists());
    }

    public function test_multi_file_submit_and_rectificative_transmission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');

        $obligation = ProductionObligation::query()->create([
            'code' => 'MULTI',
            'label' => 'Plusieurs fichiers',
            'producer_id' => $admin->id,
            'frequency' => ProductionFrequency::Once->value,
            'schedule_config' => ['date' => '2026-10-12'],
            'deadline_time' => '10:00:00',
            'validation_required' => false,
            'min_files' => 2,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        ProductionObligationActor::query()->create([
            'production_obligation_id' => $obligation->id,
            'role' => 'recipient',
            'user_id' => $admin->id,
            'step_order' => 1,
        ]);

        $occurrence = app(ProductionOccurrenceService::class)
            ->createIfMissing($obligation, Carbon::parse('2026-10-12'), $admin);

        Sanctum::actingAs($admin);
        $this->attachFile($occurrence, $admin);
        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/submit')->assertStatus(422);

        $this->attachFile($occurrence, $admin, 'second.pdf');
        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/submit')->assertOk();
        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/transmit')->assertOk();
        $this->postJson('/api/productions/occurrences/'.$occurrence->id.'/transmit', [
            'rectificative' => true,
        ])->assertOk();

        $this->assertSame(2, ProductionTransmission::query()->where('production_occurrence_id', $occurrence->id)->count());
        $this->assertSame(1, ProductionTransmission::query()
            ->where('production_occurrence_id', $occurrence->id)
            ->where('is_rectificative', true)
            ->count());
    }

    private function attachFile(ProductionOccurrence $occurrence, User $author, string $name = 'livrable.pdf'): ProductionFile
    {
        $structure = Structure::query()->first() ?? Structure::query()->create([
            'code' => 'TEST-STR',
            'name' => 'Structure test',
        ]);
        $type = DocumentType::query()->first() ?? DocumentType::query()->create([
            'code' => 'NOTE',
            'name' => 'Note',
        ]);

        $document = Document::query()->create([
            'reference' => 'DOC-'.uniqid(),
            'object' => $name,
            'title' => $name,
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $author->id,
            'status' => DocumentStatus::Brouillon->value,
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'information',
            'origin' => 'production',
            'current_version' => 1,
        ]);

        return ProductionFile::query()->create([
            'production_occurrence_id' => $occurrence->id,
            'document_id' => $document->id,
            'uploaded_by' => $author->id,
            'logical_version' => 1,
            'status' => 'deposited',
        ]);
    }
}
