<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Delegation;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkflowAction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParapheurActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_cannot_validate_without_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $this->actingAs($agent)->postJson('/api/parapheur/documents', [
            'object' => 'Note droits',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'validation',
            'transmit_to' => $dg->id,
        ])->assertCreated();

        // L'agent n'est plus destinataire ; on force un document accessible (auteur)
        $doc = Document::query()->where('object', 'Note droits')->firstOrFail();

        $this->actingAs($agent)
            ->postJson("/api/parapheur/documents/{$doc->id}/validate", ['comment' => 'tentative'])
            ->assertForbidden();
    }

    public function test_avis_recommandation_and_complement(): void
    {
        $this->seed(DatabaseSeeder::class);

        $directeur = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $id = $this->actingAs($agent)->postJson('/api/parapheur/documents', [
            'object' => 'Note avis',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'avis',
            'transmit_to' => $directeur->id,
        ])->assertCreated()->json('id');

        $this->actingAs($directeur)
            ->postJson("/api/parapheur/documents/{$id}/comments", [
                'body' => 'Avis favorable sous réserve',
                'kind' => 'avis',
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'avis');

        $this->assertTrue(
            WorkflowAction::query()
                ->where('document_id', $id)
                ->where('action_type', 'avis')
                ->exists()
        );

        $this->actingAs($directeur)
            ->postJson("/api/parapheur/documents/{$id}/comments", [
                'body' => 'Je recommande d\'approuver',
                'kind' => 'recommandation',
            ])
            ->assertCreated();

        $this->actingAs($directeur)
            ->postJson("/api/parapheur/documents/{$id}/complement", [
                'comment' => 'Merci de joindre le budget',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'a_corriger');
    }

    public function test_reassign_uses_dedicated_endpoint(): void
    {
        $this->seed(DatabaseSeeder::class);

        $directeur = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $id = $this->actingAs($agent)->postJson('/api/parapheur/documents', [
            'object' => 'Note réaffectation',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'validation',
            'transmit_to' => $directeur->id,
        ])->assertCreated()->json('id');

        $this->actingAs($directeur)
            ->postJson("/api/parapheur/documents/{$id}/reassign", [
                'to_user_id' => $dg->id,
                'expected_action' => 'validation',
                'message' => 'Pour le DG',
            ])
            ->assertOk()
            ->assertJsonPath('current_assignee_id', $dg->id);

        $this->assertTrue(
            WorkflowAction::query()
                ->where('document_id', $id)
                ->where('action_type', 'reaffecter')
                ->exists()
        );
    }

    public function test_validation_records_active_delegation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $dga = User::query()->create([
            'name' => 'DGA Test',
            'email' => 'dga.test@dgtcp.local',
            'password' => 'password',
            'structure_id' => $dg->structure_id,
            'is_active' => true,
        ]);
        $dga->syncRoles(['DGA']);

        Delegation::query()->create([
            'delegator_id' => $dg->id,
            'delegate_id' => $dga->id,
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDays(5)->toDateString(),
            'allowed_actions' => ['validate', 'vise'],
            'is_active' => true,
            'reason' => 'Absence DG',
        ]);

        $id = $this->actingAs($agent)->postJson('/api/parapheur/documents', [
            'object' => 'Note délégation',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'expected_action' => 'validation',
            'transmit_to' => $dga->id,
        ])->assertCreated()->json('id');

        $this->actingAs($dga)
            ->postJson("/api/parapheur/documents/{$id}/validate", ['comment' => 'Validé en délégation'])
            ->assertOk();

        $approval = Approval::query()->where('document_id', $id)->firstOrFail();
        $this->assertSame($dga->id, $approval->user_id);
        $this->assertSame($dg->id, $approval->delegator_id);
    }
}
