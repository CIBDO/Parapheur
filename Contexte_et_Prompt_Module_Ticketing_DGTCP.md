# CONTEXTE ET PROMPT CURSOR --- MODULE COMPLET DE TICKETING / CENTRE DE SERVICES DGTCP

## 1. Contexte général

La **Direction Générale du Trésor et de la Comptabilité Publique
(DGTCP)** met en place une plateforme intégrée de travail numérique,
appelée provisoirement **Bureau Numérique DGTCP**.

La solution ne se limite plus à un e-Parapheur. Elle constitue
progressivement un environnement numérique institutionnel regroupant
notamment :

-   e-Parapheur et workflows de visa, validation et signature ;
-   Gestion Électronique des Documents (GED) ;
-   gestion complète du courrier entrant, sortant et interne ;
-   bordereaux physiques et fiches de circulation ;
-   moteur transversal de modèles documentaires ;
-   intégration ONLYOFFICE ;
-   versioning documentaire ;
-   réunions et suivi des décisions ;
-   agenda, audiences et rendez-vous ;
-   instructions, tâches et plans d'actions ;
-   dossiers / affaires ;
-   espaces documentaires personnels et collaboratifs ;
-   bibliothèque de références ;
-   recherche documentaire ;
-   notifications ;
-   audit et traçabilité ;
-   tableaux de bord ;
-   intégrations futures avec l'IA.

L'architecture technique actuelle/cible repose principalement sur :

-   **Laravel 12** pour le backend ;
-   **Vue.js** pour le frontend ;
-   **Vuexy/Vuetify** selon les composants déjà présents ;
-   **MySQL/MariaDB** ;
-   **Laravel Sanctum** ;
-   **Spatie Laravel Permission** ;
-   stockage privé Laravel ;
-   **ONLYOFFICE Docs** hébergé sur un serveur séparé ;
-   hébergement applicatif compatible **cPanel** ;
-   APIs REST ;
-   queues/jobs Laravel compatibles avec les contraintes d'hébergement ;
-   système transversal de notifications et d'audit.

La DGTCP souhaite maintenant intégrer un **module complet de Ticketing /
Centre de Services**, destiné en priorité à la gestion des demandes et
incidents informatiques, mais dont l'architecture doit permettre son
utilisation ultérieure par d'autres structures et services.

Le module ne doit donc pas être conçu comme un simple formulaire de
signalement d'incident informatique.

Il doit constituer un véritable **Centre de Services institutionnel**,
permettant de gérer :

-   incidents ;
-   demandes de service ;
-   assistance ;
-   problèmes ;
-   demandes d'accès et d'habilitation ;
-   demandes relatives aux équipements ;
-   anomalies applicatives ;
-   demandes d'évolution ;
-   demandes nécessitant approbation ;
-   escalades ;
-   SLA ;
-   dispatching ;
-   interventions ;
-   satisfaction ;
-   base de connaissances ;
-   rapports d'intervention ;
-   statistiques et pilotage.

Le module doit s'intégrer au reste du Bureau Numérique sans créer de
mécanismes parallèles.

Principe architectural :

``` text
                    BUREAU NUMÉRIQUE DGTCP
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
     COURRIER             e-PARAPHEUR              GED
        │                     │                     │
        └──────────────┬──────┴───────┬─────────────┘
                       │              │
                  TICKETING      DOSSIERS/AFFAIRES
                       │
          ┌────────────┼─────────────┐
          │            │             │
      INCIDENTS     DEMANDES      PROBLÈMES
          │            │             │
          └────────────┼─────────────┘
                       │
                  DISPATCHING
                       │
                 ÉQUIPES / AGENTS
                       │
                       SLA
                       │
                TÂCHES / ACTIONS
                       │
              BASE DE CONNAISSANCES
                       │
                 TABLEAUX DE BORD
```

Le Ticketing porte le **cycle métier de la demande et de l'incident**.

La GED porte les documents institutionnels.

Le moteur documentaire porte les fichiers, versions et modèles.

ONLYOFFICE porte l'édition collaborative des documents Office.

Le Parapheur porte les circuits d'approbation, visa, validation et
signature.

Le module Tâches/Instructions porte les actions transversales lorsqu'un
ticket génère une action métier.

------------------------------------------------------------------------

# PROMPT CURSOR --- MODULE COMPLET DE TICKETING / CENTRE DE SERVICES

## 2. Mission

Tu interviens sur une application existante Laravel 12 + Vue.js
constituant le **Bureau Numérique DGTCP**.

Ta mission est de concevoir et implémenter un module professionnel :

# CENTRE DE SERVICES / TICKETING DGTCP

Le résultat ne doit pas être un simple CRUD de tickets.

Il doit constituer un véritable système de gestion des demandes,
incidents, problèmes, interventions et niveaux de service, avec une
architecture évolutive vers un **ITSM léger institutionnel**.

------------------------------------------------------------------------

## 3. Principe fondamental

Ne pas créer une application isolée dans l'application.

Réutiliser autant que possible les briques transversales existantes :

-   utilisateurs ;
-   structures ;
-   rôles ;
-   permissions ;
-   documents ;
-   fichiers ;
-   versions ;
-   GED ;
-   workflows ;
-   Parapheur ;
-   tâches ;
-   instructions ;
-   notifications ;
-   audit ;
-   moteur de modèles ;
-   ONLYOFFICE ;
-   recherche ;
-   annuaire ;
-   applications métier ;
-   équipements si déjà présents.

------------------------------------------------------------------------

## 4. Audit obligatoire avant développement

Avant de créer une migration, auditer complètement l'existant.

### Backend

Analyser :

-   migrations ;
-   modèles Eloquent ;
-   relations ;
-   controllers ;
-   services ;
-   repositories éventuels ;
-   Form Requests ;
-   API Resources ;
-   Policies ;
-   permissions Spatie ;
-   notifications ;
-   events/listeners ;
-   jobs ;
-   scheduler ;
-   audit ;
-   documents ;
-   versions ;
-   workflows ;
-   tâches/instructions ;
-   structures ;
-   utilisateurs ;
-   applications ;
-   équipements éventuels ;
-   moteur de modèles ;
-   ONLYOFFICE.

