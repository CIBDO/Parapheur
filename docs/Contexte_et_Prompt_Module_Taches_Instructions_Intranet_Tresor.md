# Contexte et Prompt Cursor --- Module transversal Tâches & Instructions

## Intranet du Trésor / Bureau Numérique DGTCP

## 1. Contexte général

La **Direction Générale du Trésor et de la Comptabilité Publique
(DGTCP)** met en place un **Intranet institutionnel du Trésor**, destiné
à devenir l'environnement numérique de travail quotidien des agents. Le
site web `tresor.ml` conserve sa vocation de communication
institutionnelle publique ; l'Intranet est consacré au travail interne,
à la collaboration, aux services aux agents, aux documents, aux circuits
administratifs et au pilotage.

L'écosystème comprend ou prévoit : Accueil / Mon Travail, Tâches &
Instructions, e-Parapheur, Courrier, GED, Centre de Services /
Ticketing, Réunions, Agenda / Audiences / Rendez-vous, Dossiers /
Affaires, espaces documentaires, bibliothèque de références, annuaire
institutionnel, portail des applications, notifications, recherche
globale, ONLYOFFICE et assistant IA futur.

Le module **Tâches & Instructions** doit être un composant transversal
central. Il ne doit pas être conçu comme une simple todo-list et ne doit
pas être dupliqué dans Courrier, Ticketing, Réunions ou les autres
modules.

``` text
                         TASK SERVICE
                              │
       ┌──────────────────────┼──────────────────────┐
       │                      │                      │
    Courrier               Ticketing             Réunions
       │                      │                      │
       ├──────────────────────┼──────────────────────┤
       │                      │                      │
   Parapheur             Dossiers/Affaires       Agenda
       │                      │                      │
       └──────────────────────┼──────────────────────┘
                              │
                      TÂCHES & INSTRUCTIONS
                              │
                         MON TRAVAIL
                              │
                       ACCUEIL INTRANET
```

L'Accueil Intranet agrège les actions ; il n'est pas propriétaire des
tâches.

## 2. Distinction métier obligatoire

### Ticket

Un utilisateur signale un incident ou demande un service. Exemple : «
SIGRAC est inaccessible ».

### Tâche

Un travail précis doit être réalisé. Exemple : « Vérifier la
configuration du serveur SIGRAC ».

### Instruction

Une autorité ou un responsable donne une directive à exécuter. Exemple :
« Préparer une situation consolidée des régies avant vendredi ».

Une instruction peut générer plusieurs tâches :

``` text
INSTRUCTION DG
"Préparer la situation consolidée des régies"
        │
        ├── Tâche 1 → DSI : Extraire les données
        ├── Tâche 2 → Direction X : Vérifier les données
        └── Tâche 3 → Agent Y : Préparer la synthèse
```

## 3. Objectif

Le système doit permettre de répondre à : qui doit faire quoi, qui a
donné l'instruction, à qui la tâche a été imputée, quand, avec quelle
priorité et quelle échéance, si elle a été prise en charge, son
avancement, son retard éventuel, le travail réalisé, les preuves
fournies, la validation ou le retour pour correction, sa source métier
et son historique complet.

## 4. Stack et principes

Respecter l'existant : Laravel 12, Vue.js, Vuexy/Vuetify selon
l'existant, MySQL/MariaDB, Sanctum, Spatie Permission, Policies,
stockage privé, notifications Laravel, jobs/queues, scheduler, API REST,
GED, ONLYOFFICE, workflow/Parapheur et audit transversal.

## 5. Mission de Cursor

Concevoir et implémenter un module professionnel **Tâches &
Instructions**, simple pour l'utilisateur mais robuste pour gérer
hiérarchie, imputation, contributeurs, sous-tâches, dépendances,
échéances, rappels, délégations, réaffectations, preuves, validation,
corrections, historique, reporting et intégrations.

## 6. Audit obligatoire avant codage

