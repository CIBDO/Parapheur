# Audit E-Trésor et écarts SAE

## Réutilisable

- `documents` + versions/checksums (`PrivateDocumentStorage`)
- GED : classement opérationnel, rétention simple, legal hold, antivirus
- `InstitutionalDepositService` (pont modules → documentation) — distinct du SAE
- Multi-structures (`Structure`, `StructureBoundaryService`)
- Spatie + CASL (`PermissionCatalog`, `AbilityMapper`)
- `AuditLogger` append-only
- `ArchivePackService` (pack preuves parapheur — base d’export)

## Écarts traités (Phases 1–4 + production)

- Namespace `archives.*`, subject CASL `Archives`
- Versements, records, fonds / séries / classement versionné
- Magasins / boîtes / prêts / récolement
- Tableaux de gestion (templates) + élimination 3 rôles
- SIP/AIP/DIP, réplication, vérification d’intégrité, restauration depuis réplique
- Proposition SAE explicite (`POST /archives/propose`) depuis GED
- Import CSV (preview / commit), conservation préventive manuelle
- Support hybride (`medium`, `physical_reference`)
- Disks `archives` / `archives_replica` S3-ready + `ARCHIVES_OBJECT_LOCK`

## Écarts résiduels (hors code / ops)

- Object Lock S3 réel : dépend du bucket hébergeur (`ARCHIVES_OBJECT_LOCK=true`)
- Délais de rétention Mali : saisie métier (templates non validés, délais à 0)
- Capteurs IoT climatiques : API manuelle seulement
- Conversion PDF/A : nécessite Ghostscript (`gs`) sur le serveur
