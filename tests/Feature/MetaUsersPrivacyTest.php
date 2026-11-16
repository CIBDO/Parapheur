<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetaUsersPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_meta_users_hides_email(): void
    {
        $this->seed(DatabaseSeeder::class);
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        Sanctum::actingAs($agent);

        $response = $this->getJson('/api/meta/users')->assertOk();
        $first = $response->json('0');

        $this->assertArrayHasKey('name', $first);
        $this->assertArrayNotHasKey('email', $first);
        $this->assertArrayNotHasKey('homeRoute', $first);
    }

    public function test_admin_meta_users_includes_email(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@dgtcp.local')->firstOrFail();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/meta/users')->assertOk();
        $first = $response->json('0');

        $this->assertArrayHasKey('email', $first);
    }
}
