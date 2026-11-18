# Modèle de données SAE (Phase 1)

## Tables

- `archive_fonds` — fonds (structure productrice optionnelle, code unique)
- `archive_series` — séries rattachées à un fonds
- `archive_classification_nodes` — plan versionné (parent_id, version, code, path)
- `archive_transfers` — bordereaux de versement (statut workflow)
- `archive_transfer_items` — pièces du bordereau
- `archive_records` — unité intellectuelle archivée (réf permanente, document_id)
- `archive_holds` — gels de conservation (socle pour Phase 3)

## Statuts versement

`brouillon` → `soumis` → `en_controle` → (`rejete` | `accepte`) → `pris_en_charge` → `conserve`

## Statuts record

`en_versement` | `conserve` | `gele` | `a_eliminer` | `elimine` | `restitue`
