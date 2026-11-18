<?php

namespace Tests\Feature;

use App\Enums\ArchiveRecordStatus;
use App\Models\ArchiveRecord;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivesGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_retention_rule_apply_hold_blocks_disposition_execution(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $proposer = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $authorizer = User::query()->where('email', 'chef.dsi@dgtcp.local')->first()
            ?? User::query()->where('email', '!=', $proposer->email)->where('email', '!=', 'admin@dgtcp.local')->firstOrFail();

        $proposer->givePermissionTo([
            'archives.proposer_elimination',
            'archives.consulter',
            'archives.gerer_conservation',
        ]);
        $authorizer->givePermissionTo([
            'archives.autoriser_elimination',
            'archives.executer_elimination',
            'archives.consulter',
            'archives.gerer_conservation',
        ]);

        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'GED-GOV-001',
            'object' => 'Archive gouvernance',
            'title' => 'Archive gouvernance',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $proposer->id,
            'status' => 'valide',
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => 'ged',
            'document_date' => now()->subYears(10)->toDateString(),
            'current_version' => 1,
            'archive_status' => 'verse',
        ]);

        $record = ArchiveRecord::query()->create([
            'permanent_id' => 'AR-GOV-001',
            'reference' => 'GED-GOV-001',
            'title' => 'Archive gouvernance',
            'status' => ArchiveRecordStatus::Conserve,
            'confidentiality' => 'normal',
            'structure_id' => $structure->id,
            'document_id' => $document->id,
            'date_end' => now()->subYears(10)->toDateString(),
            'conserved_at' => now()->subYears(9),
            'created_by' => $admin->id,
        ]);

        $rule = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/retention-rules', [
                'code' => 'TG-TEST',
                'name' => 'Règle test',
                'current_years' => 1,
                'intermediate_years' => 1,
                'final_disposition' => 'elimination',
                'legal_basis' => 'À valider institutionnellement',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/retention-rules/'.$rule['id'].'/validate')
            ->assertOk()
            ->assertJsonPath('is_validated', true);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/records/'.$record->id.'/retention', [
                'retention_rule_id' => $rule['id'],
            ])
            ->assertOk()
            ->assertJsonPath('retention_rule_id', $rule['id']);

        $hold = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/records/'.$record->id.'/holds', [
                'reason' => 'Contentieux en cours',
                'hold_type' => 'contentieux',
            ])
            ->assertCreated()
            ->json();

        $this->assertDatabaseHas('archive_records', [
            'id' => $record->id,
            'status' => 'gele',
        ]);

        $disposition = $this->actingAs($proposer, 'sanctum')
            ->postJson('/api/archives/dispositions', [
                'title' => 'Élimination test',
                'structure_id' => $structure->id,
                'disposition_type' => 'elimination',
                'record_ids' => [$record->id],
                'justification' => 'Échéance atteinte',
            ])
            ->assertCreated()
            ->json();

        $this->assertTrue(
            collect($disposition['items'])->contains(fn ($i) => $i['status'] === 'bloque_gel')
        );

        $this->actingAs($authorizer, 'sanctum')
            ->postJson('/api/archives/dispositions/'.$disposition['id'].'/authorize', [
                'pv_reference' => 'PV-TEST-001',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'autorisee');

        $this->actingAs($authorizer, 'sanctum')
            ->postJson('/api/archives/dispositions/'.$disposition['id'].'/execute')
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/holds/'.$hold['id'].'/lift', [
                'notes' => 'Contentieux clos',
            ])
            ->assertOk();

        // Re-propose clean path without gel for execution success
        $record->refresh();
        $record->status = ArchiveRecordStatus::Conserve;
        $record->save();

        $disposition2 = $this->actingAs($proposer, 'sanctum')
            ->postJson('/api/archives/dispositions', [
                'title' => 'Élimination après levée',
                'structure_id' => $structure->id,
                'disposition_type' => 'elimination',
                'record_ids' => [$record->id],
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($authorizer, 'sanctum')
            ->postJson('/api/archives/dispositions/'.$disposition2['id'].'/authorize')
            ->assertOk();

        // Executor must be different from authorizer (separation)
        $executor = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $this->actingAs($executor, 'sanctum')
            ->postJson('/api/archives/dispositions/'.$disposition2['id'].'/execute')
            ->assertOk()
            ->assertJsonPath('status', 'executee');

        $this->assertDatabaseHas('archive_records', [
            'id' => $record->id,
            'status' => 'elimine',
        ]);
    }

    public function test_communication_grant_and_reports(): void
    {
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'GED-COM-001',
            'object' => 'Doc communication',
            'title' => 'Doc communication',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $agent->id,
            'status' => 'valide',
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => 'ged',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'verse',
        ]);

        $record = ArchiveRecord::query()->create([
            'permanent_id' => 'AR-COM-001',
            'title' => 'Doc communication',
            'status' => ArchiveRecordStatus::Conserve,
            'confidentiality' => 'normal',
            'structure_id' => $structure->id,
            'document_id' => $document->id,
            'created_by' => $admin->id,
            'conserved_at' => now(),
        ]);

        $com = $this->actingAs($agent, 'sanctum')
            ->postJson('/api/archives/communications', [
                'record_id' => $record->id,
                'purpose' => 'Étude interne',
                'access_mode' => 'consultation',
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/archives/communications/'.$com['id'].'/authorize', ['days' => 7])
            ->assertOk()
            ->assertJsonPath('status', 'autorisee');

        $this->assertDatabaseHas('archive_access_grants', [
            'record_id' => $record->id,
            'user_id' => $agent->id,
            'ability' => 'view',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/reports/responsible')
            ->assertOk()
            ->assertJsonStructure(['records_total', 'retention_due', 'holds_active']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/reports/dg')
            ->assertOk()
            ->assertJsonStructure(['coverage', 'transfers', 'occupation', 'compliance']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/archives/reports/export-metadata')
            ->assertOk()
            ->assertJsonFragment(['permanent_id' => 'AR-COM-001']);
    }

    public function test_proposer_cannot_authorize_own_disposition(): void
    {
        $proposer = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $proposer->givePermissionTo([
            'archives.proposer_elimination',
            'archives.autoriser_elimination',
            'archives.consulter',
        ]);

        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $document = Document::query()->create([
            'reference' => 'GED-SEP-001',
            'object' => 'Sep roles',
            'title' => 'Sep roles',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'author_id' => $proposer->id,
            'status' => 'valide',
            'priority' => 'normale',
            'confidentiality' => 'normal',
            'expected_action' => 'consultation',
            'origin' => 'ged',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
            'archive_status' => 'verse',
        ]);

        $record = ArchiveRecord::query()->create([
            'permanent_id' => 'AR-SEP-001',
            'title' => 'Sep roles',
            'status' => ArchiveRecordStatus::Conserve,
            'confidentiality' => 'normal',
            'structure_id' => $structure->id,
            'document_id' => $document->id,
            'created_by' => $proposer->id,
            'conserved_at' => now(),
        ]);

        $disposition = $this->actingAs($proposer, 'sanctum')
            ->postJson('/api/archives/dispositions', [
                'title' => 'Prop sans autorisation auto',
                'structure_id' => $structure->id,
                'record_ids' => [$record->id],
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($proposer, 'sanctum')
            ->postJson('/api/archives/dispositions/'.$disposition['id'].'/authorize')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Séparation des rôles : le proposant ne peut pas autoriser.']);
    }
}
