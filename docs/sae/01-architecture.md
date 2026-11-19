# Architecture SAE

## Principes

1. GED ≠ SAE : statut GED `archive` / `verse` ≠ enregistrement SAE.
2. Pas de duplication binaire : `archive_records.document_id` (+ `document_version_id` figé).
3. Contrôles d’accès centralisés dans `ArchiveAccessService` + policies.
4. Pont métier : `ArchiveModuleBridgeService::proposeFromDocument` → versement **brouillon** (action explicite, jamais silencieuse).
5. Documentation institutionnelle reste via `InstitutionalDepositService` (canal distinct).

## Couches

```
Vue (pages/archives + GED) → /api/archives → Controllers → Services → Models → MySQL
                                         ↓
                                   AuditLogger
```

## Services clés

| Service | Rôle |
|---------|------|
| `ArchiveModuleBridgeService` | Proposition SAE depuis modules |
| `ArchiveIngestService` | Cycle de vie des versements |
| `ArchiveImportService` | Import CSV preview/commit |
| `ArchiveRestoreService` | Restauration depuis réplique |
| `ArchivePreventiveService` | Incidents + climat manuel |
| `ArchivePackageService` | SIP/AIP/DIP + réplication |
| `ArchiveIntegrityService` | Contrôles SHA-256 |

## Stockage

- Disks `archives` / `archives_replica` (local ou S3 via env)
- Immutabilité applicative si `ARCHIVES_OBJECT_LOCK=true` (refus overwrite réplique)
- Object Lock natif S3 : activation côté bucket

## Intégrations

- GED : bouton « Proposer au SAE » → `POST /archives/propose`
- Archives : poste numérisation → `POST /archives/digitize/batch` (upload / caméra / scanner → documents + versement)
- Courrier / Parapheur / Réunions / Tâches / Productions : même endpoint propose avec `document_id` + `source_module`
- ONLYOFFICE : consultation seule de la version figée
