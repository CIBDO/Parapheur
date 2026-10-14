<?php

namespace App\Http\Middleware;

use App\Services\Organization\OrganizationalContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMailOrganizationalContext
{
    public function __construct(
        private readonly OrganizationalContextResolver $contexts,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Compte inactif.');
        }

        $request->attributes->set('organizational_context', $this->contexts->resolve($user));

        return $next($request);
    }
}