### Frontend

Analyser :

-   routes ;
-   menus ;
-   pages ;
-   composants ;
-   stores ;
-   composables ;
-   services API ;
-   formulaires ;
-   DataTables ;
-   filtres ;
-   composants upload ;
-   notifications ;
-   dashboards ;
-   composants de workflow ;
-   composants documentaires.

------------------------------------------------------------------------

## 5. Premier livrable obligatoire avant codage

Produire d'abord :

1.  ce qui existe et peut être réutilisé ;
2.  ce qui existe mais doit évoluer ;
3.  ce qui manque ;
4.  les risques de duplication ;
5.  le modèle de données recommandé ;
6.  le diagramme fonctionnel ;
7.  les permissions proposées ;
8.  les intégrations avec les modules existants ;
9.  le plan d'implémentation par phases ;
10. les risques techniques et fonctionnels.

Ne commencer le développement qu'après cet audit.

------------------------------------------------------------------------

## 6. Objectifs fonctionnels

Le module doit permettre de gérer :

``` text
DEMANDE / INCIDENT
        ↓
ENREGISTREMENT
        ↓
CATÉGORISATION
        ↓
QUALIFICATION
        ↓
PRIORISATION
        ↓
AFFECTATION / DISPATCHING
        ↓
PRISE EN CHARGE
        ↓
DIAGNOSTIC
        ↓
TRAITEMENT
        ↓
ESCALADE éventuelle
        ↓
RÉSOLUTION
        ↓
VALIDATION DEMANDEUR
        ↓
CLÔTURE
        ↓
CAPITALISATION
```

------------------------------------------------------------------------

## 7. Typologie des tickets

Prévoir des types configurables.

Types initiaux possibles :

``` text
INCIDENT
DEMANDE_SERVICE
ASSISTANCE
ACCES_HABILITATION
MATERIEL
RESEAU
MESSAGERIE
APPLICATION
SECURITE
ANOMALIE
EVOLUTION
RECLAMATION
DEMANDE_ADMINISTRATIVE
AUTRE
```

Ne pas hardcoder les catégories métier.

------------------------------------------------------------------------

## 8. Numérotation

Chaque ticket reçoit un numéro unique.

Exemple :

``` text
TCK-2026-001258
```

La nomenclature doit être configurable.

La génération doit être transactionnelle et résistante aux accès
concurrents.

------------------------------------------------------------------------

## 9. Informations du ticket

Prévoir notamment :

-   numéro ;
-   titre/objet ;
-   description ;
-   demandeur ;
-   structure du demandeur ;
-   canal ;
-   type ;
-   catégorie ;
-   sous-catégorie ;
-   service demandé ;
-   application concernée ;
-   équipement concerné ;
-   localisation fonctionnelle éventuelle ;
-   impact ;
-   urgence ;
-   priorité ;
-   statut ;
-   équipe assignée ;
-   technicien assigné ;
-   date création ;
-   date prise en charge ;
-   échéance ;
-   date résolution ;
-   date clôture ;
-   SLA ;
-   pièces jointes ;
-   tags ;
-   source ;
-   ticket parent éventuel.

------------------------------------------------------------------------

## 10. Canaux de création

Prévoir :

``` text
PORTAIL
AGENT_SUPPORT
EMAIL
TELEPHONE
AUTRE
```

L'intégration Email → Ticket peut être réalisée en phase ultérieure.

------------------------------------------------------------------------

## 11. Catégories et sous-catégories

Créer un référentiel hiérarchique configurable.

Exemple :

``` text
Informatique
├── Applications
│   ├── SIGRAC
│   ├── SIMF
│   ├── Dougamassa
│   └── Autres
├── Réseau
├── Internet
├── Messagerie
├── Matériel
├── Impression
├── Comptes utilisateurs
├── Accès / habilitations
└── Sécurité
```

Ne pas coder ces valeurs directement dans le frontend.

------------------------------------------------------------------------

## 12. Catalogue de services

Créer un véritable catalogue de services.

Exemple :

``` text
CATALOGUE DES SERVICES

Informatique
├── Assistance applicative
├── Création de compte
├── Réinitialisation accès
├── Habilitation application
├── Assistance réseau
├── Assistance messagerie
├── Intervention matériel
├── Installation logiciel
├── Demande équipement
└── Demande d’évolution
```

Chaque service peut définir :

-   catégorie ;
-   formulaire ;
-   équipe responsable ;
-   SLA ;
-   workflow ;
-   approbations ;
-   priorité par défaut ;
-   champs obligatoires ;
-   documentation associée.

------------------------------------------------------------------------

## 13. Formulaires dynamiques

Le formulaire doit pouvoir varier selon le service.

Exemple Application :

``` text
Application concernée
Module
Message d’erreur
Depuis quand ?
Nombre d’utilisateurs touchés
Capture d’écran
```

Exemple Matériel :

``` text
Type équipement
N° inventaire
Nature de la panne
Localisation
Disponibilité de l’équipement
```

Prévoir une architecture de champs personnalisables sans transformer le
modèle principal en table incontrôlable.

------------------------------------------------------------------------

## 14. Impact

Référentiel configurable :

``` text
FAIBLE
MOYEN
ELEVE
CRITIQUE
```

Exemples d'évaluation :

-   un utilisateur ;
-   plusieurs utilisateurs ;
-   une structure ;
-   plusieurs structures ;
-   service institutionnel indisponible.

------------------------------------------------------------------------

## 15. Urgence

Référentiel configurable :

``` text
FAIBLE
MOYENNE
HAUTE
CRITIQUE
```

------------------------------------------------------------------------

## 16. Priorité

La priorité doit pouvoir être calculée automatiquement à partir de :

``` text
IMPACT × URGENCE
```

Exemple de matrice initiale :

``` text
                 URGENCE
              Faible Moyenne Haute Critique
IMPACT Faible     P4     P4     P3      P2
       Moyen      P4     P3     P2      P2
       Élevé      P3     P2     P2      P1
       Critique   P2     P2     P1      P1
```

