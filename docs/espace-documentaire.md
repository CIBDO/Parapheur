# Espace documentaire personnel et collaboratif

Module complémentaire à la GED institutionnelle. Réutilise le socle `documents` / `document_versions` (pas de second système de fichiers).

## Concepts

| Niveau | Rôle |
|--------|------|
| Mon espace (`origin=personal`) | Travail quotidien de l’agent |
| Espaces collaboratifs (`origin=workspace`) | Projets / équipes |
| GED (`origin=ged`) | Patrimoine institutionnel |
| Bibliothèque | Fiches bibliographiques (± PDF lié), perso ou institutionnelle |

## Quotas

- Politique globale : `workspace_storage_policies` (admin `workspace.manage_quotas`)
- Overrides : `workspace_quota_overrides` (user ou workspace)
- Enforcement à l’upload / nouvelle version si `block_on_exceed`
- Jauge : `GET /api/workspace/{id}/storage`

## API principale

- `GET /api/workspace/home` — bootstrap espace perso + stockage + récents/favoris
- `GET/POST /api/workspace/{id}/folders|documents`
- `GET /api/workspace/{id}/browse`
- `GET /api/workspace/favorites` · `POST /api/workspace/favorites/toggle`
- `GET /api/workspace/recent` — consultés / modifiés
- `GET /api/workspace/{id}/members` · activity
- `GET /api/search?q=` — recherche unifiée (espace, partagés, GED, références)
- Admin : `/api/workspace/admin/storage-policy`, `quota-overrides`
- Bibliothèque : `/api/library/references`, `propose`, `moderate`, `note`

## Sécurité

Les documents `personal` / `workspace` ne bénéficient **jamais** de la clearance structure GED. Accès = auteur, membre workspace, ou partage explicite.

## Ponts métier

| Action | Endpoint |
|--------|----------|
| Envoyer GED | `POST /api/workspace/{ws}/documents/{doc}/submit-ged` |
| Soumettre parapheur (version figée) | `POST .../submit-parapheur` |
| Copie de travail | `POST .../working-copy` |
| Lier réunion / RDV / instruction | `POST .../attach-meeting\|appointment\|instruction` |
| Partager | `POST /api/workspace/{ws}/shares` |

Soumission parapheur : la version courante est marquée `is_official` (figée) ; les versions ultérieures restent distinctes.

## Bibliothèque institutionnelle

1. Création perso → `publication_status=personal`
2. `POST .../propose` → `proposed`
3. Modérateur (`library.moderate`) : `POST .../moderate` `{ approve: true|false }`
4. Notes personnelles : `POST .../note`

## UI Vue

Pages sous `/espace/*` : accueil, dossiers, documents, fiche document (PDF / OnlyOffice), partages, collaboratifs (+ membres / activité), favoris, récents, corbeille, bibliothèque, recherche, admin quotas.

## Hors scope (volontaire)

IA / RAG, OCR avancé, OpenSearch obligatoire, quotas multi-structures avancés.
