<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            return response()->json([
                'message' => 'Vous devez changer votre mot de passe avant de continuer.',
                'code' => 'MUST_CHANGE_PASSWORD',
            ], 403);
        }

        return $next($request);
    }
}