La matrice doit être configurable par l'administrateur.

Une modification manuelle de priorité doit être autorisée uniquement aux
profils habilités et historisée avec motif.

------------------------------------------------------------------------

## 17. Niveaux de priorité

Prévoir initialement :

``` text
P1_CRITIQUE
P2_HAUTE
P3_NORMALE
P4_FAIBLE
```

------------------------------------------------------------------------

## 18. SLA

Mettre en place un moteur de SLA configurable.

Un SLA peut définir :

-   délai de prise en charge ;
-   délai de résolution ;
-   calendrier de service ;
-   heures ouvrées ;
-   jours ouvrés ;
-   exclusions ;
-   pauses ;
-   escalades ;
-   notifications.

Exemple :

``` text
P1
Prise en charge : 15 min
Résolution cible : 4 h

P2
Prise en charge : 1 h
Résolution cible : 8 h

P3
Prise en charge : 4 h
Résolution cible : 2 jours

P4
Prise en charge : 1 jour
Résolution cible : 5 jours
```

Ces valeurs ne doivent pas être hardcodées.

------------------------------------------------------------------------

## 19. Calendriers SLA

Prévoir des calendriers configurables :

-   jours ouvrés ;
-   heures ouvrées ;
-   jours fériés ;
-   périodes exceptionnelles ;
-   éventuellement service 24/7.

Le moteur doit calculer les échéances selon le calendrier applicable.

------------------------------------------------------------------------

## 20. Pause SLA

Le SLA peut être suspendu dans certains états configurés, par exemple :

``` text
EN_ATTENTE_DEMANDEUR
EN_ATTENTE_TIERS
```

Toute pause/reprise doit être historisée.

------------------------------------------------------------------------

## 21. Dispatching

Créer un système complet d'affectation.

``` text
Ticket
  ↓
Centre de services
  ↓
Équipe
  ↓
Technicien N1
  ↓
N2
  ↓
N3 / Expert
```

Permettre :

-   affectation manuelle ;
-   réaffectation ;
-   affectation automatique ;
-   transfert entre équipes ;
-   escalade ;
-   assignation à plusieurs contributeurs si nécessaire.

------------------------------------------------------------------------

## 22. Groupes / équipes support

Prévoir des équipes configurables :

``` text
Support utilisateurs
Applications
Infrastructure
Réseau
Sécurité
Base de données
Développement
Administration systèmes
```

Les noms réels doivent rester administrables.

------------------------------------------------------------------------

## 23. Affectation automatique

Préparer des règles :

``` text
catégorie
service
application
structure
priorité
localisation
compétence
```

→ équipe responsable.

Ne pas implémenter une logique opaque difficile à administrer.

------------------------------------------------------------------------

## 24. Statuts

Prévoir un workflow cohérent :

``` text
NOUVEAU
A_QUALIFIER
AFFECTE
PRIS_EN_CHARGE
EN_COURS
EN_ATTENTE_DEMANDEUR
EN_ATTENTE_TIERS
ESCALADE
RESOLU
A_VALIDER
REOUVERT
CLOTURE
ANNULE
```

Les transitions doivent être contrôlées.

------------------------------------------------------------------------

## 25. Résolu ≠ clôturé

Principe obligatoire :

``` text
Technicien
   ↓
RÉSOLUTION PROPOSÉE
   ↓
Demandeur
   ↓
CONFIRMATION
   ↓
CLÔTURE
```

Le demandeur peut :

``` text
Confirmer
ou
Réouvrir
```

Prévoir éventuellement une clôture automatique après X jours sans
réponse, configurable.

------------------------------------------------------------------------

## 26. Prise en charge

Enregistrer :

-   utilisateur ;
-   date/heure ;
-   équipe ;
-   SLA restant ;
-   commentaire éventuel.

Différencier :

``` text
AFFECTE
PRIS_EN_CHARGE
EN_COURS
```

------------------------------------------------------------------------

## 27. Commentaires

Prévoir deux catégories :

### Commentaire public

Visible par le demandeur.

### Note interne

Visible uniquement par les équipes support autorisées.

L'interface doit rendre cette distinction extrêmement claire pour éviter
les erreurs.

------------------------------------------------------------------------

## 28. Pièces jointes

Permettre :

-   images ;
-   captures ;
-   PDF ;
-   documents Office ;
-   logs ;
-   autres fichiers autorisés.

Utiliser le stockage privé.

Contrôler :

-   taille ;
-   extension ;
-   MIME ;
-   permission ;
-   téléchargement.

Préparer l'intégration antivirus.

------------------------------------------------------------------------

## 29. Historique

Chaque ticket doit posséder une timeline complète.

Exemple :

``` text
10:05 Ticket créé
10:07 Catégorisé Réseau
10:08 Priorité P2
10:10 Affecté Infrastructure
10:15 Pris en charge
10:34 Note interne
11:12 En attente demandeur
11:45 Réponse demandeur
12:20 Résolution proposée
13:05 Résolution confirmée
13:06 Ticket clôturé
```

------------------------------------------------------------------------

## 30. Journal d'audit

Ne pas confondre :

-   timeline fonctionnelle ;
-   audit de sécurité.

L'audit doit tracer les opérations sensibles.

------------------------------------------------------------------------

## 31. Escalade fonctionnelle

Permettre le transfert :

``` text
N1
 ↓
N2
 ↓
N3
```

avec :

-   motif ;
-   auteur ;
-   date ;
-   ancienne équipe ;
-   nouvelle équipe ;
-   ancien technicien ;
-   nouveau technicien.

------------------------------------------------------------------------

## 32. Escalade SLA

Prévoir des règles automatiques.

Exemple :

``` text
P1 non pris en charge à 80 % du SLA
        ↓
Chef d’équipe

SLA prise en charge dépassé
        ↓
Chef de Division

SLA résolution critique
        ↓
Directeur concerné
```

Les règles doivent être configurables.

------------------------------------------------------------------------

## 33. Relations entre tickets

Supporter :

