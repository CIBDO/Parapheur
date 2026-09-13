# CAHIER DES CHARGES FONCTIONNEL

## Plateforme e‑Parapheur / Bureau Numérique de la DGTCP

**Projet :** Dématérialisation des circuits de consultation, d’instruction, de visa et de validation des documents  
**Maître d’ouvrage :** Direction Générale du Trésor et de la Comptabilité Publique – DGTCP  
**Solution proposée :** e‑Parapheur DGTCP  
**Architecture envisagée :** Laravel + Vue.js/Vuexy + MySQL/MariaDB sur cPanel + service ONLYOFFICE séparé  
**Type :** Application Web responsive et PWA à terme

---

## 1. Contexte

La Direction Générale du Trésor et de la Comptabilité Publique produit et échange quotidiennement un volume important de documents administratifs : notes, rapports, correspondances, comptes rendus, projets de décisions, documents de réunion, dossiers techniques, états financiers et autres pièces soumises à consultation, avis, visa ou validation.

Le fonctionnement actuel repose encore largement sur la circulation physique des documents.

Cette situation entraîne notamment :

- une consommation importante de papier ;
- l’accumulation de dossiers physiques au niveau de la Direction Générale ;
- le transport de piles de documents par les responsables ;
- des difficultés de suivi des dossiers ;
- des délais dans les circuits de validation ;
- la multiplication des impressions après correction ;
- une faible visibilité sur la position d’un document dans son circuit ;
- la difficulté à retrouver certaines observations antérieures ;
- l’absence de centralisation de l’historique des décisions ;
- des risques de perte ou de consultation non autorisée.

La DGTCP souhaite donc mettre en place une plateforme permettant de **dématérialiser progressivement le parapheur administratif**.

---

## 2. Objectif général

Mettre en place une plateforme électronique sécurisée permettant aux structures de la DGTCP de **déposer, transmettre, consulter, commenter, annoter, corriger, viser, valider, signer et archiver électroniquement les documents administratifs**.

Le système doit permettre au Directeur Général et aux autres responsables de disposer d’un **bureau numérique**, accessible notamment depuis ordinateur et tablette.

---

## 3. Principe fondamental

L'application ne doit pas être conçue comme un simple espace de stockage de fichiers.

Elle doit reproduire le **cycle de vie administratif du document** :

```text
CRÉATION
   ↓
DÉPÔT
   ↓
VALIDATION INTERNE
   ↓
TRANSMISSION
   ↓
RÉCEPTION
   ↓
CONSULTATION
   ↓
OBSERVATIONS / INSTRUCTIONS
   ↓
CORRECTION éventuelle
   ↓
VISA
   ↓
VALIDATION / SIGNATURE
   ↓
DIFFUSION
   ↓
CLASSEMENT
   ↓
ARCHIVAGE
```

Chaque opération doit être historisée.

---

## 4. Périmètre fonctionnel

La plateforme sera structurée autour de **14 modules fonctionnels** :

1. Tableau de bord ;
2. Parapheur électronique ;
3. Dépôt documentaire ;
4. Transmission des documents ;
5. Consultation et annotations ;
6. Gestion des versions ;
7. Workflow et circuits de validation ;
8. Instructions et recommandations ;
9. Visa, approbation et signature ;
10. Réunions et dossiers de séance ;
11. Notifications et relances ;
12. Recherche et archivage ;
13. Reporting et statistiques ;
14. Administration et sécurité.

---

## 5. Organisation des utilisateurs

L'application doit être organisée autour de la structure administrative réelle de la DGTCP.

```text
DGTCP
│
├── Direction Générale
│   ├── Directeur Général
│   ├── Directeur Général Adjoint
│   ├── Conseillers
│   └── Secrétariat
│
├── Directions
│   ├── Directeur
│   ├── Directeur Adjoint
│   ├── Chefs de division
│   ├── Chefs de section
│   └── Agents
│
└── Structures rattachées
    ├── Responsables
    └── Agents
```