Avant toute migration, auditer migrations, modèles, relations,
controllers, services, Form Requests, Resources, Policies, permissions,
workflows, notifications, events/listeners, jobs, scheduler, audit,
documents, versions, GED, Courrier, Ticketing, Réunions, Agenda,
Parapheur, utilisateurs, structures, délégations et Dossiers/Affaires.
Auditer aussi routes, menus, stores, composables, services API,
composants, formulaires, tableaux, Kanban éventuel, dashboards,
calendrier et notifications frontend.

### Premier livrable obligatoire

Avant de coder, produire : inventaire réutilisable, éléments à faire
évoluer, manques, risques de duplication, modèle de données, diagramme
fonctionnel, permissions, intégrations, workflow, plan par phases,
risques techniques et recommandations.

## 7. Types

Distinguer au minimum `TASK` et `INSTRUCTION`. Ne pas multiplier
inutilement les types.

## 8. Création d'une tâche

Prévoir : référence, objet, description, responsable principal,
contributeurs, structure, priorité, début, échéance, progression
éventuelle, pièces, tags, source, dossier/affaire et confidentialité
éventuelle.

Principe obligatoire :

``` text
1 tâche = 1 responsable principal + 0..N contributeurs
```

## 9. Création rapide

Depuis l'Accueil, un bouton `+ Nouveau` peut proposer : Nouvelle tâche,
Nouveau ticket, Nouveau courrier, Nouvelle réunion, Nouveau document,
selon permissions. Le formulaire rapide de tâche affiche d'abord Objet,
Responsable, Priorité, Échéance et Description ; les options avancées
restent secondaires.

## 10. Instructions

Une instruction conserve auteur, autorité émettrice, destinataire(s),
objet, contenu, priorité, date, échéance, source, confidentialité,
documents, statut et tâches d'exécution.

## 11. Sources

Une tâche peut provenir de : `MANUAL`, `COURRIER`, `TICKET`, `MEETING`,
`DECISION`, `PARAPHEUR`, `DOSSIER`, `AFFAIRE`, `APPOINTMENT`,
`INSTRUCTION`, `OTHER`. Étudier une relation polymorphique ou le
mécanisme transversal déjà utilisé.

## 12. Intégration Courrier

``` text
Courrier ARR/2026/000458
→ Action "Préparer réponse"
→ Créer tâche
→ Agent / Direction
→ Échéance
```

Le lien doit être bidirectionnel sans dupliquer le courrier ni ses
documents.

## 13. Intégration Ticketing

Un ticket peut générer une tâche technique, par exemple
`TCK-2026-001258 → Vérifier serveur SIGRAC`. Ticket et tâche gardent
leurs workflows et statuts propres.

## 14. Intégration Réunions

Une décision peut générer une tâche avec responsable et échéance. La
clôture de la réunion ne ferme pas automatiquement une tâche encore
ouverte.

## 15. Intégration Agenda / RDV

Une suite à donner peut créer une tâche :
`RDV DG → Préparer projet de réponse → Tâche`.

## 16. Intégration Dossiers / Affaires

Les tâches apparaissent dans la vue 360° de l'affaire avec courriers,
documents, réunions et instructions.

## 17. Imputation

Une tâche peut être imputée directement à un agent ou, si le métier le
permet, à une structure avant affectation interne. Historiser chaque
changement.

## 18. Contributeurs

Les contributeurs peuvent, selon droits, consulter, commenter, joindre
des éléments et exécuter leurs actions. Ils ne deviennent pas
responsables principaux par défaut.

## 19. Sous-tâches

Permettre de décomposer :

``` text
Préparer rapport annuel DSI
├── Collecter statistiques → Agent A
├── Préparer graphiques → Agent B
└── Rédiger synthèse → Agent C
```

Le responsable principal reste responsable de la consolidation. Limiter
raisonnablement la profondeur.

## 20. Dépendances

Prévoir `BLOCKS`, `BLOCKED_BY`, `DEPENDS_ON`, `RELATED_TO`.

## 21. Statuts

Prévoir initialement :

