<?php

namespace App\Services\Identity;

use App\Contracts\IdentityProvider;
use App\Models\User;
use RuntimeException;

/**
 * Stub SSO (OIDC/SAML) — brancher Socialite / SAML au lot Temps 2 ultérieur.
 */
class SsoIdentityProvider implements IdentityProvider
{
    public function attempt(array $credentials): ?User
    {
        throw new RuntimeException(
            'IDENTITY_DRIVER=sso n’est pas encore branché. Voir docs/identity.md.'
        );
    }

    public function driver(): string
    {
        return 'sso';
    }
}
