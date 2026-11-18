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

Permissions Phase 2+ (`gerer_magasins`, `proposer_elimination`, …) déclarées mais non exposées en UI avant livrables correspondants.

## Workflow versement

1. Agent crée brouillon (`archives.verser`)
2. Soumet → `soumis`
3. Archiviste passe en contrôle → `en_controle`
4. Accepte ou rejette (motif obligatoire si rejet)
5. Prise en charge → création / finalisation des `archive_records`
6. Conservé → document source `archive_status = verse`
