# Archives institutionnelles (SAE) — E-Trésor

Module distinct de la GED opérationnelle. Les binaires restent dans `documents` / `document_versions` ; le SAE référence ces pièces via `archive_records.document_id`.

## Phases

| Phase | Contenu | Statut |
|-------|---------|--------|
| 0 | Audit, écarts, architecture | Livré |
| 1 | Socle électronique | Livré |
| 2 | Archives physiques | Livré |
| 3 | Gouvernance | Livré |
| 4 | Préservation avancée | Livré |
| Prod | Ponts métier, import, restore, préventive, seeds | Livré |

## Documents

- [00-audit-et-ecarts.md](00-audit-et-ecarts.md)
- [01-architecture.md](01-architecture.md)
- [02-modele-donnees.md](02-modele-donnees.md)
- [03-permissions-et-workflows.md](03-permissions-et-workflows.md)

## API production

- `POST /archives/propose` — proposition versement depuis un document
- `POST /archives/records/{id}/hybrid` — support hybride
- `POST /archives/imports/preview|…/commit` — import CSV
- `POST /archives/packages/{id}/restore` · `POST /archives/records/{id}/restore`
- `GET|POST /archives/preventive/events` · `GET|POST /archives/preventive/climate`

## Ops checklist

1. `php artisan migrate`
2. `php artisan db:seed` (inclut `ArchivesSeeder`)
3. Configurer `.env` : `ARCHIVES_*`, optionnellement S3 + `ARCHIVES_OBJECT_LOCK=true`
4. Schedule : `archives:verify-integrity` (quotidien)
5. Optionnel : installer Ghostscript pour migration PDF/A
6. Smoke UI : GED « Proposer au SAE », Import, Conservation préventive, Préservation

## Hors scope assumé

- Capteurs IoT, provisionnement bucket Object Lock, tableau de gestion officiel Mali (délais).
