<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Révoque le token Sanctum après une période d'inactivité configurable.
 * Complète le timeout côté SPA (événements souris/clavier).
 */
class EnsureNotIdle
{
    public function handle(Request $request, Closure $next): Response
    {
        $idleMinutes = (int) config('auth.idle_timeout', 30);

        if ($idleMinutes <= 0) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $cacheKey = 'auth:idle:token:'.$token->id;
        $lastActivity = Cache::get($cacheKey);

        if (is_string($lastActivity) || is_int($lastActivity)) {
            $lastTs = is_numeric($lastActivity)
                ? (int) $lastActivity
                : strtotime((string) $lastActivity);

            if ($lastTs && (now()->getTimestamp() - $lastTs) > ($idleMinutes * 60)) {
                $token->delete();
                Cache::forget($cacheKey);

                return response()->json([
                    'message' => 'Session expirée pour cause d’inactivité. Veuillez vous reconnecter.',
                    'code' => 'IDLE_TIMEOUT',
                ], 401);
            }
        }

        Cache::put(
            $cacheKey,
            now()->getTimestamp(),
            now()->addMinutes($idleMinutes + 120)
        );

        return $next($request);
    }
}