``` text
PARENT_OF
CHILD_OF
DUPLICATE_OF
RELATED_TO
CAUSED_BY
BLOCKED_BY
```

------------------------------------------------------------------------

## 34. Tickets parents/enfants

Exemple :

``` text
INCIDENT MAJEUR
Internet DGTCP indisponible
        │
        ├── TCK-00128
        ├── TCK-00131
        ├── TCK-00134
        └── TCK-00142
```

Permettre de regrouper les tickets similaires.

------------------------------------------------------------------------

## 35. Détection de doublons

À la création, rechercher éventuellement des tickets ouverts similaires
selon :

-   service ;
-   application ;
-   catégorie ;
-   objet ;
-   mots-clés.

Ne jamais fusionner automatiquement sans validation humaine.

------------------------------------------------------------------------

## 36. Incident majeur

Créer un mécanisme spécifique permettant de :

-   déclarer un incident majeur ;
-   désigner un responsable ;
-   rattacher des tickets ;
-   suivre les communications ;
-   afficher l'impact ;
-   suivre les actions ;
-   enregistrer la résolution ;
-   produire un rapport d'incident.

------------------------------------------------------------------------

## 37. Gestion des problèmes

Distinguer :

``` text
INCIDENT
→ rétablir le service

PROBLÈME
→ identifier et supprimer la cause racine
```

Un ou plusieurs incidents peuvent être rattachés à un problème.

------------------------------------------------------------------------

## 38. Fiche problème

Prévoir :

-   référence ;
-   titre ;
-   description ;
-   incidents liés ;
-   impact ;
-   responsable ;
-   équipe ;
-   analyse ;
-   cause racine ;
-   solution temporaire ;
-   solution définitive ;
-   statut ;
-   actions ;
-   documents ;
-   date clôture.

------------------------------------------------------------------------

## 39. Erreurs connues

Prévoir une base de :

``` text
KNOWN ERROR / ERREUR CONNUE
```

avec :

-   problème ;
-   symptômes ;
-   cause ;
-   workaround ;
-   solution ;
-   applications/équipements concernés.

------------------------------------------------------------------------

## 40. Demandes d'évolution

Une demande peut devenir une demande d'évolution.

Exemple :

``` text
Ticket
 ↓
Demande d’évolution
 ↓
Analyse
 ↓
Estimation
 ↓
Validation
 ↓
Développement
 ↓
Test
 ↓
Déploiement
 ↓
Clôture
```

Ne pas transformer le Ticketing en outil complet de développement
logiciel ; créer les relations nécessaires avec les tâches/projets
existants.

------------------------------------------------------------------------

## 41. Approbations

Certaines demandes doivent nécessiter validation.

Exemple :

``` text
Agent
 ↓
Demande accès application
 ↓
Responsable hiérarchique
 ↓
Validation
 ↓
DSI
 ↓
Exécution
```

Réutiliser le moteur transversal de workflow/Parapheur.

Ne pas créer un deuxième moteur d'approbation.

------------------------------------------------------------------------

## 42. Tâches et actions

Un ticket peut générer une ou plusieurs tâches.

Exemple :

``` text
Ticket
├── Diagnostic
├── Intervention serveur
├── Test utilisateur
└── Documentation
```

Réutiliser le module transversal Tâches/Instructions si compatible.

------------------------------------------------------------------------

## 43. Interventions

Prévoir la possibilité d'enregistrer :

-   intervention à distance ;
-   intervention sur site ;
-   appel téléphonique ;
-   diagnostic ;
-   maintenance ;
-   remplacement équipement ;
-   configuration.

Pour chaque intervention :

``` text
technicien
date début
date fin
durée
type
description
résultat
pièces
```

------------------------------------------------------------------------

## 44. Temps passé

Permettre aux techniciens d'enregistrer le temps consacré.

Utiliser cette information pour :

-   statistiques ;
-   charge ;
-   coût éventuel ;
-   pilotage.

Ne pas en faire un mécanisme intrusif.

------------------------------------------------------------------------

## 45. Portail utilisateur

Créer une interface simple :

``` text
Centre de services
├── Nouveau ticket
├── Mes demandes
├── Mes tickets ouverts
├── En attente de ma réponse
├── Résolus
├── Historique
└── Base de connaissances
```

------------------------------------------------------------------------

## 46. Assistant à la création

Avant la création du ticket, proposer :

1.  choix du service ;
2.  catégorie ;
3.  formulaire adapté ;
4.  articles de connaissance potentiellement utiles ;
5.  tickets/incidents majeurs éventuellement déjà ouverts.

L'utilisateur doit toujours pouvoir poursuivre la création si le
problème persiste.

------------------------------------------------------------------------

## 47. Interface technicien

Prévoir une vue de travail présentant immédiatement :

``` text
TCK-2026-001258

Incident : SIGRAC inaccessible
Demandeur : ...
Structure : ...
Priorité : P1
SLA prise en charge : ...
SLA résolution : ...

Équipe : Applications
Assigné à : ...

Description
Pièces jointes
Commentaires
Notes internes
Historique
Interventions
Temps passé
Tickets liés
Problème lié
Équipement
Application
Documents
```

Actions :

``` text
Prendre en charge
Affecter
Réaffecter
Commenter
Ajouter note interne
Demander information
Mettre en attente
Escalader
Créer tâche
Lier problème
Résoudre
Annuler
```

------------------------------------------------------------------------

## 48. File de travail

Prévoir :

``` text
Non affectés
Mes tickets
Tickets de mon équipe
P1/P2
SLA proche
SLA dépassé
En attente
Réouverts
Incidents majeurs
```

------------------------------------------------------------------------

## 49. Vue Kanban

Prévoir éventuellement une vue :

``` text
NOUVEAU | AFFECTÉ | EN COURS | EN ATTENTE | RÉSOLU
```

sans remplacer la vue tableau.

------------------------------------------------------------------------

## 50. Base de connaissances

Créer/réutiliser une base permettant de capitaliser les solutions.

Exemple :

``` text
BASE DE CONNAISSANCES
├── Applications
│   ├── SIGRAC
│   ├── SIMF
│   └── Dougamassa
├── Réseau
├── Messagerie
├── Matériel
└── Sécurité
```

