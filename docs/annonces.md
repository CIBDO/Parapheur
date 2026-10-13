# Annonces et communications internes

Module de diffusion interne de la DGTCP. Il ne remplace ni le courrier, ni le parapheur, ni la GED.

## Mise en service

```bash
php artisan migrate
php artisan db:seed --class=AnnouncementPermissionSeeder
php artisan db:seed --class=AnnouncementCatalogSeeder
```

Les agents doivent se reconnecter pour recevoir les nouveaux droits dans le menu.

Les dates saisies sont interprétées en heure de Bamako, puis stockées en UTC.

## Droits

- Tout agent authentifié consulte les annonces dont il est destinataire.
- Un chef de section rédige et soumet.
- Un directeur valide, publie et consulte les accusés de sa structure.
- Le secrétariat DG, la DGA et le DG gèrent le circuit institutionnel et les catégories.
- Le droit d'administration technique n'ouvre pas les annonces restreintes ou confidentielles.

## Circuits

- Direct : le rédacteur habilité publie ou programme.
- Structure : rédacteur, puis responsable de la structure, puis publication.
- Institutionnel : rédacteur, puis autorité de communication, puis autorité de publication.

Une modification du titre, du texte ou de l'audience après publication est historisée. Si la catégorie l'exige, l'annonce repart en validation.

## Destinataires

L'audience est calculée à la publication et figée. Un changement d'affectation ultérieur ne retire pas l'annonce à l'agent déjà destinataire, et ne l'ajoute pas à un nouvel arrivant.

Cibles disponibles : tous les agents actifs, une structure (avec ou sans structures rattachées), une fonction, une liste d'agents, plus des exclusions.

## Bandeau

Le bandeau s'affiche sous le menu, sur toutes les pages authentifiées, uniquement s'il existe une annonce publiée, non expirée et destinée à l'agent. Le défilement s'arrête au survol, au focus et lorsque le système demande de réduire les animations. Une annonce à lecture obligatoire ne peut pas être masquée avant confirmation.

## Tâches planifiées

- `parapheur:publish-announcements` chaque minute
- `parapheur:expire-announcements` toutes les cinq minutes
- `parapheur:remind-announcements` chaque jour à 08:30

Les envois de notification sont dédoublonnés. Le planificateur Laravel doit être actif en production.