`BROUILLON`, `IMPUTEE`, `PRISE_EN_CHARGE`, `EN_COURS`, `EN_ATTENTE`,
`TERMINEE`, `A_VALIDER`, `VALIDEE`, `RETOURNEE`, `ANNULEE`.

Les transitions sont contrôlées côté backend.

## 22. Cycle nominal

``` text
CRÉATION → IMPUTATION → PRISE EN CHARGE → EN COURS
→ TERMINÉE → À VALIDER → VALIDÉE
```

Retour :

``` text
À VALIDER → RETOURNÉE → EN COURS
```

## 23. Brouillon

Une tâche en brouillon n'est pas visible par son futur responsable et ne
déclenche pas de notification d'imputation.

## 24. Prise en charge

L'agent clique `Prendre en charge`. Enregistrer utilisateur, date/heure
et commentaire éventuel. Distinguer `IMPUTEE`, `PRISE_EN_CHARGE` et
`EN_COURS`.

## 25. Priorités

Prévoir `NORMALE`, `IMPORTANTE`, `URGENTE`, `TRES_URGENTE`, idéalement
configurables.

## 26. Échéances et retard

Gérer début, échéance et heure éventuelle. Calculer `À VENIR`,
`AUJOURD’HUI`, `BIENTÔT ÉCHUE`, `EN RETARD`. Le retard doit de
préférence être calculé plutôt qu'un statut métier séparé.

## 27. Rappels et escalades

Prévoir des rappels configurables J-3, J-1, Jour J, J+1 et des escalades
selon priorité, retard et règles hiérarchiques. Ne pas hardcoder les
délais.

## 28. Progression

Autoriser éventuellement 0--100 %, sans obliger l'utilisateur si le
workflow par statuts suffit.

## 29. Commentaires et mentions

Fil de discussion avec auteur, date, texte, pièces et éventuellement
`@mentions`. Les règles de visibilité doivent respecter la
confidentialité de la tâche.

## 30. Pièces jointes

Stockage privé, validation MIME/taille/extensions, téléchargement
contrôlé et préparation à l'antivirus.

## 31. Preuve de réalisation

Lorsqu'une tâche est terminée, permettre ou exiger selon configuration :
compte rendu de réalisation, résultat, documents et preuves.

## 32. Validation

Le commanditaire/responsable habilité peut `VALIDER` ou
`RETOURNER POUR CORRECTION`.

Un retour enregistre obligatoirement auteur, date, motif, commentaire et
nouvelle échéance éventuelle. Conserver tous les cycles de retour.

## 33. Réaffectation et délégation

Distinguer : - **Réaffectation** : changement du responsable. -
**Délégation** : X agit pour le compte de Y selon une délégation
autorisée.

Historiser ancien/nouveau responsable, auteur, date et motif. Afficher
`Action réalisée par X par délégation de Y` lorsque pertinent.

## 34. Annulation

Ne jamais supprimer physiquement une tâche ayant circulé. Utiliser
`ANNULEE` avec motif.

## 35. Confidentialité

Prévoir si nécessaire `NORMAL`, `RESTREINT`, `CONFIDENTIEL`. Appliquer
les droits côté backend.

## 36. Historique fonctionnel

Timeline complète : création, imputation, prise en charge, progression,
commentaire, document, changement d'échéance/priorité, réaffectation,
délégation, terminaison, retour et validation.

## 37. Audit

Séparer historique métier et audit de sécurité. Auditer les opérations
sensibles : création, modification, imputation, prise en charge,
réaffectation, délégation, priorité, échéance, téléchargements, pièces,
validation, retour et annulation.

## 38. Mon Travail

Créer une couche d'agrégation transversale, sans dupliquer les objets :

``` text
MON TRAVAIL
├── À faire
├── À traiter
├── À valider
├── À signer
├── En attente
├── En retard
├── Aujourd’hui
└── À venir
```

Agrégation : Tâches, Instructions, Courriers, Tickets, Documents à
valider, décisions/actions de réunions et autres objets nécessitant une
action.

