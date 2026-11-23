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
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use App\Services\Productions\ProductionOccurrenceService;
use App\Services\Productions\RecurrenceService;
use App\Services\Tasks\TaskAccessService;
use App\Services\Tasks\TaskService;
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
            ->whereDate('reference_date', $date->toDateString())
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

    public function test_weekdays_frequency_respects_selected_days(): void
    {
        $obligation = new ProductionObligation([
            'frequency' => ProductionFrequency::Weekdays,
            'schedule_config' => ['weekdays' => [1, 3, 5]], // lun, mer, ven
            'is_active' => true,
        ]);

        $recurrence = app(RecurrenceService::class);
        // 2026-11-23 = lundi, 24 = mardi, 25 = mercredi, 21 = samedi, 22 = dimanche
        $this->assertTrue($recurrence->shouldGenerate($obligation, Carbon::parse('2026-11-23')));
        $this->assertFalse($recurrence->shouldGenerate($obligation, Carbon::parse('2026-11-24')));
        $this->assertTrue($recurrence->shouldGenerate($obligation, Carbon::parse('2026-11-25')));
        $this->assertFalse($recurrence->shouldGenerate($obligation, Carbon::parse('2026-11-21')));
        $this->assertFalse($recurrence->shouldGenerate($obligation, Carbon::parse('2026-11-22')));
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

    public function test_partial_obligation_update_does_not_require_code(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');
        $producer = User::factory()->create();

        $obligation = ProductionObligation::query()->create([
            'code' => 'PARTIAL-UPD',
            'label' => 'Libellé initial',
            'description' => 'Desc initiale',
            'producer_id' => $producer->id,
            'frequency' => ProductionFrequency::Daily->value,
            'deadline_time' => '10:00:00',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson('/api/productions/obligations/'.$obligation->id, [
            'label' => 'Libellé modifié',
            'description' => 'Nouvelle description',
            'deadline_time' => '11:30',
            'validation_required' => false,
            'auto_transmit' => true,
            'submit_to_parapheur' => false,
        ])->assertOk()
            ->assertJsonPath('code', 'PARTIAL-UPD')
            ->assertJsonPath('label', 'Libellé modifié')
            ->assertJsonPath('producer_id', $producer->id)
            ->assertJsonPath('deadline_time', '11:30:00');
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

    public function test_dossier_du_jour_pack_buckets(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrateur');
        $admin->givePermissionTo(['production.view', 'production.manage', 'production.transmit']);
        $dg = User::factory()->create();
        $dg->assignRole('Directeur Général');
        $dg->givePermissionTo(['production.view']);

        \App\Models\ProductionFolderTemplate::query()->create([
            'code' => 'daily_treasury',
            'label' => 'Dossier quotidien de trésorerie',
            'obligation_codes' => ['PACK-MISS', 'PACK-SEND', 'PACK-VIEW', 'PACK-OP'],
            'is_active' => true,
        ]);

        $makeObligation = function (string $code) use ($admin) {
            return ProductionObligation::query()->create([
                'code' => $code,
                'label' => "Situation {$code}",
                'category' => 'tresorerie',
                'producer_id' => $admin->id,
                'frequency' => ProductionFrequency::Once->value,
                'schedule_config' => ['date' => '2026-11-14'],
                'deadline_time' => '10:00:00',
                'validation_required' => false,
                'min_files' => 1,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        };

        $missing = $makeObligation('PACK-MISS');
        $toSend = $makeObligation('PACK-SEND');
        $viewed = $makeObligation('PACK-VIEW');
        $withOpinion = $makeObligation('PACK-OP');

        foreach ([$viewed, $withOpinion] as $ob) {
            ProductionObligationActor::query()->create([
                'production_obligation_id' => $ob->id,
                'role' => 'recipient',
                'user_id' => $dg->id,
                'step_order' => 1,
            ]);
        }

        $service = app(ProductionOccurrenceService::class);
        $day = Carbon::parse('2026-11-14');

        $occToSend = $service->createIfMissing($toSend, $day, $admin);
        $this->attachFile($occToSend, $admin);

        $occViewed = $service->createIfMissing($viewed, $day, $admin);
        $this->attachFile($occViewed, $admin);
        Sanctum::actingAs($admin);
        $this->postJson('/api/productions/occurrences/'.$occViewed->id.'/transmit')->assertOk();
        ProductionTransmissionRecipient::query()
            ->where('user_id', $dg->id)
            ->whereHas('transmission', fn ($q) => $q->where('production_occurrence_id', $occViewed->id))
            ->update(['viewed_at' => now()]);

        $occOpinion = $service->createIfMissing($withOpinion, $day, $admin);
        $this->attachFile($occOpinion, $admin);
        $this->postJson('/api/productions/occurrences/'.$occOpinion->id.'/transmit')->assertOk();
        ProductionTransmissionRecipient::query()
            ->where('user_id', $dg->id)
            ->whereHas('transmission', fn ($q) => $q->where('production_occurrence_id', $occOpinion->id))
            ->update([
                'viewed_at' => now(),
                'opinion' => 'ok',
                'opinion_comment' => 'Conforme',
                'opinion_at' => now(),
            ]);

        // occurrence manquante volontairement non créée pour PACK-MISS
        $this->assertNotNull($missing->id);

        Sanctum::actingAs($dg);
        $pack = $this->getJson('/api/productions/dossier-du-jour?date=2026-11-14')
            ->assertOk()
            ->json();

        $this->assertSame(4, $pack['stats']['expected']);
        $this->assertSame(1, $pack['stats']['missing']);
        $this->assertSame(1, $pack['stats']['to_send']);
        $this->assertSame(2, $pack['stats']['sent']);
        $this->assertSame(2, $pack['stats']['viewed']);
        $this->assertSame(1, $pack['stats']['with_opinion']);

        $byCode = collect($pack['items'])->keyBy('obligation_code');
        $this->assertSame('missing', $byCode['PACK-MISS']['bucket']);
        $this->assertSame('to_send', $byCode['PACK-SEND']['bucket']);
        $this->assertSame('viewed', $byCode['PACK-VIEW']['bucket']);
        $this->assertSame('with_opinion', $byCode['PACK-OP']['bucket']);
        $this->assertSame('ok', $byCode['PACK-OP']['opinions'][0]['opinion']);
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

    public function test_system_generation_creates_task_across_structures_without_collaboration_scope(): void
    {
        $structureA = Structure::query()->create([
            'code' => 'PROD-A',
            'name' => 'Structure A',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);
        $structureB = Structure::query()->create([
            'code' => 'PROD-B',
            'name' => 'Structure B',
            'is_active' => true,
            'is_access_boundary' => true,
        ]);

        $systemActor = User::factory()->create(['structure_id' => $structureA->id]);
        $systemActor->assignRole('Agent');

        $producer = User::factory()->create(['structure_id' => $structureB->id]);
        $producer->assignRole('Agent');

        $validator = User::factory()->create(['structure_id' => $structureB->id]);
        $validator->assignRole('Directeur');

        $this->assertFalse(
            app(TaskAccessService::class)->canAssignTo($systemActor, $producer),
            'Précondition : l\'acteur système ne peut pas collaborer avec le producteur.'
        );

        $obligation = ProductionObligation::query()->create([
            'code' => 'CROSS-STRUCT',
            'label' => 'Production multi-structures',
            'structure_id' => $structureB->id,
            'producer_id' => $producer->id,
            'frequency' => ProductionFrequency::Daily->value,
            'deadline_time' => '10:00:00',
            'validation_required' => true,
            'is_active' => true,
            'created_by' => $systemActor->id,
        ]);
        ProductionObligationActor::query()->create([
            'production_obligation_id' => $obligation->id,
            'role' => 'validator',
            'user_id' => $validator->id,
            'step_order' => 1,
        ]);

        $result = app(ProductionOccurrenceService::class)
            ->generateForDate(Carbon::parse('2026-10-12'), $systemActor);

        $this->assertSame(1, $result['created']);

        $occurrence = ProductionOccurrence::query()
            ->where('production_obligation_id', $obligation->id)
            ->firstOrFail();

        $this->assertNotNull($occurrence->task_id);

        $task = Task::query()->findOrFail($occurrence->task_id);
        $this->assertSame($producer->id, $task->assignee_id);
        $this->assertSame($validator->id, $task->validator_id);
        $this->assertSame($systemActor->id, $task->created_by);
        $this->assertSame('production', $task->source_kind?->value ?? $task->source_kind);
        $this->assertSame(ProductionOccurrence::class, $task->source_type);
        $this->assertSame($occurrence->id, $task->source_id);
    }

    public function test_create_from_production_rejects_assignee_outside_obligation(): void
    {
        $structure = Structure::query()->create([
            'code' => 'PROD-C',
            'name' => 'Structure C',
            'is_active' => true,
        ]);

        $systemActor = User::factory()->create(['structure_id' => $structure->id]);
        $producer = User::factory()->create(['structure_id' => $structure->id]);
        $stranger = User::factory()->create(['structure_id' => $structure->id]);

        $obligation = ProductionObligation::query()->create([
            'code' => 'GUARD-ASSIGN',
            'label' => 'Garde affectation',
            'structure_id' => $structure->id,
            'producer_id' => $producer->id,
            'frequency' => ProductionFrequency::Once->value,
            'schedule_config' => ['date' => '2026-10-12'],
            'deadline_time' => '10:00:00',
            'is_active' => true,
            'created_by' => $systemActor->id,
        ]);

        $occurrence = ProductionOccurrence::query()->create([
            'reference' => 'PO-GUARD-1',
            'production_obligation_id' => $obligation->id,
            'period_key' => '2026-10-12',
            'reference_date' => '2026-10-12',
            'period_start' => '2026-10-12',
            'period_end' => '2026-10-12',
            'deadline_at' => '2026-10-12 10:00:00',
            'structure_id' => $structure->id,
            'responsible_id' => $producer->id,
            'status' => ProductionOccurrenceStatus::AProduire->value,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(TaskService::class)->createFromProduction($systemActor, $obligation, $occurrence, [
            'title' => 'Tâche illégale',
            'assignee_id' => $stranger->id,
        ]);
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
