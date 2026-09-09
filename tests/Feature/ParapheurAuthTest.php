<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParapheurAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dg_can_login_and_access_parapheur_counts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'dg@dgtcp.local',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['accessToken', 'userData', 'userAbilityRules']);

        $token = $response->json('accessToken');

        $this->withToken($token)
            ->getJson('/api/parapheur/counts')
            ->assertOk()
            ->assertJsonStructure(['a_traiter', 'a_valider', 'urgents']);
    }

    public function test_agent_can_create_and_transmit_document(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $dg = User::query()->where('email', 'dg@dgtcp.local')->firstOrFail();
        $type = DocumentType::query()->firstOrFail();
        $structure = Structure::query()->where('code', 'DSI')->firstOrFail();

        $token = $agent->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/parapheur/documents', [
            'object' => 'Note technique de test',
            'document_type_id' => $type->id,
            'structure_id' => $structure->id,
            'priority' => 'urgente',
            'expected_action' => 'validation',
            'transmit_to' => $dg->id,
            'transmit_message' => 'Pour validation DG',
        ]);

        $response->assertCreated()
            ->assertJsonPath('object', 'Note technique de test')
            ->assertJsonPath('current_assignee_id', $dg->id);
    }
}