Cette organisation doit être **paramétrable** et ne doit pas être codée en dur.

---

## 6. Profils utilisateurs

| Profil            | Principales possibilités                               |
| ----------------- | ------------------------------------------------------ |
| Administrateur    | Administration globale                                 |
| Directeur Général | Consultation, instruction, visa, validation, signature |
| DGA               | Consultation et traitement selon délégation            |
| Conseiller        | Analyse, avis et observations                          |
| Secrétariat DG    | Réception, enregistrement, dispatching                 |
| Directeur         | Validation des documents de sa direction               |
| Chef de division  | Contrôle et transmission                               |
| Chef de section   | Préparation et contrôle                                |
| Agent             | Création et dépôt                                      |
| Lecteur           | Consultation uniquement                                |

Les permissions doivent utiliser un système **RBAC** et être paramétrables.

---

## 7. Tableau de bord du Directeur Général

L’écran du Directeur Général doit être simple, clair et orienté action.

```text
┌──────────────────────────────────────────────────────┐
│                 MON PARAPHEUR                        │
├──────────────────────────────────────────────────────┤
│                                                      │
│  📥 À consulter        18     🔴 Urgents        4    │
│                                                      │
│  ✍ À viser              6     ✅ À valider       8   │
│                                                      │
│  🔄 Retournés           3     ⏳ En attente      5   │
│                                                      │
│  📑 Pour information   11     ✔ Traités         43   │
│                                                      │
├──────────────────────────────────────────────────────┤
│ DOCUMENTS À TRAITER                                  │
│                                                      │
│ DSI     Note interconnexion      Validation   URGENT │
│ DNTCP   Rapport trimestriel      Consultation        │
│ DRH     Projet décision          Signature           │
│ DFM     Situation budgétaire     Avis                │
└──────────────────────────────────────────────────────┘
```

Le DG ne doit pas parcourir une arborescence documentaire complexe. Son parapheur doit lui présenter automatiquement les dossiers nécessitant son intervention.

---

## 8. Dépôt d'un document

Un utilisateur habilité clique sur **+ Nouveau document**.

### 8.1 Identification

Le formulaire doit permettre de renseigner :

- objet ;
- référence ;
- type de document ;
- structure émettrice ;
- auteur ;
- date ;
- niveau de priorité ;
- degré de confidentialité ;
- date limite de traitement ;
- mots-clés éventuels.

### 8.2 Fichiers

Le dépôt doit permettre d’ajouter :

- un document principal ;
- plusieurs pièces jointes ;
- plusieurs annexes ;
- éventuellement des fichiers complémentaires.

### 8.3 Action attendue

L’expéditeur doit préciser l’action demandée :

- pour information ;
- pour consultation ;
- pour avis ;
- pour observations ;
- pour instruction ;
- pour visa ;
- pour validation ;
- pour signature.

---

## 9. Types de documents

Le référentiel doit être administrable.

Exemples :

- Note ;
- Note technique ;
- Note d'information ;
- Note de service ;
- Rapport ;
- Projet de lettre ;
- Lettre ;
- Décision ;
- Projet de décision ;
- Compte rendu ;
- Procès-verbal ;
- Termes de référence ;
- Convention ;
- Contrat ;
- Rapport de mission ;
- Document budgétaire ;
- Document financier ;
- Dossier de réunion ;
- Présentation ;
- Tableau ;
- Autre.

---

## 10. Niveaux de confidentialité

La plateforme doit gérer au minimum les niveaux suivants :

### NORMAL

Document accessible selon les permissions normales.

### RESTREINT

Accessible uniquement aux acteurs du circuit.

### CONFIDENTIEL

Accès nominatif et contrôlé.

### TRÈS CONFIDENTIEL

Accès explicitement autorisé avec journalisation renforcée.

