# Permissions et workflows SAE

## Permissions `archives.*`

| Permission | Usage |
|------------|--------|
| `archives.consulter` | Lecture dashboard / records |
| `archives.rechercher` | Recherche |
| `archives.verser` | Créer / soumettre versement |
| `archives.valider_versement` | Contrôle / acceptation |
| `archives.rejeter_versement` | Rejet |
| `archives.classer` | Classement records / plan |
| `archives.telecharger` | Téléchargement pièce liée |
| `archives.auditer` | Journaux |
| `archives.administrer` | Admin plan / fonds |

Permissions Phase 2+ mappées CASL / menu :

| Permission | Subject CASL |
|------------|--------------|
| `archives.gerer_magasins` / `gerer_emplacements` | `ArchivesLocations` |
| `archives.gerer_boites` | `ArchivesBoxes` |
| `archives.gerer_prets` | `ArchivesLoans` |
| `archives.gerer_recolement` | `ArchivesInventory` |
| `archives.gerer_conservation` | `ArchivesRetention` |
| `archives.proposer/autoriser/executer_elimination` | `ArchivesDisposition` |
| `archives.communiquer` | `ArchivesCommunications` |
| `archives.exporter` | `export` sur `Archives` |

## Workflow versement

1. Agent crée brouillon (`archives.verser`)
2. Soumet → `soumis`
3. Archiviste passe en contrôle → `en_controle`
4. Accepte ou rejette (motif obligatoire si rejet)
5. Prise en charge → création / finalisation des `archive_records`
6. Conservé → document source `archive_status = verse`
