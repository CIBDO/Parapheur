# Module Agenda, Audiences et Rendez-vous du Directeur Général

## Vision

Module parallèle au **module Réunions**, intégré au bureau numérique DGTCP. Il ne duplique pas les réunions : le calendrier unifié agrège rendez-vous, réunions et indisponibilités.

## Audit (synthèse)

| Élément | Statut |
|---|---|
| Réunions / calendrier réunions | EXISTANT — réutilisé via agrégation |
| Confidentialité / priorité | EXISTANT — enums documents |
| Instructions / notifications / audit | EXISTANT — réutilisés |
| Appointment / audiences DG / conflits | ABSENT → CRÉÉ |
| Policies Laravel | ABSENT globalement → Policy Appointment + AccessService |

## Modèle métier

### Tables

- `appointment_types` — référentiel administrable
- `appointments` — fiche RDV / audience
- `appointment_participants`
- `appointment_documents` — lien GED (pas de re-téléversement obligatoire)
- `appointment_notes` — privées / institutionnelles
- `appointment_followups` — suites (instruction, nouveau RDV, réunion…)
- `calendar_unavailabilities`
- `appointment_reminder_logs`

### Statuts (contrôlés par `AppointmentStateMachine`)

`brouillon` → `demande_recue` → `a_examiner` → `en_attente` / `creneau_*` → `a_valider` → `valide` → `confirme` → `pret` → `en_cours` → `termine` / `suite_a_donner` → `cloture` → `archive`

Également : `reporte`, `refuse`, `annule`.

### Conflits

`CalendarConflictService` détecte les chevauchements avec :

1. autres rendez-vous bloquants du Directeur ;
2. réunions où le Directeur est président ;
3. indisponibilités bloquantes.

Ne modifie jamais automatiquement les événements existants. Propose créneaux avant/après.

### Confidentialité

Réutilise `DocumentConfidentiality`. Les utilisateurs non habilités voient « Indisponible / Créneau indisponible ». Consultations sensibles journalisées.

## API principale

```
GET/POST   /api/appointments
GET/PUT    /api/appointments/{id}
POST       /api/appointments/{id}/propose-slot|validate|reject|confirm|reschedule|cancel
POST       /api/appointments/{id}/start|finish|close|archive
POST       /api/appointments/{id}/participants|documents|notes|followups
POST       /api/appointments/{id}/convert-to-meeting
GET        /api/appointments/calendar|dashboard|types
POST       /api/appointments/check-conflicts
GET/POST   /api/appointments/unavailabilities
```

## Permissions

Préfixe `appointments.*` (view, create, update, manage_requests, validate, confirm, view_calendar, manage_unavailability, archive…).

Rôles DG et Secrétariat DG reçoivent le jeu complet de gestion.

## Scheduler (cPanel)

```
* * * * * php /home/USER/application/artisan schedule:run
```

Commande : `parapheur:remind-appointments` (J-1 / H-2 / H-30).

## Frontend

Menu **Agenda & RDV** :

- Tableau de bord
- Agenda (jour / semaine / mois unifié)
- Demandes (file secrétariat)
- À valider (UI DG simplifiée)
- Mes rendez-vous
- Indisponibilités
- Fiche RDV multi-onglets

## Intégrations

- **Réunions** : affichage calendrier + `convert-to-meeting` (lien `converted_meeting_id`)
- **Documents** : attachement par ID GED existant ou upload → draft GED
- **Instructions** : suite `kind=instruction`
- **Notifications** : `AppointmentNotification` (database + mail)
- **Audit** : `AuditLogger` morphique

## P0 / P1 — finition

- Bureau DG (`dg.vue`) : widget Agenda + liste du jour + KPI
- Fiche RDV : dialogs report / réorientation / refus / annulation / attente
- Documents : sélection GED existante + téléversement fichier
- Calendrier : filtres type, statut, confidentialité, mode, directeur, structure, sources, recherche

## P2 non inclus

Sync Outlook/Google, portail public, visioconférence native, IA de proposition de créneaux.