------------------------------------------------------------------------

## 51. Article de connaissance

Prévoir :

-   titre ;
-   résumé ;
-   contenu ;
-   catégorie ;
-   application ;
-   tags ;
-   visibilité ;
-   auteur ;
-   statut ;
-   version ;
-   pièces ;
-   date publication ;
-   date révision.

------------------------------------------------------------------------

## 52. Création depuis résolution

Depuis un ticket résolu :

``` text
[Créer un article de connaissance]
```

Préremplir :

-   problème ;
-   symptômes ;
-   diagnostic ;
-   résolution.

L'utilisateur autorisé corrige avant publication.

------------------------------------------------------------------------

## 53. Statuts article

Prévoir :

``` text
BROUILLON
EN_REVISION
PUBLIE
ARCHIVE
```

------------------------------------------------------------------------

## 54. Recherche base de connaissances

Recherche par :

-   titre ;
-   contenu ;
-   catégorie ;
-   application ;
-   mots-clés.

Réutiliser le moteur de recherche existant lorsque pertinent.

------------------------------------------------------------------------

## 55. Applications métier

Un ticket peut être associé à une application.

Exemples :

``` text
SIGRAC
SIMF
Dougamassa
NAV
e-Parapheur
```

Les applications doivent provenir d'un référentiel, pas être hardcodées.

------------------------------------------------------------------------

## 56. Parc / actifs / CMDB légère

Préparer une architecture permettant de rattacher un ticket à :

-   ordinateur ;
-   serveur ;
-   imprimante ;
-   équipement réseau ;
-   téléphone ;
-   application ;
-   licence ;
-   autre actif.

Ne pas obligatoirement développer une CMDB complète au MVP.

------------------------------------------------------------------------

## 57. Fiche équipement

Si un module d'actifs est implémenté ultérieurement, prévoir :

``` text
Référence
N° inventaire
Type
Marque
Modèle
N° série
Utilisateur
Structure
Localisation
Statut
Date acquisition
Garantie
Tickets associés
```

------------------------------------------------------------------------

## 58. Historique d'un actif

Depuis un équipement :

``` text
Ordinateur PC-00158

Tickets :
TCK-...
TCK-...
TCK-...
```

Cela permettra d'identifier les équipements problématiques.

------------------------------------------------------------------------

## 59. Intégration GED

Les pièces ordinaires du ticket restent attachées au ticket.

Pour un document ayant une valeur institutionnelle, proposer :

``` text
[Verser à la GED]
```

Exemples :

-   rapport d'incident ;
-   rapport d'intervention ;
-   procédure ;
-   PV ;
-   rapport d'analyse ;
-   document de sécurité.

Ne pas verser automatiquement toutes les captures d'écran dans la GED
institutionnelle.

------------------------------------------------------------------------

## 60. Rapports d'intervention

Permettre de générer un rapport depuis un modèle.

Workflow :

``` text
Ticket
 ↓
Générer rapport d’intervention
 ↓
Modèle DOCX
 ↓
Injection données
 ↓
ONLYOFFICE
 ↓
Correction
 ↓
Nouvelle version
 ↓
Validation
 ↓
PDF final
 ↓
Ticket / GED
```

------------------------------------------------------------------------

## 61. Moteur de modèles

Réutiliser le moteur transversal de modèles documentaires du Bureau
Numérique.

Types possibles :

``` text
Rapport d’intervention
Rapport d’incident
Fiche diagnostic
PV intervention
Rapport problème
Fiche de résolution
```

------------------------------------------------------------------------

## 62. Versioning ONLYOFFICE

Toute correction d'un document généré doit être versionnée.

Exemple :

``` text
Rapport intervention

V1 générée
V2 corrigée technicien
V3 corrigée responsable
FINAL validée
```

Ne jamais écraser silencieusement une version précédente.

------------------------------------------------------------------------

## 63. Parapheur

Lorsqu'un document ou une demande nécessite approbation :

``` text
Ticket
 ↓
Document / demande
 ↓
Workflow / Parapheur
 ↓
Visa / validation
 ↓
Retour Ticketing
 ↓
Exécution
```

------------------------------------------------------------------------

## 64. Notifications

Notifier notamment :

-   ticket créé ;
-   ticket affecté ;
-   prise en charge ;
-   commentaire public ;
-   demande d'information ;
-   réponse demandeur ;
-   changement important de statut ;
-   escalade ;
-   SLA proche ;
-   SLA dépassé ;
-   résolution ;
-   réouverture ;
-   clôture.

------------------------------------------------------------------------

## 65. Préférences de notification

Permettre éventuellement :

-   notification application ;
-   email ;
-   autres canaux futurs.

Ne pas multiplier les notifications inutiles.

------------------------------------------------------------------------

## 66. Satisfaction

À la résolution/clôture, proposer :

``` text
Votre demande a-t-elle été correctement traitée ?
★★★★★
Commentaire
```

Prévoir :

-   note ;
-   commentaire ;
-   date ;
-   ticket.

------------------------------------------------------------------------

## 67. Réouverture

Le demandeur peut réouvrir un ticket résolu selon les règles.

Historiser :

-   motif ;
-   date ;
-   utilisateur ;
-   compteur de réouvertures.

------------------------------------------------------------------------

## 68. Dashboard demandeur

Afficher :

``` text
Tickets ouverts
En cours
En attente de ma réponse
Résolus récemment
Demandes récentes
```

------------------------------------------------------------------------

## 69. Dashboard technicien

Afficher :

``` text
Mes tickets
À prendre en charge
P1/P2
SLA proche
SLA dépassé
En attente
Réouverts
Résolus aujourd’hui
```

------------------------------------------------------------------------

## 70. Dashboard responsable d'équipe

Afficher :

``` text
Tickets non affectés
Charge par technicien
P1/P2
SLA
Tickets en retard
Incidents majeurs
Réouvertures
Temps moyen
Satisfaction
```

------------------------------------------------------------------------

## 71. Dashboard DSI / management

Afficher :