La confidentialité doit être gérée **au niveau métier et applicatif**.

---

## 11. Parapheur électronique

Chaque responsable dispose de son propre parapheur.

Les principales rubriques sont :

- À traiter ;
- À consulter ;
- Pour information ;
- À viser ;
- À valider ;
- À signer ;
- En attente ;
- Retournés ;
- Traités ;
- Archivés.

---

## 12. Fiche électronique d'un dossier

Un document ne doit jamais être réduit à un simple fichier.

Chaque dossier doit disposer d’une fiche complète.

```text
--------------------------------------------------
NOTE TECHNIQUE
--------------------------------------------------

Objet :
Interconnexion avec le système XXX

Référence :
DGTCP/DSI/2026/0045

Provenance :
Direction des Systèmes d'Information

Priorité :
URGENT

Confidentialité :
RESTREINT

Action attendue :
VALIDATION

Date limite :
15/09/2026

--------------------------------------------------

DOCUMENT PRINCIPAL
Note_Interconnexion_V3.docx

PIÈCES JOINTES
Architecture.pdf
Budget.xlsx

--------------------------------------------------

CIRCUIT

✓ Agent DSI
✓ Chef Division
✓ Directeur DSI
✓ Secrétariat DG
→ DIRECTEUR GÉNÉRAL

--------------------------------------------------

OBSERVATIONS

DG
09/09/2026 09:34

« Vérifier les aspects relatifs à la sécurité. »

--------------------------------------------------
```

---

## 13. Consultation documentaire

Depuis la fiche du dossier, l'utilisateur doit pouvoir :

- visualiser le document ;
- télécharger selon ses permissions ;
- zoomer ;
- parcourir les pages ;
- consulter les annexes ;
- afficher l'historique ;
- consulter les commentaires ;
- ajouter une observation ;
- visualiser les versions précédentes.

Pour les documents compatibles avec ONLYOFFICE :

- DOCX ;
- XLSX ;
- PPTX ;

l'utilisateur pourra accéder directement à l’éditeur intégré.

---

## 14. Commentaires

La plateforme doit prendre en charge deux niveaux de commentaires.

### 14.1 Commentaire général

Exemple :

> Avis favorable sous réserve de la prise en compte des observations.

### 14.2 Commentaire contextualisé

L'utilisateur sélectionne une phrase du document et ajoute une observation.

Exemple :

> Merci de préciser la source de cette donnée.

Cette fonctionnalité pourra être assurée par ONLYOFFICE.

---

## 15. Suggestions de modifications

La plateforme doit distinguer clairement :

- **Commenter** ;
- **Suggérer une modification** ;
- **Modifier directement**, lorsque l’utilisateur en a le droit.

Les suggestions de modifications doivent pouvoir être :

- acceptées ;
- rejetées ;
- historisées.

---

## 16. Gestion des versions

Chaque nouvelle correction doit créer une nouvelle version.

```text
V1 — Document initial
↓
Observation Directeur
↓
V2 — Corrections
↓
Observation DG
↓
V3 — Corrections
↓
Validation
↓
VERSION DÉFINITIVE
```

Toutes les versions doivent être conservées.

Il doit être impossible de remplacer silencieusement un document déjà transmis.

---

## 17. Workflow

Le système doit supporter deux types principaux de workflow.

### 17.1 Workflow libre

L'expéditeur choisit les destinataires.

```text
DSI → DG
```

### 17.2 Workflow prédéfini

Exemple :

```text
Agent
 ↓
Chef Section
 ↓
Chef Division
 ↓
Directeur
 ↓
Secrétariat DG
 ↓
DG
```

Les circuits doivent être configurables depuis l’administration.

---

## 18. Actions possibles

Selon le rôle, les permissions et l'étape du circuit, l'utilisateur pourra :

