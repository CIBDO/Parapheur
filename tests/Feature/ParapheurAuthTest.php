<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_new_user_must_change_password_on_first_login(): void
    {
        $this->seed(DatabaseSeeder::class);

        Notification::fake();

        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        $adminToken = $admin->createToken('test')->plainTextToken;

        $create = $this->withToken($adminToken)->postJson('/api/users', [
            'first_name' => 'Nouveau',
            'last_name' => 'Agent',
            'email' => 'nouveau.agent@dgtcp.local',
            'structure_id' => Structure::query()->firstOrFail()->id,
            'role' => 'Agent',
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('must_change_password', true);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $user = User::query()->where('email', 'nouveau.agent@dgtcp.local')->firstOrFail();
        $this->assertTrue($user->must_change_password);

        $plainPassword = null;
        Notification::assertSentTo(
            $user,
            UserAccountCreatedNotification::class,
            function (UserAccountCreatedNotification $notification) use (&$plainPassword) {
                $plainPassword = $notification->plainPassword;

                return $plainPassword !== '';
            }
        );

        $login = $this->postJson('/api/auth/login', [
            'email' => 'nouveau.agent@dgtcp.local',
            'password' => $plainPassword,
        ]);

        $login->assertOk()
            ->assertJsonPath('userData.mustChangePassword', true);

        $token = $login->json('accessToken');
        $this->assertNotEmpty($token);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->getJson('/api/parapheur/counts')
            ->assertForbidden()
            ->assertJsonPath('code', 'MUST_CHANGE_PASSWORD');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->postJson('/api/auth/change-password', [
                'current_password' => $plainPassword,
                'password' => 'NewSecurePass1!',
                'password_confirmation' => 'NewSecurePass1!',
            ])
            ->assertOk()
            ->assertJsonPath('userData.mustChangePassword', false);

        $this->assertFalse($user->fresh()->must_change_password);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/parapheur/counts')
            ->assertOk();
    }
}
