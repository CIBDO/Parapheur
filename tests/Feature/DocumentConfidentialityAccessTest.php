<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentConfidentialityAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_circuit_agent_cannot_view_restreint_document_of_other_structure(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agentDsi = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $agentDfm = User::query()->where('email', 'directeur.dfm@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'CONF-REST-001',
            'object' => 'Note restreinte DSI',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agentDsi->id,
            'current_assignee_id' => $agentDsi->id,
            'status' => DocumentStatus::Depose->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Restreint->value,
            'expected_action' => 'consultation',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
        ]);

        $this->actingAs($agentDfm, 'sanctum')
            ->getJson('/api/parapheur/documents/'.$document->id)
            ->assertForbidden();
    }

    public function test_same_structure_agent_can_view_restreint_without_being_in_circuit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $directeurDsi = User::query()->where('email', 'directeur.dsi@dgtcp.local')->firstOrFail();
        $agentDsi = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $document = Document::query()->create([
            'reference' => 'CONF-REST-002',
            'object' => 'Note restreinte visible structure',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $directeurDsi->id,
            'current_assignee_id' => $directeurDsi->id,
            'status' => DocumentStatus::Depose->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Restreint->value,
            'expected_action' => 'consultation',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
        ]);

        $this->actingAs($agentDsi, 'sanctum')
            ->getJson('/api/parapheur/documents/'.$document->id)
            ->assertOk()
            ->assertJsonPath('id', $document->id);
    }

    public function test_dg_can_view_confidentiel_without_circuit_but_not_tres_confidentiel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $confidentiel = Document::query()->create([
            'reference' => 'CONF-C-001',
            'object' => 'Note confidentielle',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'current_assignee_id' => $agent->id,
            'status' => DocumentStatus::Depose->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::Confidentiel->value,
            'expected_action' => 'consultation',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
        ]);

        $tresConfidentiel = Document::query()->create([
            'reference' => 'CONF-TC-001',
            'object' => 'Note très confidentielle',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'current_assignee_id' => $agent->id,
            'status' => DocumentStatus::Depose->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::TresConfidentiel->value,
            'expected_action' => 'consultation',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
        ]);

        $this->actingAs($dg, 'sanctum')
            ->getJson('/api/parapheur/documents/'.$confidentiel->id)
            ->assertOk();

        $this->actingAs($dg, 'sanctum')
            ->getJson('/api/parapheur/documents/'.$tresConfidentiel->id)
            ->assertForbidden();
    }

    public function test_agent_cannot_receive_tres_confidentiel_document(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        // Agent sans documents.validate / documents.vise
        $plainAgent = User::query()->create([
            'name' => 'Agent Test',
            'email' => 'agent.test@dgtcp.local',
            'password' => 'password',
            'structure_id' => $dsi->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $plainAgent->assignRole('Agent');

        $document = Document::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'CONF-TC-TX',
            'object' => 'Transmission très confidentielle',
            'document_type_id' => $type->id,
            'structure_id' => $dsi->id,
            'author_id' => $agent->id,
            'current_assignee_id' => $agent->id,
            'status' => DocumentStatus::Brouillon->value,
            'priority' => 'normale',
            'confidentiality' => DocumentConfidentiality::TresConfidentiel->value,
            'expected_action' => 'validation',
            'document_date' => now()->toDateString(),
            'current_version' => 1,
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/parapheur/documents/'.$document->id.'/transmit', [
                'to_user_id' => $plainAgent->id,
                'expected_action' => 'validation',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => sprintf(
                'Le destinataire %s n’a pas le niveau d’habilitation requis pour ce document.',
                $plainAgent->name
            )]);
    }

    public function test_upload_rejects_disallowed_extension(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $dsi = Structure::query()->where('code', 'DSI')->firstOrFail();

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/parapheur/documents', [
                'object' => 'Fichier interdit',
                'document_type_id' => $type->id,
                'structure_id' => $dsi->id,
                'main_file' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['main_file']);
    }
}