- prendre connaissance ;
- commenter ;
- émettre un avis ;
- donner une instruction ;
- recommander ;
- demander un complément ;
- retourner pour correction ;
- valider ;
- rejeter ;
- viser ;
- signer ;
- transmettre ;
- réaffecter ;
- mettre en attente ;
- classer ;
- archiver.

Chaque action doit permettre l'ajout facultatif ou obligatoire d'un commentaire.

---

## 19. Gestion des instructions du DG

Le DG doit pouvoir transformer une observation en **instruction formelle**.

Exemple :

> **Instruction DG**  
> DSI : proposer une architecture sécurisée et me faire un retour avant vendredi.

L'instruction devient une tâche.

```text
Responsable : Directeur DSI

Échéance : 11/09/2026

Priorité : Haute

Statut :
À faire

Document lié :
NOTE-2026-0045
```

---

## 20. Suivi des instructions

Un module spécifique doit permettre de suivre :

- les nouvelles instructions ;
- les instructions en cours ;
- les instructions en retard ;
- les instructions exécutées ;
- les instructions clôturées.

Exemple de synthèse :

```text
Instructions données           48
Exécutées                      31
En cours                       12
En retard                       5
```

---

## 21. Gestion des réunions

Le système doit permettre de réduire les impressions lors des réunions.

### 21.1 Création d’une réunion

Informations à saisir :

- objet ;
- date ;
- heure ;
- lieu ;
- président ;
- participants ;
- ordre du jour ;
- observations éventuelles.

### 21.2 Dossier électronique de réunion

```text
Réunion de Direction
15 septembre 2026

1. Ordre du jour
2. Procès-verbal précédent
3. Situation budgétaire
4. Projet SIGRAC
5. Projet Microfinance
6. Questions diverses
```

Chaque participant autorisé doit pouvoir consulter le dossier depuis un ordinateur, une tablette ou un smartphone.

---

## 22. Décisions issues d'une réunion

À partir d’un compte rendu ou d’un procès-verbal, le système doit pouvoir créer des décisions ou actions de suivi.

```text
Décision 01
Responsable : DSI
Échéance : 30/09/2026

Décision 02
Responsable : DFM
Échéance : 20/09/2026
```

Ces décisions alimentent automatiquement le module de suivi.

---

## 23. Notifications

La plateforme doit disposer de notifications internes et, si nécessaire, d’alertes par email.

Exemples :

- Nouveau document à consulter ;
- Document transmis pour validation ;
- Nouvelle observation du DG ;
- Document retourné pour correction ;
- Document validé ;
- Instruction reçue ;
- Échéance dans 48 heures ;
- Instruction en retard ;
- Réunion ajoutée ou modifiée ;
- Nouvelle version d’un document disponible.

---

## 24. Recherche documentaire

La recherche doit permettre de filtrer par :

- objet ;
- référence ;
- type ;
- contenu des métadonnées ;
- direction ;
- auteur ;
- destinataire ;
- période ;
- statut ;
- priorité ;
- niveau de confidentialité ;
- décision ;
- numéro de dossier ;
- mots-clés.

---

## 25. Archivage

Après traitement, le dossier électronique final doit contenir :

```text
DOCUMENT
+
VERSIONS
+
PIÈCES JOINTES
+
COMMENTAIRES
+
AVIS
+
INSTRUCTIONS
+
VALIDATIONS
+
SIGNATURES
+
HISTORIQUE
```

L’ensemble constitue le dossier électronique définitif.

---

## 26. Journal d'audit

Le système doit enregistrer les opérations sensibles.

Exemple :

```text
09/09/2026 08:12
Connexion utilisateur

09/09/2026 08:15
Consultation NOTE-0045

09/09/2026 08:21
Commentaire ajouté

09/09/2026 08:25
Document retourné à DSI

09/09/2026 09:04
Version V2 déposée

09/09/2026 09:18
Document validé
```

Le journal d’audit ne doit pas être modifiable par les utilisateurs standards.

---

## 27. Statuts

