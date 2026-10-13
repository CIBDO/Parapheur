<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthIdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_token_can_access_me(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['auth.idle_timeout' => 30]);

        $user = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_idle_token_is_revoked_with_401(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['auth.idle_timeout' => 30]);

        $user = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $plain = $user->createToken('spa')->plainTextToken;

        /** @var PersonalAccessToken $accessToken */
        $accessToken = $user->tokens()->latest('id')->firstOrFail();

        // Simule une dernière activité trop ancienne
        Cache::put(
            'auth:idle:token:'.$accessToken->id,
            now()->subMinutes(45)->getTimestamp(),
            now()->addHour()
        );

        $this->withToken($plain)
            ->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'IDLE_TIMEOUT');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $accessToken->id,
        ]);
    }

    public function test_idle_check_can_be_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['auth.idle_timeout' => 0]);

        $user = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $plain = $user->createToken('spa')->plainTextToken;
        $accessToken = $user->tokens()->latest('id')->firstOrFail();

        Cache::put(
            'auth:idle:token:'.$accessToken->id,
            now()->subDays(2)->getTimestamp(),
            now()->addHour()
        );

        $this->withToken($plain)
            ->getJson('/api/auth/me')
            ->assertOk();
    }
}
