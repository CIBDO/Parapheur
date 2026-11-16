<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relit le token Sanctum depuis un cookie HttpOnly (non accessible au JS).
 * Doit s’exécuter avant auth:sanctum, sur toutes les routes API.
 */
class AttachAuthTokenFromCookie
{
    public const COOKIE_NAME = 'parapheur_token';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $token = $request->cookies->get(self::COOKIE_NAME);
            if (! is_string($token) || $token === '') {
                $token = $request->cookie(self::COOKIE_NAME);
            }

            if (is_string($token) && $token !== '' && $token !== 'deleted') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