Les principaux statuts proposés sont :

```text
BROUILLON
DÉPOSÉ
EN CIRCUIT
TRANSMIS
À CONSULTER
EN CONSULTATION
EN ATTENTE
À CORRIGER
CORRIGÉ
À VISER
VISÉ
À VALIDER
VALIDÉ
REJETÉ
À SIGNER
SIGNÉ
TRAITÉ
CLASSÉ
ARCHIVÉ
ANNULÉ
```

Le workflow détermine les transitions autorisées.

---

## 28. Priorité

Quatre niveaux de priorité sont proposés :

```text
NORMALE
IMPORTANTE
URGENTE
TRÈS URGENTE
```

Chaque dossier peut disposer d’une date limite de traitement.

---

## 29. Délégation

Le système doit gérer les délégations temporaires.

Exemple :

> Directeur Général absent du 15 au 20 septembre.

La délégation doit préciser :

- bénéficiaire ;
- date de début ;
- date de fin ;
- types de documents concernés ;
- actions autorisées.

La traçabilité doit toujours préciser la délégation.

Exemple :

> Validé par le DGA par délégation du DG.

---

## 30. Visa et signature

Le système doit séparer :

### 30.1 Validation électronique

Exemple :

> VALIDÉ par Monsieur X le 09/09/2026 à 09:45.

### 30.2 Visa électronique

Apposition d'un visa administratif dans le circuit.

### 30.3 Signature électronique

La signature électronique juridiquement engageante devra être intégrée dans une phase dédiée selon les exigences réglementaires et de sécurité retenues par la DGTCP.

Un simple bouton **Signer** ne doit pas être confondu avec une véritable signature électronique qualifiée.

---

## 31. Tableau de bord des Directeurs

Chaque directeur doit disposer d’un tableau de bord limité à sa direction.

Exemple :

```text
Documents préparés        43
En validation interne      8
Transmis DG                6
Retournés                  3
Validés                   21
En retard                  5
```

---

## 32. Tableau de bord DG

Le Directeur Général doit pouvoir consulter notamment :

- nombre de dossiers reçus ;
- dossiers à traiter ;
- dossiers urgents ;
- dossiers en retard ;
- dossiers par direction ;
- dossiers validés ;
- dossiers retournés ;
- instructions données ;
- instructions non exécutées ;
- délai moyen de traitement.

---

## 33. Indicateurs de performance

La plateforme doit permettre à terme de produire les indicateurs suivants :

- délai moyen de traitement par direction ;
- nombre de documents soumis au DG ;
- taux de retour pour correction ;
- nombre de documents traités électroniquement ;
- nombre estimatif de documents imprimés évités ;
- nombre d'instructions non exécutées ;
- taux d'exécution des décisions ;
- volume documentaire par structure ;
- taux de respect des échéances.

---

## 34. Architecture adaptée au cPanel

L’architecture cible recommandée est la suivante :

```text
                   UTILISATEURS
                        │
                        ▼
              https://parapheur.xxx
                        │
                        ▼
              ┌───────────────────┐
              │      cPanel       │
              │                   │
              │ Laravel / Vue.js  │
              │                   │
              │ Workflow          │
              │ Parapheur         │
              │ Dashboard         │
              │ Notifications     │
              │ API               │
              └─────────┬─────────┘
                        │
                  MySQL/MariaDB
                        │
           ┌────────────┴────────────┐
           │                         │
      STOCKAGE                  ONLYOFFICE
     DOCUMENTAIRE               DOCS SERVER
       cPanel                        │
                               VPS / serveur
                               indépendant
```

### Principe

Le cPanel héberge :

- Laravel ;
- Vue.js ;
- MySQL/MariaDB ;
- fichiers privés ;
- API métier ;
- notifications ;
- workflows.

ONLYOFFICE Docs doit être installé sur un serveur séparé, de préférence un VPS ou serveur interne compatible Docker.