## 39. Accueil Intranet

Afficher une synthèse :

``` text
MON TRAVAIL
À faire 8 | À valider 3 | En retard 2 | Aujourd’hui 4

MES TÂCHES
• Préparer rapport SIMF       Aujourd’hui
• Vérifier données SIGRAC     25/09
• Préparer note CODIR         26/09

AUTRES ACTIONS
• 2 courriers à traiter
• 1 ticket affecté
• 3 documents à valider
```

L'Accueil affiche, agrège et fournit des raccourcis ; le module Tâches
stocke, contrôle, historise et valide.

## 40. Vues métier

Prévoir : Mes tâches, Tâches imputées par moi, Tâches de mon équipe
selon droits, À valider, En retard, Instructions. Vue tableau
obligatoire ; Kanban et calendrier peuvent compléter sans contourner les
transitions métier.

## 41. Fiche tâche 360°

Afficher Synthèse, Description, Responsable, Contributeurs, Priorité,
Échéance, Progression, Source, Sous-tâches, Dépendances, Commentaires,
Documents, Preuves, Historique et Audit selon droits.

## 42. Numérotation

Exemples `TSK-2026-001258` et `INS-2026-000458`. Nomenclature
configurable et génération transactionnelle.

## 43. Notifications

Notifier : nouvelle tâche/instruction, réaffectation, échéance proche,
retard, commentaire, mention, sous-tâche terminée, tâche terminée,
validation demandée, retour et validation.

Réutiliser un `NotificationService` transversal :

``` text
Événement
→ NotificationService
   ├── Intranet
   ├── Email
   ├── WhatsApp
   └── autres canaux
```

Ne jamais appeler directement l'API WhatsApp depuis le module Tâches.

## 44. WhatsApp

Utiliser WhatsApp comme canal d'alerte : objet, priorité, échéance et
lien sécurisé vers l'Intranet. Ne pas envoyer de documents
confidentiels. Le lien exige l'authentification.

## 45. GED

Les pièces de travail restent dans la tâche. Un document institutionnel
final peut être explicitement `Versé à la GED`. Une tâche peut aussi
lier un document GED existant sans duplication.

## 46. ONLYOFFICE

Pour produire un document :

``` text
Tâche → Créer depuis modèle → DOCX → ONLYOFFICE
→ V1 → correction → V2 → validation → document final
```

Réutiliser le moteur documentaire et le versioning existants.

## 47. Parapheur

Si un livrable nécessite visa/validation :

``` text
Tâche → Document → Parapheur → Visa/Validation
→ Retour tâche → Finalisation
```

Ne pas créer un second moteur d'approbation.

## 48. Recherche

Recherche par référence, objet, description, responsable, contributeur,
structure, auteur, statut, priorité, source, échéance, période,
dossier/affaire et tags. Respecter les permissions avant restitution.

## 49. Dashboards

### Agent

À faire, aujourd'hui, en retard, en attente, à valider, terminées
récemment.

### Responsable

Tâches imputées, en cours, terminées à valider, retards, charge par
agent, tâches critiques, retours.

### Direction

Volume, taux de réalisation, taux de retard, délai moyen, répartition
par structure/priorité/source, instructions ouvertes/exécutées.

Les statistiques ne doivent pas réduire l'évaluation d'un agent à un
simple volume de tâches.

## 50. Permissions

Étudier et adapter :

``` text
task.view
task.create
task.update
task.assign
task.reassign
task.take_charge
task.comment
task.complete
task.validate
task.return
task.cancel
task.view_team
task.view_all
task.view_reports
task.manage
instruction.view
instruction.create
instruction.assign
instruction.update
instruction.close
instruction.cancel
task.audit.view
```

## 51. Policies

Ne pas se contenter de Spatie. Prendre en compte auteur, responsable,
contributeur, structure, hiérarchie, délégation, confidentialité,
source, statut et workflow.

## 52. Hiérarchie