``` text
Tickets ouverts
Nouveaux aujourd’hui
Incidents critiques
SLA respectés
SLA dépassés
Temps moyen prise en charge
Temps moyen résolution
Taux résolution
Taux réouverture
Satisfaction
Applications les plus concernées
Catégories principales
Structures demandeuses
```

------------------------------------------------------------------------

## 72. KPI

Calculer notamment :

-   volume de tickets ;
-   tickets par type ;
-   tickets par catégorie ;
-   tickets par structure ;
-   tickets par application ;
-   tickets par équipe ;
-   tickets par technicien ;
-   P1/P2/P3/P4 ;
-   délai moyen de prise en charge ;
-   délai moyen de résolution ;
-   taux de respect SLA ;
-   taux de réouverture ;
-   taux de résolution ;
-   satisfaction ;
-   temps passé ;
-   incidents récurrents.

------------------------------------------------------------------------

## 73. Rapports

Prévoir exports contrôlés :

-   PDF ;
-   Excel si déjà supporté ;
-   rapports périodiques.

Exemples :

``` text
Rapport mensuel support
Rapport SLA
Rapport incidents
Rapport applications
Rapport satisfaction
```

------------------------------------------------------------------------

## 74. Recherche

Recherche simple et avancée sur :

``` text
N° ticket
Objet
Description
Demandeur
Structure
Catégorie
Sous-catégorie
Application
Équipement
Technicien
Équipe
Priorité
Statut
Période
SLA
Tags
```

------------------------------------------------------------------------

## 75. Permissions

Étudier notamment :

``` text
ticket.view
ticket.create
ticket.update
ticket.assign
ticket.reassign
ticket.take_charge
ticket.comment
ticket.internal_note
ticket.escalate
ticket.resolve
ticket.close
ticket.reopen
ticket.cancel

ticket.view_all
ticket.view_team
ticket.view_reports
ticket.manage_sla
ticket.manage_catalog
ticket.manage_categories

problem.view
problem.create
problem.update
problem.close

knowledge.view
knowledge.create
knowledge.review
knowledge.publish

ticket.audit.view
```

Adapter aux conventions existantes.

------------------------------------------------------------------------

## 76. Policies

Ne pas se contenter des permissions Spatie.

Les Policies doivent prendre en compte :

-   demandeur ;
-   structure ;
-   équipe ;
-   technicien ;
-   rôle ;
-   niveau de confidentialité éventuel ;
-   workflow ;
-   statut ;
-   délégation.

------------------------------------------------------------------------

## 77. Confidentialité

Prévoir des tickets sensibles, notamment sécurité.

Exemples :

``` text
NORMAL
RESTREINT
CONFIDENTIEL
```

Les incidents cybersécurité peuvent nécessiter un accès limité à
certaines équipes.

------------------------------------------------------------------------

## 78. Sécurité

Appliquer :

-   stockage privé ;
-   contrôle backend ;
-   validation upload ;
-   protection CSRF/XSS/SQLi ;
-   rate limiting ;
-   audit ;
-   liens temporaires ;
-   journalisation ;
-   contrôle des téléchargements ;
-   antivirus futur ;
-   masquage des données sensibles.

------------------------------------------------------------------------

## 79. Tables à étudier après audit

Évaluer, sans créer automatiquement :

``` text
tickets
ticket_types
ticket_categories
ticket_statuses
ticket_priorities

service_catalogs
service_items
service_item_fields

support_teams
support_team_members

ticket_assignments
ticket_comments
ticket_status_histories
ticket_relations
ticket_attachments
ticket_worklogs

sla_policies
sla_calendars
sla_events
ticket_slas
ticket_escalations

problems
problem_ticket_links
known_errors

knowledge_articles
knowledge_categories

assets
asset_types
applications

ticket_satisfactions
```

Réutiliser les tables transversales existantes lorsqu'elles conviennent.

------------------------------------------------------------------------

## 80. Services métier

Éviter la logique métier dans les controllers.

Étudier des services tels que :

``` text
TicketService
TicketAssignmentService
TicketPriorityService
SlaService
TicketEscalationService
ServiceCatalogService
ProblemService
KnowledgeService
TicketReportingService
```

Adapter à l'architecture existante.

------------------------------------------------------------------------

## 81. Événements

Prévoir des événements métier :

``` text
TicketCreated
TicketAssigned
TicketTakenInCharge
TicketEscalated
TicketResolved
TicketReopened
TicketClosed
SlaWarningReached
SlaBreached
```

pour découpler notifications, audit et autres traitements.

------------------------------------------------------------------------

## 82. Jobs

Utiliser des jobs pour les traitements appropriés :

-   notifications ;
-   escalades ;
-   vérification SLA ;
-   génération de rapports ;
-   indexation ;
-   email futur.

Rester compatible cPanel.

------------------------------------------------------------------------

## 83. Scheduler

Le scheduler Laravel peut vérifier périodiquement :

-   SLA proches ;
-   SLA dépassés ;
-   clôtures automatiques ;
-   escalades ;
-   rappels.

------------------------------------------------------------------------

## 84. API

Créer des APIs REST cohérentes.

Exemples conceptuels :

``` text
/api/tickets
/api/tickets/{id}
/api/tickets/{id}/assign
/api/tickets/{id}/take-charge
/api/tickets/{id}/comments
/api/tickets/{id}/internal-notes
/api/tickets/{id}/escalate
/api/tickets/{id}/resolve
/api/tickets/{id}/reopen
/api/tickets/{id}/close
/api/tickets/{id}/worklogs
/api/tickets/{id}/relations

/api/service-catalog
/api/problems
/api/knowledge
/api/sla-policies
```

Respecter les conventions du projet.

------------------------------------------------------------------------

## 85. Menu

Proposition :

``` text
Centre de services
├── Tableau de bord
├── Nouveau ticket
├── Mes tickets
├── Tickets de mon équipe
├── Tous les tickets
├── Non affectés
├── Incidents majeurs
├── Problèmes
├── Catalogue de services
├── Base de connaissances
├── Rapports
└── Administration
```

Afficher les éléments selon permissions.

