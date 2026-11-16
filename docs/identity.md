# Identité & confiance (P1)

## MFA (TOTP)

Optionnel via `AUTH_MFA_ENABLED=true`.

- Setup : `POST /api/auth/mfa/setup` puis `POST /api/auth/mfa/confirm` avec code 6 chiffres
- Login : si l’utilisateur a activé le MFA → `requiresMfa` + `mfaChallenge`, puis `POST /api/auth/mfa/verify`
- Désactivation : `POST /api/auth/mfa/disable` (mot de passe + code)

Implémentation : `App\Services\Mfa\TotpService` (RFC 6238, sans dépendance externe).

## Session SPA

Le token Sanctum est posé en cookie **HttpOnly** `parapheur_token` (plus de Bearer lisible en JS).
Le front garde seulement un marqueur `accessToken=1` pour les guards UI + `userData`.

## Fournisseur d’identité

| Driver | État | Config |
|--------|------|--------|
| `local` | Actif | mots de passe Laravel |
| `ldap` | Stub | `LDAP_*` dans `.env` |
| `sso` | Stub | `SSO_*` (OIDC/SAML) |

`IDENTITY_DRIVER=local|ldap|sso` — seul `local` est opérationnel. Brancher LdapRecord / Socialite au lot Temps 2.

## Signature électronique

**Non permise au Mali.** Le produit reste en visa / validation administrative uniquement.
Les libellés / valeurs `signature` sont migrés vers `validation`.

## URLs signées

Les téléchargements portent un `jti` anti-rejeu (cache) :

- download : **1 usage**, TTL 15 min
- stream : multi-usages limités, TTL 15 min
- OnlyOffice : multi-usages limités, TTL 30–120 min

Voir `config/parapheur.php` / `SIGNED_URL_*`.

## Rotation JWT OnlyOffice

```bash
php artisan onlyoffice:generate-jwt-secret
```

1. Ancien secret → `ONLYOFFICE_JWT_SECRET_PREVIOUS`
2. Nouveau → `ONLYOFFICE_JWT_SECRET` + `JWT_SECRET` du conteneur
3. Redémarrer Document Server
4. Retirer `ONLYOFFICE_JWT_SECRET_PREVIOUS`
