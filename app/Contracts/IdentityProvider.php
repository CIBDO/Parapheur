<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Contrat d’authentification pluggable (local / LDAP / SSO).
 */
interface IdentityProvider
{
    /**
     * Authentifie un utilisateur à partir des credentials locaux (email/password).
     * Les drivers LDAP/SSO lèveront une exception tant qu’ils ne sont pas branchés.
     *
     * @param  array{email?: string, password?: string, username?: string}  $credentials
     */
    public function attempt(array $credentials): ?User;

    public function driver(): string;
}
