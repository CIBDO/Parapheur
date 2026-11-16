<?php

namespace App\Support;

use App\Http\Middleware\AttachAuthTokenFromCookie;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

final class AuthTokenCookie
{
    public static function attach(JsonResponse $response, string $plainTextToken): JsonResponse
    {
        $minutes = (int) config('sanctum.expiration', 480);
        if ($minutes <= 0) {
            $minutes = 480;
        }

        return $response->withCookie(self::make($plainTextToken, $minutes));
    }

    public static function clear(JsonResponse $response): JsonResponse
    {
        return $response->withCookie(self::make('', -60));
    }

    private static function make(string $value, int $minutes): Cookie
    {
        $secure = app()->environment('production')
            || filter_var(config('session.secure'), FILTER_VALIDATE_BOOL);

        // Cookie non chiffré (exclu EncryptCookies) : le token Sanctum doit rester lisible tel quel.
        return Cookie::create(
            AttachAuthTokenFromCookie::COOKIE_NAME,
            $value,
            $minutes > 0 ? now()->addMinutes($minutes) : now()->subHour(),
            '/',
            config('session.domain'),
            $secure,
            true, // httpOnly
            false, // raw
            config('session.same_site', 'lax') ?: Cookie::SAMESITE_LAX,
        );
    }
}
