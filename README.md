# e-Parapheur DGTCP

Plateforme de dématérialisation des circuits de consultation, visa et validation administrative (Temps 1 — sans intégrations externes ni signature électronique qualifiée).

## Stack

- Laravel 12 + Sanctum + Spatie Permission
- Vue 3 + Vuexy / Vuetify
- MySQL/MariaDB (prod) ou SQLite (local)

## Démarrage local

```bash
composer install
cp .env.example .env
php artisan key:generate
# SQLite déjà prévu dans .env.example
php artisan migrate --seed
pnpm install
composer run dev
```

Ou séparément : `php artisan serve` + `pnpm dev`.

## Comptes de démo (mot de passe : `password`)

| Email                       | Rôle              |
| --------------------------- | ----------------- |
| `admin@dgtcp.local`         | Administrateur    |
| `dg@dgtcp.local`            | Directeur Général |
| `secretariat@dgtcp.local`   | Secrétariat DG    |
| `directeur.dsi@dgtcp.local` | Directeur DSI     |
| `agent.dsi@dgtcp.local`     | Agent DSI         |
| `directeur.dfm@dgtcp.local` | Directeur DFM     |

## Modules Temps 1

- Authentification API + RBAC
- Structures / types de documents
- Parapheur, dépôt, transmission libre
- Commentaires, visa, validation administrative, retours, archivage
- Versions immuables + stockage privé + URLs signées
- Instructions, réunions, délégations, reporting
- Bureau DG (tablette)

## Temps 2 — ONLYOFFICE

Édition collaborative DOCX/XLSX/PPTX (sans LDAP/SSO/OCR) :

```bash
# .env : ONLYOFFICE_ENABLED=true + JWT (voir .env.example)
php artisan onlyoffice:generate-jwt-secret   # copier dans ONLYOFFICE_JWT_SECRET
docker compose up -d
composer run dev
```

Guide : [`docs/onlyoffice.md`](docs/onlyoffice.md). Identité : [`docs/identity.md`](docs/identity.md).

## Temps 2 (lots ultérieurs)

LDAP/SSO, FTS/OCR, interop/SAE/PWA — après ONLYOFFICE.