Ne pas hardcoder
`DG > Directeur > Chef Division > Chef Section > Agent`. Réutiliser le
référentiel organisationnel. Les instructions hiérarchiques respectent
les règles organisationnelles ; les tâches personnelles/collaboratives
peuvent suivre d'autres règles selon permissions.

## 53. Tâches personnelles

Prévoir éventuellement une tâche personnelle privée. Ne jamais la
confondre avec une instruction officielle.

## 54. Modèle de données à étudier après audit

Évaluer sans créer automatiquement :

``` text
tasks
task_types
task_statuses
task_priorities
task_assignments
task_contributors
task_comments
task_attachments
task_histories
task_relations
task_dependencies
task_worklogs
task_completions
task_validations
instructions
instruction_recipients
instruction_tasks
task_reminders
```

Réutiliser les tables transversales existantes.

## 55. Services métier

Éviter la logique dans les controllers. Étudier :

``` text
TaskService
TaskAssignmentService
TaskWorkflowService
TaskReminderService
TaskValidationService
InstructionService
WorkAggregationService
```

## 56. Events / Jobs / Scheduler

Événements possibles : `TaskCreated`, `TaskAssigned`,
`TaskTakenInCharge`, `TaskStarted`, `TaskCompleted`, `TaskReturned`,
`TaskValidated`, `TaskReassigned`, `TaskOverdue`, `InstructionCreated`,
`InstructionAssigned`, `InstructionCompleted`.

Utiliser jobs pour notifications, rappels, indexation, WhatsApp/email et
rapports. Scheduler pour échéances, retards, escalades et rappels.
Rester compatible cPanel.

## 57. API conceptuelle

``` text
/api/tasks
/api/tasks/{id}
/api/tasks/{id}/assign
/api/tasks/{id}/take-charge
/api/tasks/{id}/start
/api/tasks/{id}/comments
/api/tasks/{id}/contributors
/api/tasks/{id}/subtasks
/api/tasks/{id}/complete
/api/tasks/{id}/validate
/api/tasks/{id}/return
/api/tasks/{id}/reassign
/api/tasks/{id}/cancel
/api/tasks/{id}/history
/api/instructions
/api/instructions/{id}
/api/instructions/{id}/tasks
/api/my-work
```

Respecter les conventions existantes.

## 58. Menu

``` text
Mon Travail
├── Vue d’ensemble
├── Mes tâches
├── Mes instructions
├── À valider
├── En retard
└── Calendrier

Tâches & Instructions
├── Toutes les tâches
├── Tâches imputées
├── Tâches de mon équipe
├── Instructions
└── Rapports
```

Adapter selon permissions et éviter la surcharge.

## 59. UX et mobile

La complexité doit rester derrière une expérience simple. Sur mobile :
Mon Travail, Mes tâches, Aujourd'hui, En retard, Notifications, Prendre
en charge, Commenter, Terminer, Valider.

## 60. Sécurité

Contrôles backend, stockage privé, validation upload, Policies, audit,
liens temporaires, protection IDOR, contrôle des documents liés et
confidentialité.

## 61. Cas d'usage de référence

### Tâche directe

`Chef Division → tâche "Préparer statistiques" → Agent → prise en charge → rapport → terminer → validation`.

### Retour correction

`Agent termine → Responsable retourne avec motif → Agent corrige → nouvelle preuve/version → Responsable valide`.

### Instruction DG

`DG → instruction → Direction → tâches/sous-tâches → consolidation → résultat → validation → instruction exécutée`.

### Courrier

`Courrier entrant → imputation "Préparer réponse" → tâche → projet réponse → ONLYOFFICE → Parapheur → courrier sortant → validation tâche`.

### Réunion

`Décision → tâche → responsable → échéance → réalisation → validation → décision exécutée`.

### Ticket

`Ticket SIGRAC → tâche technique "Analyser logs" → administrateur → diagnostic → résultat → retour Ticketing`.

### Dossier/Affaire

`Affaire Interconnexion DGTCP-Douanes → tâche "Préparer protocole" → document → ONLYOFFICE → Parapheur → GED`.

