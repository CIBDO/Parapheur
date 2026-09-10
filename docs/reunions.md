# Module Réunions — e-Parapheur DGTCP

## Architecture

Le module **Gestion électronique des réunions et suivi des décisions** s’intègre aux moteurs existants :

- **GED** : documents, versions, pièces jointes, stockage privé `storage/app/private`
- **Workflow parapheur** : convocations et comptes rendus deviennent des `Document` (types `CONVOCATION`, `CR`, `DOSSIER_REUNION`) soumis au circuit visa/validation existant
- **Instructions** : chaque décision peut générer une `Instruction` (pas de second moteur de tâches)
- **Notifications** Laravel (`database` + `mail`)
- **Audit** polymorphe `audit_logs`
- **Confidentialité / priorité** : enums déjà utilisés par les documents

ONLYOFFICE n’est pas requis. Les documents compatibles s’ouvrent via la fiche parapheur existante.

## Modèle de données (tables ajoutées ou étendues)

Réutilisées : `meetings`, `meeting_participants`, `meeting_documents`, `meeting_decisions`, `instructions`, `documents`.

Ajoutées : `meeting_types`, `meeting_recurrences`, `meeting_agenda_items`, `meeting_notes`, `meeting_recommendations`, `meeting_minutes`, `meeting_templates`, `meeting_reminder_logs`.

Les **actions** ne dupliquent pas `instructions`. Les **présences** sont portées par `meeting_participants`.

## Statuts réunion

`brouillon → en_preparation → convocation_a_valider → convoquee → confirmation_en_cours → prete → en_cours → terminee → cr_en_redaction → cr_en_validation → cr_valide → cloturee → archivee`

Également : `planifiee`, `suspendue`, `reportee`, `annulee`.

Les transitions sont contrôlées par `MeetingStateMachine`. Une réunion convoquée, commencée ou ayant produit un CR n’est plus supprimable (annulation / archivage).

## Permissions Spatie

Umbrella : `meetings.manage` (DG, DGA, Secrétariat, Directeur, Admin).

Consultation : `meetings.view` (agents, chefs, conseillers, lecteurs) — limitée par la confidentialité et la participation.

Granulaires : `meetings.create`, `meetings.start`, `meetings.take_official_notes`, `meetings.validate_minutes`, `meetings.view_reports`, etc.

Les **notes privées** restent visibles uniquement par leur auteur, y compris pour un utilisateur disposant de `meetings.manage`.

## API (extrait)

| Méthode | URL | Rôle |
|---------|-----|------|
| GET/POST | `/api/meetings` | Liste / création |
| GET/PUT | `/api/meetings/{id}` | Fiche |
| POST | `/api/meetings/{id}/send-invitations` | Diffusion (throttle 10/min) |
| POST | `/api/meetings/{id}/confirm` | Confirmation de présence |
| POST | `/api/meetings/{id}/transition` | Changement de statut |
| POST | `/api/meetings/{id}/decisions` | Décision (+ instruction optionnelle) |
| POST | `/api/meetings/{id}/minutes` | Projet de CR |
| GET | `/api/meetings/dashboard` | Indicateurs module |
| GET | `/api/meetings/calendar` | Calendrier interne |
| GET | `/api/meetings/decisions` | Suivi transversal |

## Notifications et cron cPanel

Commande : `php artisan parapheur:remind-meetings` (horaire, `withoutOverlapping`).

Paramètres : `config/meetings.php` (J-3 / J-1 / H-1 réunions, J-7 / J-3 / J-1 / retard décisions). Les doublons sont bloqués par `meeting_reminder_logs`.

À déclarer dans le cron cPanel avec le scheduler Laravel déjà utilisé :

```text
* * * * * php /chemin/vers/artisan schedule:run
```

Les files d’attente restent `sync` par défaut (pas de Redis/Supervisor imposés).

## Front

- `/parapheur/reunions` — tableau de bord
- `/parapheur/reunions/nouvelle` — création (récurrence : intervalle, jour, fin, nb occurrences)
- `/parapheur/reunions/:id` — fiche à onglets (exports HTML, aperçu/impression CR, liens parapheur, soumission convocation)
- `/parapheur/reunions/seance/:id` — mode séance (tablette)
- `/parapheur/reunions/calendrier`
- `/parapheur/reunions/decisions`
- `/parapheur/meeting-types` — admin types de réunion
- `/parapheur/meeting-templates` — admin modèles HTML (placeholders `{{reference}}`, `{{agenda}}`…)

Le bureau DG affiche aujourd’hui / semaine / décisions ouvertes / retards.

## Récurrence

- Avec **ends_on** ou **occurrences_limit** (2–26) : la série est pré-générée à la création.
- Sans borne : la prochaine occurrence est créée à la transition `terminee` ou `cloturee`.
- `weekday` (1=lundi … 7=dimanche) aligne les occurrences hebdomadaires.

## Exports / impression

- `GET /api/meetings/{id}/export/{convocation|agenda|attendance|decisions}` → HTML institutionnel
- `GET /api/meetings/{id}/minutes/{minute}/preview` → aperçu CR
- Pas de DomPDF : même approche que le reporting (HTML imprimable / téléchargeable)

## Déploiement

1. `php artisan migrate`
2. `php artisan db:seed` (ou `MeetingSeeder` seul si le socle existe déjà)
3. `npm run build`
4. Vérifier le cron `schedule:run`

Aucune dépendance Composer supplémentaire (PDF institutionnel = HTML imprimable / téléchargeable, comme le reporting existant).