------------------------------------------------------------------------

## 86. Administration

Prévoir :

``` text
Types de tickets
Catégories
Sous-catégories
Catalogue de services
Formulaires
Équipes
Règles d’affectation
Priorités
Matrice Impact/Urgence
SLA
Calendriers
Escalades
Statuts
Base de connaissances
Applications
Actifs
Paramètres
```

------------------------------------------------------------------------

## 87. Email → Ticket --- phase ultérieure

Préparer l'architecture pour une adresse de support.

Exemple :

``` text
support@domaine
       ↓
Email
       ↓
Ticket
       ↓
Réponse depuis plateforme
```

Gérer correctement les fils de discussion et éviter la création de
doublons.

------------------------------------------------------------------------

## 88. Notifications email

Les réponses email ne doivent pas exposer d'informations confidentielles
sans contrôle.

------------------------------------------------------------------------

## 89. IA --- phase ultérieure

Préparer des usages contrôlés :

``` text
Résumé du ticket
Suggestion de catégorie
Suggestion de priorité
Suggestion d’équipe
Détection de doublons
Suggestion d’article de connaissance
Résumé d’incident majeur
Recherche de solutions similaires
Proposition de réponse
```

------------------------------------------------------------------------

## 90. IA et sécurité

Toujours appliquer les permissions avant l'accès aux données utilisées
par l'IA.

Ne jamais envoyer automatiquement des incidents confidentiels vers un
fournisseur externe sans politique explicite.

------------------------------------------------------------------------

## 91. Principe IA

``` text
IA PROPOSE
    ↓
UTILISATEUR CONTRÔLE
    ↓
UTILISATEUR VALIDE
    ↓
APPLICATION EXÉCUTE
```

L'IA ne doit pas automatiquement :

-   clôturer ;
-   changer une priorité critique ;
-   affecter définitivement un ticket sensible ;
-   valider une habilitation ;
-   publier un article ;
-   exécuter une action administrative.

------------------------------------------------------------------------

## 92. Cas d'usage --- incident applicatif

``` text
Agent
 ↓
Nouveau ticket
 ↓
Application : SIGRAC
 ↓
"Impossible de se connecter"
 ↓
Impact / Urgence
 ↓
Priorité calculée
 ↓
Équipe Applications
 ↓
Technicien
 ↓
Diagnostic
 ↓
Résolution
 ↓
Validation utilisateur
 ↓
Clôture
```

------------------------------------------------------------------------

## 93. Cas d'usage --- habilitation

``` text
Agent
 ↓
Demande accès application
 ↓
Formulaire dynamique
 ↓
Validation responsable
 ↓
Workflow / Parapheur
 ↓
DSI
 ↓
Création accès
 ↓
Preuve
 ↓
Résolution
 ↓
Clôture
```

------------------------------------------------------------------------

## 94. Cas d'usage --- incident majeur

``` text
Plusieurs tickets
 ↓
Même panne réseau
 ↓
Déclaration incident majeur
 ↓
Regroupement
 ↓
Équipe Infrastructure
 ↓
Actions
 ↓
Communication
 ↓
Rétablissement
 ↓
Résolution tickets liés
 ↓
Analyse cause
 ↓
Création problème éventuel
 ↓
Rapport incident
```

------------------------------------------------------------------------

## 95. Cas d'usage --- problème récurrent

``` text
Incidents SIGRAC répétés
 ↓
Créer Problème
 ↓
Analyse
 ↓
Cause racine
 ↓
Workaround
 ↓
Erreur connue
 ↓
Correction
 ↓
Article connaissance
 ↓
Clôture
```

------------------------------------------------------------------------

## 96. Cas d'usage --- rapport d'intervention

``` text
Ticket
 ↓
Intervention
 ↓
Générer rapport
 ↓
Template DOCX
 ↓
ONLYOFFICE
 ↓
V1
 ↓
Correction
 ↓
V2
 ↓
Validation
 ↓
PDF final
 ↓
Ticket
 ↓
Versement GED éventuel
```

------------------------------------------------------------------------

## 97. Cas d'usage --- demande d'évolution

``` text
Ticket
 ↓
Qualification "Évolution"
 ↓
Analyse
 ↓
Création tâche/projet
 ↓
Validation
 ↓
Développement
 ↓
Test
 ↓
Déploiement
 ↓
Retour ticket
 ↓
Résolution
```

------------------------------------------------------------------------

## 98. Tests fonctionnels

Tester notamment :

``` text
création ticket
numérotation
catalogue service
formulaire dynamique
calcul priorité
SLA
affectation
réaffectation
prise en charge
commentaire public
note interne
attente demandeur
pause SLA
escalade
résolution
validation
réouverture
clôture
ticket parent/enfant
incident majeur
problème
base connaissance
rapport ONLYOFFICE
GED
Parapheur
notifications
audit
```

------------------------------------------------------------------------

## 99. Tests SLA

Tester :

-   calcul heure ouvrée ;
-   week-end ;
-   jour non ouvré ;
-   pause ;
-   reprise ;
-   warning ;
-   dépassement ;
-   changement priorité ;
-   réaffectation ;
-   escalade.

------------------------------------------------------------------------

## 100. Tests sécurité

Vérifier :

``` text
Demandeur A ne voit pas ticket privé de B
Technicien hors équipe ne voit pas ticket restreint
Note interne jamais visible au demandeur
Utilisateur non autorisé ne télécharge pas pièce
Utilisateur non autorisé ne modifie pas priorité
Utilisateur non autorisé ne clôture pas
Utilisateur non autorisé ne publie pas article
```

------------------------------------------------------------------------

## 101. Tests non-régression

Vérifier que le Ticketing ne casse pas :

-   authentification ;
-   structures ;
-   rôles ;
-   GED ;
-   documents ;
-   versions ;
-   ONLYOFFICE ;
-   Parapheur ;
-   workflows ;
-   tâches ;
-   instructions ;
-   notifications ;
-   audit ;
-   recherche ;
-   réunions ;
-   agenda ;
-   courrier.

------------------------------------------------------------------------

## 102. Performance

