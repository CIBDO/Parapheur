<?php

namespace App\Services\Identity;

use App\Contracts\IdentityProvider;
use App\Models\User;
use RuntimeException;

/**
 * Stub LDAP — brancher php-ldap / LdapRecord au lot Temps 2 ultérieur.
 */
class LdapIdentityProvider implements IdentityProvider
{
    public function attempt(array $credentials): ?User
    {
        throw new RuntimeException(
            'IDENTITY_DRIVER=ldap n’est pas encore branché. Voir docs/identity.md.'
        );
    }

    public function driver(): string
    {
        return 'ldap';
    }
}
