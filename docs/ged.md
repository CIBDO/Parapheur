# GED — Gestion Électronique des Documents

Module transversal du Bureau Numérique DGTCP. La table `documents` existante reste la **source documentaire unique** : parapheur, réunions, agenda et instructions y font référence sans duplication physique.

## Architecture

```
Parapheur / Réunions / Agenda / Instructions
                    │
                    ▼
              documents (GED)
           ├── document_versions
           ├── document_attachments
           ├── document_tags
           ├── classification_nodes
           ├── document_links
           ├── document_access_rules
           ├── document_favorites / document_views
           ├── document_index_contents
           ├── document_retention_rules
           └── document_classification_rules
```

**Pas de table `document_files`** : le fichier principal = `document_versions` ; les autres fichiers = `document_attachments`.

## API (`/api/ged/...`)

| Méthode | Route | Rôle |
|---------|-------|------|
| GET | `/ged/dashboard` | Compteurs + indicateurs + récents |
| GET | `/ged/indicators` | Volumes / types / années |
| GET/POST | `/ged/documents` | Liste (ACL dans la requête) / création |
| GET/PUT/DELETE | `/ged/documents/{id}` | Fiche / métadonnées / corbeille |
| POST | `/ged/documents/{id}/classify` | Rattachement plan de classement |
| POST | `/ged/documents/{id}/archive` | Archivage GED |
| POST | `/ged/documents/{id}/favorite` | Toggle favori |
| GET | `/ged/favorites` | Mes favoris |
| GET | `/ged/recent` | Récemment consultés (filtrés ACL) |
| GET | `/ged/documents/{id}/export` | Export ZIP enrichi |
| POST | `/ged/documents/{id}/reindex` | Relancer extraction / index |
| POST/DELETE | `/ged/documents/{id}/legal-hold` | Gel / levée de gel |
| CRUD | `/ged/classification-nodes` | Plan de classement |
| GET/POST | `/ged/classification-rules` | Règles de classement auto |
| GET/POST | `/ged/retention-rules` | Règles de conservation |
| CRUD | `/ged/categories` | Catégories |
| GET | `/ged/tags` | Autocomplétion tags |

Téléchargements / ONLYOFFICE : réutilisent les routes parapheur signées existantes.

## Permissions Spatie

`ged.view`, `ged.search`, `ged.create`, `ged.update`, `ged.upload`, `ged.download`, `ged.comment`, `ged.create_version`, `ged.classify`, `ged.archive`, `ged.restore`, `ged.share`, `ged.manage_metadata`, `ged.manage_classification`, `ged.manage_types`, `ged.manage_categories`, `ged.view_audit`, `ged.manage_retention`.

Contrôle fin : `DocumentPolicy` → `DocumentAccessService` (circuit + confidentialité + ACL).

## UI

Menu **GED** : tableau de bord, catalogue, mes documents, à traiter, **favoris**, **récents**, plan de classement, recherche, archives, administration.  
Fiche `/ged/:id` : favoris, export ZIP (téléchargement authentifié), gel, onglets Synthèse / Fichiers / Versions / Métadonnées / Liés / Commentaires / Workflow / Historique.  
Admin GED : catégories, **règles de conservation**, **règles de classement automatique**.

## Indexation (P1)

- Job `IndexDocumentContentJob` (queue `database`, compatible cPanel)
- Extraction native : TXT, DOCX, XLSX, PPTX, PDF textuel rudimentaire
- Table `document_index_contents` + FULLTEXT MySQL ; LIKE sur SQLite (tests)
- Recherche `q` : métadonnées + tags + contenu indexé

```bash
php artisan queue:work --queue=default
# ou schedule cPanel : * * * * * php artisan schedule:run
```

## Conservation & gel

- Règles `document_retention_rules` (durée, sort final — **pas de destruction auto**)
- Legal hold : `legal_hold_at` + `archive_status=gele` → bloque suppression / nouvelles versions
- Antivirus : statut `pending|safe|infected|failed` (moteur externe P2) ; `infected` bloque téléchargement, stream et export ZIP
- Migration de réparation `2026_09_12_190000_repair_ged_p0_schema` si rollback partiel MySQL

## Classement automatique

Après validation parapheur : `DocumentAutoClassificationService` applique la première règle active (type + structure → nœud).  
Conservation : `DocumentRetentionService::applyMatchingRule`.

## Migration des données

```bash
php artisan migrate
php artisan db:seed
php artisan ged:migrate-metadata
```

## Phases

- **P0** : socle GED, ACL, classement, tags, recherche structurée, UI
- **P1** : FULLTEXT, favoris, récents, gel, retention, auto-classement, exports, indicateurs
- **P2** : OpenSearch, OCR, antivirus réel, facettes
- **P3** : embeddings / RAG (permissions Laravel avant tout accès modèle)
