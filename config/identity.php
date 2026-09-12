<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fournisseur d’identité
    |--------------------------------------------------------------------------
    |
    | local  — mots de passe Laravel (défaut)
    | ldap   — préparé (Active Directory / OpenLDAP) — non branché
    | sso    — préparé (SAML / OIDC) — non branché
    |
    | MFA TOTP hors scope de ce projet (reporté / non retenu).
    | Voir docs/identity.md
    |
    */

    'driver' => env('IDENTITY_DRIVER', 'local'),

    'ldap' => [
        'host' => env('LDAP_HOST'),
        'port' => (int) env('LDAP_PORT', 389),
        'base_dn' => env('LDAP_BASE_DN'),
        'bind_dn' => env('LDAP_BIND_DN'),
        'bind_password' => env('LDAP_BIND_PASSWORD'),
        'user_filter' => env('LDAP_USER_FILTER', '(sAMAccountName={username})'),
        'use_tls' => (bool) env('LDAP_USE_TLS', true),
    ],

    'sso' => [
        'provider' => env('SSO_PROVIDER', 'oidc'), // oidc|saml
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        'authorize_url' => env('SSO_AUTHORIZE_URL'),
        'token_url' => env('SSO_TOKEN_URL'),
        'userinfo_url' => env('SSO_USERINFO_URL'),
        'redirect_uri' => env('SSO_REDIRECT_URI'),
        'scopes' => env('SSO_SCOPES', 'openid profile email'),
    ],

];
