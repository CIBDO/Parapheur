# Identité & confiance (P1)

## MFA

Non retenu pour ce projet (abstraction / hors scope). L’authentification repose sur le mot de passe local (et plus tard LDAP/SSO).

## Fournisseur d’identité

| Driver | État | Config |
|--------|------|--------|
| `local` | Actif | mots de passe Laravel |
| `ldap` | Stub | `LDAP_*` dans `.env` |
| `sso` | Stub | `SSO_*` (OIDC/SAML) |

`IDENTITY_DRIVER=local|ldap|sso` — seul `local` est opérationnel. Brancher LdapRecord / Socialite au lot Temps 2.

## URLs signées

Les téléchargements portent un `jti` anti-rejeu (cache) :

- download : **1 usage**, TTL 15 min
- stream : multi-usages limités, TTL 15 min
- OnlyOffice : multi-usages limités, TTL 30 min

Voir `config/parapheur.php` / `SIGNED_URL_*`.

## Rotation JWT OnlyOffice

```bash
php artisan onlyoffice:generate-jwt-secret
```

1. Ancien secret → `ONLYOFFICE_JWT_SECRET_PREVIOUS`
2. Nouveau → `ONLYOFFICE_JWT_SECRET` + `JWT_SECRET` du conteneur
3. Redémarrer Document Server
4. Retirer `ONLYOFFICE_JWT_SECRET_PREVIOUS`
