<?php

namespace Tests\Feature;

use App\Http\Middleware\AttachAuthTokenFromCookie;
use App\Models\User;
use App\Services\Mfa\TotpService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthTokenCookieTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_copies_cookie_token_to_authorization_header(): void
    {
        $request = Request::create('/api/parapheur/counts', 'GET', cookies: [
            AttachAuthTokenFromCookie::COOKIE_NAME => '1|plain-test-token',
        ]);

        $middleware = new AttachAuthTokenFromCookie;
        $response = $middleware->handle($request, function (Request $req) {
            return response($req->bearerToken() ?? '');
        });

        $this->assertSame('1|plain-test-token', $response->getContent());
    }

    public function test_mfa_challenge_required_when_enabled_on_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['auth.mfa_enabled' => true]);

        $user = User::query()->where('email', 'agent.dsi@dgtcp.local')->firstOrFail();
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => true,
            'mfa_confirmed_at' => now(),
        ])->save();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'agent.dsi@dgtcp.local',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('requiresMfa', true)
            ->assertJsonStructure(['mfaChallenge']);

        $code = $totp->at($secret, (int) floor(time() / 30));

        $this->postJson('/api/auth/mfa/verify', [
            'mfa_challenge' => $login->json('mfaChallenge'),
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertCookie(AttachAuthTokenFromCookie::COOKIE_NAME);
    }

    public function test_login_cookie_authenticates_subsequent_api_calls(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@dgtcp.local',
            'password' => 'password',
        ])->assertOk()
            ->assertCookie(AttachAuthTokenFromCookie::COOKIE_NAME);

        $cookie = $login->getCookie(AttachAuthTokenFromCookie::COOKIE_NAME, false);
        $this->assertNotNull($cookie);
        $this->assertNotSame('', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());

        $this->call(
            'GET',
            '/api/auth/me',
            cookies: [AttachAuthTokenFromCookie::COOKIE_NAME => $cookie->getValue()],
            server: $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ]),
        )->assertOk()
            ->assertJsonPath('userData.email', 'admin@dgtcp.local');
    }
}