---

## 35. Nextcloud non obligatoire en V1

L’utilisation de Nextcloud n’est pas indispensable dans la première version.

L’architecture recommandée est :

```text
Laravel
   │
   ├── Utilisateurs
   ├── Structures
   ├── Documents
   ├── Workflow
   ├── Permissions
   ├── Commentaires
   ├── Instructions
   ├── Notifications
   ├── Historique
   │
   └── ONLYOFFICE API
             │
             ▼
       édition DOCX/XLSX/PPTX
```

Laravel reste le cœur métier de la plateforme.

---

## 36. Architecture technique recommandée

### Backend

- Laravel 12

### Frontend

- Vue.js
- Vuexy / Vuetify

### Base de données

- MySQL ou MariaDB

### Authentification

- Laravel Sanctum

### Gestion des rôles

- Spatie Laravel Permission

### Stockage

- Laravel Storage
- stockage privé non directement exposé au Web

### Documents

- DOCX
- XLSX
- PPTX
- PDF
- images

### Édition collaborative

- ONLYOFFICE Docs

### PDF

- Visionneuse PDF intégrée

### Notifications

- Laravel Notifications
- email

### Traitements différés

- Laravel Queue

### Tâches planifiées

Cron cPanel :

```bash
* * * * * php /home/USER/application/artisan schedule:run
```

### Exports

- PDF
- Excel
- éventuellement Word

### Audit

- journalisation métier dédiée.

---

## 37. Stockage sécurisé des fichiers

Les documents confidentiels ne doivent pas être stockés dans :

```text
public/uploads/documents/
```

Le stockage recommandé est :

```text
storage/app/private/documents/
```

Les fichiers doivent être accessibles uniquement via des contrôleurs Laravel autorisant l’utilisateur après vérification des permissions.

Une URL directe comme :

```text
https://site.com/uploads/note-confidentielle.pdf
```

ne doit jamais donner accès au document.

---

## 38. Modèle de données principal

Tables proposées :

```text
users
roles
permissions

structures
structure_types
positions

documents
document_types
document_files
document_versions
document_attachments

workflows
workflow_steps
workflow_instances
workflow_actions

document_transmissions
document_recipients

comments
annotations
reviews

instructions
instruction_updates

visas
approvals
signatures

meetings
meeting_participants
meeting_documents
meeting_decisions

notifications

delegations

document_confidentialities
document_priorities
document_statuses

audit_logs
```

---

## 39. Responsive et tablette

La solution doit être conçue pour :

- **Desktop** : travail administratif complet ;
- **Tablette** : consultation et traitement par le DG et les Directeurs ;
- **Mobile** : notifications, consultation rapide et actions simples.

La tablette constitue une cible prioritaire.

---

## 40. Sécurité

La solution doit intégrer au minimum :

- HTTPS obligatoire ;
- mots de passe robustes ;
- contrôle des sessions ;
- RBAC ;
- contrôle d'accès document par document ;
- journalisation ;
- protection CSRF ;
- protection XSS ;
- prévention des injections SQL ;
- liens temporaires pour les documents ;
- chiffrement des secrets ;
- limitation des tentatives de connexion ;
- sauvegarde quotidienne ;
- restauration testée ;
- contrôle antivirus des fichiers téléversés si possible ;
- authentification à deux facteurs pour les comptes sensibles ;
- expiration des sessions ;
- révocation des accès ;
- séparation des documents confidentiels.

---

## 41. Sauvegardes

Stratégie minimale :

```text
APPLICATION
      ↓
Sauvegarde quotidienne

BASE DE DONNÉES
      ↓
Sauvegarde quotidienne

DOCUMENTS
      ↓
Sauvegarde quotidienne

      +
      ↓

COPIE EXTERNE
```

Une sauvegarde conservée uniquement sur le même cPanel que l’application n’est pas suffisante.

---

## 42. MVP – Phase 1