Prévoir :

-   pagination serveur ;
-   indexes SQL ;
-   requêtes optimisées ;
-   eager loading maîtrisé ;
-   cache seulement si pertinent ;
-   jobs pour traitements lourds ;
-   statistiques optimisées.

------------------------------------------------------------------------

## 103. Responsive

### Desktop

Administration et traitement complet.

### Tablette

Consultation, affectation, prise en charge, résolution.

### Mobile

Prioriser :

``` text
Créer ticket
Mes tickets
Commentaires
Notifications
Prise en charge
Actions rapides
```

------------------------------------------------------------------------

## 104. MVP --- Phase 1

Prioriser :

``` text
Tickets
Types
Catégories
Catalogue de services
Formulaires adaptés
Impact/Urgence/Priorité
Équipes
Dispatching
Affectation
Statuts
Commentaires
Notes internes
Pièces jointes
SLA
Relances
Escalades
Résolution
Réouverture
Clôture
Satisfaction
Notifications
Audit
Recherche
Dashboards
Rapports de base
```

------------------------------------------------------------------------

## 105. Phase 2

Ajouter :

``` text
Incidents majeurs
Problèmes
Erreurs connues
Base de connaissances avancée
Rapports d’intervention
ONLYOFFICE
GED
Approbations
Parapheur
Temps passé
Règles d’affectation avancées
Applications
Actifs / CMDB légère
Email → Ticket
```

------------------------------------------------------------------------

## 106. Phase 3

Ajouter :

``` text
IA
Recherche sémantique
Suggestion solutions
Détection doublons
Classification automatique
Résumé incident
Assistant technicien
Assistant utilisateur
Analyse des tendances
Maintenance prédictive éventuelle
```

------------------------------------------------------------------------

## 107. Vision fonctionnelle finale

Le module doit permettre :

``` text
SIGNALER
+
DEMANDER
+
QUALIFIER
+
PRIORISER
+
AFFECTER
+
TRAITER
+
ESCALADER
+
MESURER
+
RÉSOUDRE
+
VALIDER
+
CAPITALISER
+
AMÉLIORER
```

------------------------------------------------------------------------

## 108. Architecture finale cible

``` text
                  CENTRE DE SERVICES DGTCP
                            │
            ┌───────────────┼───────────────┐
            │               │               │
        INCIDENTS        DEMANDES        PROBLÈMES
            │               │               │
            └───────────────┼───────────────┘
                            │
                    CATALOGUE SERVICES
                            │
                      PRIORISATION
                            │
                           SLA
                            │
                       DISPATCHING
                            │
                  ÉQUIPES / TECHNICIENS
                            │
                  INTERVENTIONS / ACTIONS
                            │
              ┌─────────────┼─────────────┐
              │             │             │
             GED       e-PARAPHEUR    ONLYOFFICE
              │             │             │
              └─────────────┼─────────────┘
                            │
                   BASE CONNAISSANCES
                            │
                    TABLEAUX DE BORD
                            │
                       ASSISTANT IA
```

------------------------------------------------------------------------

## 109. Règle finale de conception

Ne construis pas un simple outil :

``` text
Créer ticket
→ Affecter
→ Fermer
```

Construis un véritable **Centre de Services institutionnel DGTCP**,
suffisamment simple pour l'utilisateur final mais suffisamment structuré
pour permettre à la DSI et aux responsables de maîtriser :

-   la demande ;
-   l'incident ;
-   la priorité ;
-   le niveau de service ;
-   la responsabilité ;
-   le délai ;
-   l'escalade ;
-   l'intervention ;
-   la résolution ;
-   la satisfaction ;
-   la connaissance ;
-   la performance.

------------------------------------------------------------------------

## 110. Principe de non-duplication

Avant chaque nouvelle table, service ou composant, vérifier si une
fonction équivalente existe déjà.

En particulier :

``` text
DOCUMENT → réutiliser le socle documentaire
VERSION → réutiliser document_versions
FICHIER → stockage privé existant
APPROBATION → réutiliser Workflow / Parapheur
TÂCHE → réutiliser Tâches / Instructions
MODÈLE → réutiliser moteur de modèles
ÉDITION DOCX → ONLYOFFICE
ARCHIVAGE → GED
NOTIFICATION → moteur transversal
AUDIT → audit transversal
UTILISATEUR → users
STRUCTURE → structures
```

------------------------------------------------------------------------

## 111. Principe d'expérience utilisateur

Le système peut être techniquement riche, mais l'utilisateur qui
souhaite simplement signaler une panne doit pouvoir le faire rapidement.

Exemple :

``` text
1. Choisir le service
2. Décrire le problème
3. Ajouter une capture
4. Envoyer
```

La complexité de SLA, dispatching, escalade, workflow et reporting doit
rester principalement derrière cette expérience simple.

------------------------------------------------------------------------

## 112. Livrables finaux attendus

À la fin de l'implémentation, fournir :

1.  rapport d'audit ;
2.  architecture retenue ;
3.  diagramme de données ;
4.  migrations ;
5.  modèles ;
6.  relations ;
7.  services métier ;
8.  controllers ;
9.  Form Requests ;
10. API Resources ;
11. Policies ;
12. permissions ;
13. routes API ;
14. pages Vue ;
15. composants Vue ;
16. catalogue de services ;
17. moteur SLA ;
18. dispatching ;
19. escalades ;
20. notifications ;
21. dashboards ;
22. rapports ;
23. intégration documentaire ;
24. intégration ONLYOFFICE si phase concernée ;
25. intégration Parapheur si phase concernée ;
26. base de connaissances ;
27. tests fonctionnels ;
28. tests sécurité ;
29. tests SLA ;
30. résultats des tests ;
31. documentation technique ;
32. guide utilisateur ;
33. guide administrateur ;
34. limites connues ;
35. recommandations pour les phases suivantes.

Le développement doit respecter l'architecture existante, éviter les
duplications et conserver une séparation claire entre **Ticketing, GED,
Parapheur, Documents, Tâches, Modèles et ONLYOFFICE**, tout en assurant
leur intégration fonctionnelle.