## 62. Tests

### Fonctionnels

Création, brouillon, imputation, prise en charge, progression,
commentaire, pièces, contributeurs, sous-tâches, dépendances, échéances,
retard, rappel, réaffectation, délégation, terminaison, preuve,
validation, retour, annulation, instructions, intégrations
Courrier/Ticket/Réunion/Dossier, GED, ONLYOFFICE, Parapheur,
notifications, Mon Travail et dashboards.

### Sécurité

Vérifier notamment qu'un agent non autorisé ne voit pas une tâche
confidentielle, qu'un contributeur ne valide pas sans droit, qu'une
tâche validée n'est pas modifiée illégalement, que les pièces sont
protégées et que les liens vers les objets sources respectent leurs
propres permissions.

### Non-régression

Authentification, utilisateurs, structures, Courrier, Ticketing, GED,
Parapheur, Réunions, Agenda, Dossiers/Affaires, Documents, Versions,
ONLYOFFICE, Notifications, Audit et Recherche.

## 63. MVP --- Phase 1

Tâches, Instructions, création, imputation, responsable, contributeurs,
priorité, échéance, prise en charge, statuts, commentaires, pièces,
sous-tâches simples, preuve, terminer, valider, retour correction,
réaffectation, rappels, retards, notifications, historique, audit, Mes
tâches, Tâches imputées, Mon Travail, dashboard et recherche.

## 64. Phase 2

Dépendances avancées, délégations, escalades, calendrier, Kanban,
intégrations complètes Courrier/Ticketing/Réunions/Agenda/Dossiers, GED,
ONLYOFFICE, Parapheur, WhatsApp et rapports avancés.

## 65. Phase 3 --- IA

Résumé de tâche, extraction d'actions, suggestion de sous-tâches,
suggestion d'échéances, résumé d'instruction, recherche sémantique,
analyse des retards et synthèse managériale.

Principe :

``` text
IA PROPOSE
→ UTILISATEUR CONTRÔLE
→ UTILISATEUR VALIDE
→ APPLICATION EXÉCUTE
```

L'IA ne valide pas officiellement une tâche, ne donne pas une
instruction au nom d'une autorité, ne réaffecte pas silencieusement une
tâche sensible et ne supprime pas de tâche.

## 66. Principe de non-duplication

``` text
UTILISATEUR → users existants
STRUCTURE → structures existantes
DOCUMENT → socle documentaire
VERSION → document_versions
GED → GED existante
ÉDITION OFFICE → ONLYOFFICE
APPROBATION → Workflow / Parapheur
NOTIFICATION → NotificationService
WHATSAPP → canal transversal WhatsApp
AUDIT → audit transversal
COURRIER / TICKET / RÉUNION / DOSSIER → relations, jamais copies
```

## 67. Livrables attendus

Fournir : rapport d'audit, architecture, diagrammes fonctionnel et de
données, migrations, modèles/relations, services métier, workflows,
controllers, Requests, Resources, Policies, permissions, routes,
events/listeners, jobs, notifications, pages et composants Vue, Mon
Travail, dashboards, recherche/filtres, intégrations inter-modules,
tests fonctionnels/sécurité/non-régression, résultats des tests,
documentation technique, guide utilisateur, guide administrateur,
limites et recommandations.

## 68. Résultat final attendu

Lorsqu'un agent ouvre `intranet.tresor.ml`, l'Intranet doit lui
permettre de répondre immédiatement à :

# « Qu'est-ce que j'ai à faire aujourd'hui ? »

Selon ses droits, **Mon Travail** agrège :

``` text
Tâches
+ Instructions
+ Courriers
+ Tickets
+ Documents à valider
+ Décisions / actions de réunions
+ Échéances
```

Le module **Tâches & Instructions** est le moteur transversal permettant
d'organiser, imputer, suivre, prouver et valider le travail qui ne
relève pas exclusivement du Courrier ou du Ticketing, tout en restant
intégré à l'ensemble de l'Intranet du Trésor.