La première version doit couvrir les fonctionnalités prioritaires permettant une démonstration rapide au Directeur Général.

### Fonctionnalités MVP

1. Authentification ;
2. utilisateurs ;
3. structures ;
4. rôles et permissions ;
5. dépôt documentaire ;
6. parapheur ;
7. transmission ;
8. workflow ;
9. commentaires ;
10. versions ;
11. visa ;
12. validation ;
13. notifications ;
14. historique ;
15. tableau de bord DG ;
16. tableau de bord Direction ;
17. visualisation PDF ;
18. responsive tablette.

---

## 43. Phase 2 – Intégration ONLYOFFICE

Ajouter :

- édition DOCX ;
- édition XLSX ;
- édition PPTX ;
- commentaires contextualisés ;
- mode révision ;
- suggestions de modifications ;
- édition collaborative ;
- comparaison de versions.

---

## 44. Phase 3 – Collaboration et pilotage

Ajouter :

- réunions ;
- dossiers de séance ;
- instructions ;
- décisions ;
- échéances ;
- délégations ;
- statistiques ;
- reporting ;
- archivage avancé.

---

## 45. Phase 4 – Extensions

Ajouter éventuellement :

- signature électronique ;
- LDAP / Active Directory ;
- SSO ;
- PWA ;
- notifications push ;
- OCR ;
- recherche plein texte ;
- courrier arrivée/départ ;
- API avec les autres systèmes de la DGTCP ;
- intégration à un système d’archivage électronique.

---

## 46. Interface cible du DG

```text
╔══════════════════════════════════════════════╗
║               Bonjour Monsieur              ║
║             Directeur Général               ║
╠══════════════════════════════════════════════╣
║                                              ║
║     23                                       ║
║     DOCUMENTS À TRAITER                      ║
║                                              ║
║   🔴 4 urgents                               ║
║                                              ║
╠══════════════════════════════════════════════╣
║                                              ║
║  DSI                                         ║
║  Note relative à l'interconnexion            ║
║                                              ║
║  POUR VALIDATION              🔴 URGENT      ║
║                                              ║
║       [ OUVRIR LE DOCUMENT ]                 ║
║                                              ║
╠══════════════════════════════════════════════╣
║                                              ║
║   COMMENTER     RETOURNER     VALIDER        ║
║                                              ║
╚══════════════════════════════════════════════╝
```

Le succès du projet dépend fortement de la simplicité de cette interface.

---

## 47. Processus cible

### Situation actuelle

```text
Direction
   ↓
Impression
   ↓
Paraphe
   ↓
Secrétariat
   ↓
Pile de documents
   ↓
DG
   ↓
Annotations manuscrites
   ↓
Retour secrétariat
   ↓
Retour Direction
   ↓
Correction
   ↓
Réimpression
```

### Situation cible

```text
Direction
       ↓
e-PARAPHEUR 
       ↓
DG / TABLETTE
       ↓
Commentaire
Instruction
Visa
Validation
       ↓
Direction
       ↓
Correction éventuelle
       ↓
Validation définitive
       ↓
ARCHIVAGE
```

---

## 48. Recommandation d'architecture finale

L’architecture recommandée pour la DGTCP est :

- **Laravel 12 + Vue.js/Vuexy + MySQL/MariaDB sur cPanel** pour l’application métier ;
- **ONLYOFFICE Docs sur un VPS ou serveur séparé** pour l’édition collaborative ;
- **stockage privé des documents** piloté par Laravel ;
- **workflow configurable** pour les circuits administratifs ;
- **journal d’audit complet** ;
- **interface optimisée tablette** pour le DG et les Directeurs ;
- **déploiement progressif par phases**.

L’objectif final est de transformer l’application en véritable **bureau numérique de la DGTCP**, capable de réduire significativement l’utilisation du papier tout en améliorant la traçabilité, la rapidité des traitements et la gouvernance documentaire.
