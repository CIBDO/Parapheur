# PROMPT MAÎTRE — E-TRÉSOR
## Conception et implémentation du module transversal « Tâches, Directives & Productions périodiques »

### 1. Rôle de l'agent de développement

Tu interviens comme **architecte logiciel senior, analyste fonctionnel, expert Laravel, Vue.js, gestion électronique de documents, workflow administratif et automatisation des processus métiers**.

Tu travailles sur **E-Trésor**, une plateforme institutionnelle de la Direction Générale du Trésor et de la Comptabilité Publique (DGTCP) du Mali.

Ta mission consiste à analyser l'application existante, concevoir et implémenter un module transversal de gestion des tâches, directives, productions périodiques et transmissions documentaires.

**IMPORTANT : ne commence pas par générer du code.**

Commence par auditer le projet existant afin d'identifier :

- L'architecture technique réelle ;
- Les modules déjà développés ;
- Le système d'authentification et d'autorisation ;
- Les modèles de données existants ;
- Le module Courrier ;
- Le module Parapheur et ses workflows ;
- Le module GED ;
- Le module Annuaire institutionnel ;
- Les services de notifications ;
- L'intégration ONLYOFFICE ;
- Les mécanismes de tâches, d'affectation ou de dispatching éventuellement existants ;
- Les conventions de développement et les composants d'interface réutilisables.

Réutilise systématiquement les composants existants lorsque cela est pertinent.

Ne crée pas de doublons fonctionnels ou techniques.

Toute nouvelle fonctionnalité doit respecter l'architecture et les conventions du projet.

---

# 2. Contexte institutionnel

À la suite de la présentation de E-Trésor à la Direction Générale, il a été demandé de mettre en place un système permettant :

1. La création et l'affectation de tâches aux agents et aux structures ;
2. Le suivi de leur exécution ;
3. La gestion des tâches récurrentes ;
4. La production et la transmission de documents à des échéances définies ;
5. La gestion de plusieurs fichiers pour un même livrable ;
6. La validation hiérarchique des productions ;
7. La transmission sécurisée aux destinataires ;
8. Les rappels et relances automatiques ;
9. La traçabilité des actions ;
10. L'archivage documentaire ;
11. Le pilotage des activités par les responsables ;
12. La consultation des productions par la Direction Générale.

Un besoin initial concerne la transmission, avant 10 h, de situations quotidiennes de trésorerie au DG et au DGA.

Cependant, **le système ne doit absolument pas être conçu uniquement pour ces situations**.

Il doit pouvoir couvrir toute obligation de production, de transmission ou d'exécution, quelle que soit sa fréquence, sa structure responsable, son type de document ou ses destinataires.

---

# 3. Vision générale

Développer un module transversal intitulé :

**« Tâches & Directives »**

Ce module doit comporter plusieurs capacités complémentaires :

- Gestion des directives ;
- Gestion des tâches et sous-tâches ;
- Gestion des activités récurrentes ;
- Gestion des obligations de production ;
- Gestion des livrables et pièces jointes ;
- Gestion des échéances ;
- Gestion des circuits de validation ;
- Gestion des transmissions ;
- Gestion des relances ;
- Gestion des tableaux de bord ;
- Gestion des historiques et archives.

Le sous-module spécialisé pourra être intitulé :

**« Productions & Transmissions »**

Il doit être suffisamment générique pour gérer des situations quotidiennes, rapports périodiques, notes administratives, comptes rendus, états financiers, statistiques, documents réglementaires et autres livrables institutionnels.

---

# 4. Principes d'architecture fonctionnelle

## 4.1 Distinguer les concepts

### Directive

Une directive correspond à une orientation, une demande ou une décision formulée par une autorité.

Elle peut être adressée à une structure, un responsable ou plusieurs collaborateurs.

Une directive peut générer plusieurs tâches.

### Tâche

Une tâche représente une action concrète à exécuter.

Elle peut être ponctuelle ou récurrente.

Elle peut nécessiter un livrable, une validation ou une simple confirmation d'exécution.

### Sous-tâche

Une sous-tâche constitue une décomposition opérationnelle d'une tâche.

### Obligation de production

Une obligation de production est une configuration définissant :

- Ce qui doit être produit ;
- Par qui ;
- Pour qui ;
- À quelle fréquence ;
- Dans quel délai ;
- Sous quel format ;
- Avec quel circuit de validation ;
- Selon quelles règles de transmission.

### Occurrence

Une occurrence est l'exécution concrète d'une obligation de production pour une date ou une période donnée.

Exemple :

Obligation : produire quotidiennement la situation des comptes bancaires.

Occurrence : situation des comptes bancaires du 12 octobre 2026.

### Livrable

Un livrable représente le résultat attendu d'une tâche ou d'une obligation de production.

Il peut comporter :

- Un fichier ;
- Plusieurs fichiers ;
- Une note ;
- Un rapport ;
- Un formulaire ;
- Une référence vers un document GED ;
- Une production réalisée dans ONLYOFFICE ;
- Une confirmation d'exécution sans fichier, si la configuration le permet.

### Transmission

La transmission est l'action formelle de mettre un livrable validé à disposition d'un ou plusieurs destinataires.

Elle doit être distincte du dépôt et de la validation.

---

# 5. Module de gestion des directives

Implémenter une gestion complète des directives.

## Fonctionnalités

- Création d'une directive ;
- Référence unique ;
- Objet ;
- Description ;
- Autorité émettrice ;
- Structure émettrice ;
- Destinataires ;
- Priorité ;
- Date d'émission ;
- Date limite ;
- Pièces jointes ;
- Instructions complémentaires ;
- Niveau de confidentialité ;
- Responsable de coordination ;
- Décomposition en tâches ;
- Suivi d'avancement ;
- Relances ;
- Clôture ;
- Historique.

Une directive doit pouvoir être liée à :

- Un courrier ;
- Une réunion ;
- Une décision ;
- Un dossier ;
- Un document GED ;
- Une autre directive, si nécessaire.

Prévoir les statuts :

- Brouillon ;
- Émise ;
- Prise en charge ;
- En cours ;
- En attente ;
- Exécutée ;
- En validation ;
- Clôturée ;
- Annulée.

Les transitions doivent être contrôlées par des règles métier.

---

# 6. Module de gestion des tâches

Développer un système complet de gestion des tâches.

## 6.1 Informations d'une tâche

- Identifiant ;
- Référence ;
- Intitulé ;
- Description ;
- Type ;
- Catégorie ;
- Origine ;
- Structure émettrice ;
- Créateur ;
- Responsable ;
- Exécutant principal ;
- Contributeurs ;
- Observateurs ;
- Date de création ;
- Date de début ;
- Échéance ;
- Priorité ;
- Statut ;
- Pourcentage d'avancement ;
- Niveau de confidentialité ;
- Pièces jointes ;
- Livrables attendus ;
- Commentaires ;
- Historique.

## 6.2 Types de tâches

Le système doit permettre de configurer des types de tâches, par exemple :

- Tâche administrative ;
- Tâche technique ;
- Tâche documentaire ;
- Tâche de validation ;
- Tâche de production ;
- Tâche de transmission ;
- Tâche de contrôle ;
- Tâche de suivi ;
- Tâche issue d'une réunion ;
- Tâche issue d'un courrier ;
- Tâche issue d'une directive.

Ne pas coder ces types en dur.

## 6.3 Fonctionnalités

Prévoir :

- Création ;
- Affectation ;
- Réaffectation ;
- Délégation ;
- Acceptation ou prise en charge ;
- Exécution ;
- Mise en attente ;
- Suspension ;
- Reprise ;
- Commentaires ;
- Sous-tâches ;
- Dépendances ;
- Dépôt de livrables ;
- Soumission pour validation ;
- Validation ;
- Rejet avec motif ;
- Correction ;
- Clôture ;
- Réouverture autorisée ;
- Annulation motivée.

Prévoir les règles de visibilité selon les rôles et la hiérarchie.

Une tâche doit pouvoir être créée sans directive préalable.

---

# 7. Moteur générique de productions périodiques

**Ce moteur constitue une composante majeure du projet.**

Il doit permettre de configurer librement les obligations de production et de transmission.

## 7.1 Référentiel des obligations

Créer une interface d'administration permettant de définir une obligation de production.

Champs attendus :

- Code unique ;
- Libellé ;
- Description ;
- Catégorie ;
- Domaine fonctionnel ;
- Structure responsable ;
- Service producteur ;
- Responsable principal ;
- Responsable suppléant ;
- Contributeurs ;
- Autorité de validation ;
- Destinataires ;
- Fréquence ;
- Calendrier ;
- Heure limite ;
- Fuseau horaire ;
- Date de début de validité ;
- Date de fin de validité ;
- Niveau de confidentialité ;
- Type de livrable ;
- Formats autorisés ;
- Nombre minimal et maximal de fichiers, si applicable ;
- Taille maximale ;
- Modèle documentaire facultatif ;
- Validation obligatoire ou facultative ;
- Transmission automatique ou manuelle ;
- Règles de relance ;
- Règles d'escalade ;
- Règles d'archivage ;
- Statut actif ou inactif.

Les modifications de configuration doivent être historisées.

Une obligation désactivée ne doit plus générer de nouvelles occurrences, sans supprimer l'historique.

## 7.2 Périodicités

Prendre en charge :

- Ponctuelle ;
- Quotidienne ;
- Jours ouvrables ;
- Hebdomadaire ;
- Plusieurs jours définis par semaine ;
- Bimensuelle, avec définition explicite du sens ;
- Mensuelle ;
- Trimestrielle ;
- Semestrielle ;
- Annuelle ;
- À des dates spécifiques ;
- Selon une règle personnalisée.

Prévoir :

- Jours fériés ;
- Calendriers institutionnels ;
- Dates exceptionnelles ;
- Reports d'échéances ;
- Suspensions temporaires ;
- Dates de référence ;
- Périodes couvertes ;
- Exceptions de calendrier.

La date de production, la période de référence et la date limite de transmission doivent être distinguées.

## 7.3 Génération automatique

Développer un service permettant de générer automatiquement les occurrences des obligations actives.

Exigences :

- Utiliser le scheduler de l'application ;
- Exécuter les traitements lourds dans des jobs ;
- Éviter toute duplication ;
- Garantir l'idempotence ;
- Journaliser les exécutions ;
- Permettre une régénération contrôlée ;
- Gérer les erreurs ;
- Gérer les obligations temporairement suspendues ;
- Éviter les occurrences sur des dates exclues ;
- Respecter le fuseau horaire configuré.

Une contrainte d'unicité doit empêcher la génération accidentelle de plusieurs occurrences identiques pour une même obligation et une même période.

---

# 8. Gestion des occurrences

Chaque occurrence doit posséder :

- Une référence unique ;
- L'obligation source ;
- La structure responsable ;
- Le responsable ;
- La date de référence ;
- La période couverte ;
- La date limite ;
- Le statut ;
- Les livrables attendus ;
- Les fichiers déposés ;
- Les validateurs ;
- Les destinataires ;
- Les dates de dépôt, validation et transmission ;
- Les observations ;
- L'historique ;
- Les éventuelles anomalies.

## Statuts proposés

- À produire ;
- En préparation ;
- Déposée ;
- À compléter ;
- Soumise à validation ;
- En validation ;
- Rejetée ;
- Validée ;
- Prête à transmettre ;
- Transmise ;
- Clôturée ;
- Annulée.

Le statut « En retard » doit de préférence être calculé à partir des échéances et de l'état de l'occurrence, plutôt que constituer un statut métier exclusif.

Prévoir également la distinction entre :

- Non déposée ;
- Déposée en retard ;
- Validée en retard ;
- Transmise en retard.

---

# 9. Gestion multi-fichiers des livrables

Une occurrence peut comporter un ou plusieurs fichiers.

Les formats doivent être configurables :

- PDF ;
- XLSX ;
- DOCX ;
- CSV ;
- Images ;
- Autres formats explicitement autorisés.

Chaque fichier doit disposer de métadonnées :

- Nom original ;
- Nom de stockage ;
- Type MIME vérifié ;
- Taille ;
- Empreinte ;
- Auteur ;
- Date de dépôt ;
- Version ;
- Description ;
- Statut ;
- Niveau de confidentialité.

## Fonctionnalités

- Dépôt multiple ;
- Ajout de fichiers complémentaires ;
- Remplacement par nouvelle version ;
- Prévisualisation autorisée ;
- Téléchargement sécurisé ;
- Historique des versions ;
- Commentaires ;
- Suppression contrôlée avant validation ;
- Conservation des versions déjà transmises ;
- Lien avec la GED ;
- Utilisation de modèles ONLYOFFICE lorsque nécessaire.

Un fichier déjà transmis ne doit pas être écrasé.

Toute correction ultérieure doit produire une nouvelle version ou une transmission rectificative traçable.

Ne jamais supposer qu'une situation correspond obligatoirement à un seul fichier.

---

# 10. Workflow de validation

Le système doit permettre de configurer différents circuits.

Exemples :

### Circuit simple

Producteur → Destinataire

### Circuit avec validation

Producteur → Responsable → Destinataire

### Circuit hiérarchique

Agent → Chef de section → Chef de division → Directeur → Destinataire

### Circuit personnalisé

Plusieurs validateurs, séquentiels ou parallèles, selon les règles configurées.

## Actions

- Soumettre ;
- Approuver ;
- Rejeter ;
- Demander une correction ;
- Ajouter une observation ;
- Déléguer si autorisé ;
- Consulter l'historique ;
- Reprendre après correction.

Réutiliser le moteur de workflow ou de parapheur existant lorsqu'il est adapté.

Ne pas imposer le même circuit à toutes les obligations.

Prévoir la séparation des responsabilités entre producteur et validateur lorsque le processus l'exige.

---

# 11. Gestion des transmissions

Développer un système de transmission formelle des livrables.

## Destinataires possibles

- Agent ;
- Responsable ;
- Directeur ;
- Directeur Général ;
- Directeur Général Adjoint ;
- Structure ;
- Groupe fonctionnel ;
- Liste de diffusion ;
- Destinataires configurés par obligation.

Les destinataires doivent être résolus depuis l'Annuaire institutionnel, avec prise en compte des affectations et délégations en vigueur.

## Modes de transmission

- Mise à disposition dans E-Trésor ;
- Notification interne ;
- Notification par courrier électronique ;
- Transmission via un circuit institutionnel existant ;
- Autres canaux configurés ultérieurement.

Les notifications externes ne doivent pas exposer les documents confidentiels.

Privilégier un lien sécurisé vers l'application.

## Traçabilité

Conserver :

- Auteur de la transmission ;
- Date et heure ;
- Destinataires effectivement résolus ;
- Fichiers et versions transmis ;
- Canal ;
- Résultat ;
- État de remise ;
- Consultation lorsque techniquement mesurable.

Distinguer transmission réussie, notification envoyée et consultation effective.

Prévoir la retransmission autorisée et la transmission rectificative.

---

# 12. Moteur d'échéances, rappels et escalades

Créer un moteur configurable de gestion des délais.

Chaque obligation doit pouvoir définir :

- Heure limite ;
- Rappels avant échéance ;
- Rappels après échéance ;
- Destinataires des rappels ;
- Escalade hiérarchique ;
- Niveau de priorité ;
- Fréquence des relances ;
- Conditions d'arrêt.

Exemples de règles :

- Rappel 90 minutes avant l'échéance ;
- Rappel 30 minutes avant ;
- Alerte à l'heure limite ;
- Escalade après dépassement ;
- Notification du responsable hiérarchique.

Ces paramètres doivent être configurables dans l'interface d'administration.

Éviter les notifications répétitives et les doublons.

Prévoir une file de notifications et un journal des envois.

---

# 13. Dossiers de production et regroupements

Permettre le regroupement de plusieurs obligations ou occurrences dans un dossier.

Exemples :

- Dossier quotidien de trésorerie ;
- Dossier hebdomadaire de suivi ;
- Dossier mensuel de reporting ;
- Dossier trimestriel de performance ;
- Dossier annuel d'activités ;
- Dossier de préparation d'une réunion ;
- Dossier de suivi d'une directive.

## Fonctionnalités

- Création manuelle ou automatique ;
- Regroupement d'occurrences ;
- Organisation par date ou période ;
- Organisation par structure ;
- Vue consolidée ;
- État de complétude ;
- Documents manquants ;
- Documents en validation ;
- Documents transmis ;
- Consultation sécurisée ;
- Archivage.

Le dossier doit rester une structure de regroupement et ne doit pas dupliquer les fichiers physiques.

Prévoir un système de modèles de dossiers permettant de définir quelles obligations doivent être regroupées.

---

# 14. Tableau de bord « Mon Travail »

Intégrer le module à l'accueil personnalisé de E-Trésor.

L'utilisateur doit retrouver :

- Mes tâches ;
- Mes directives ;
- Mes productions attendues ;
- Mes productions en retard ;
- Mes validations ;
- Mes transmissions ;
- Mes échéances ;
- Mes documents à compléter ;
- Mes notifications ;
- Mes activités terminées.

Prévoir :

- Filtres ;
- Recherche ;
- Tri ;
- Vue liste ;
- Vue calendrier ;
- Vue Kanban si pertinente ;
- Accès direct à chaque activité.

Les données doivent être filtrées selon les habilitations de l'utilisateur.

---

# 15. Tableaux de bord des responsables

Prévoir des tableaux de bord adaptés aux rôles.

## Responsable de structure

- Tâches de sa structure ;
- Productions attendues ;
- Productions reçues ;
- Productions non déposées ;
- Productions en retard ;
- Validations en attente ;
- Activités clôturées ;
- Charge de travail ;
- Historique des transmissions.

## Direction Générale

- Productions attendues ;
- Productions transmises ;
- Productions manquantes ;
- Respect des délais ;
- Dossiers consolidés ;
- Documents reçus ;
- Directives en cours ;
- Tâches critiques ;
- Retards par structure ;
- Historique des productions.

Les indicateurs doivent être calculés sur des données autorisées.

Une structure ne doit pas pouvoir consulter les productions confidentielles d'une autre structure sans habilitation.

---

# 16. Cas d'utilisation initial : situations quotidiennes de trésorerie

Configurer le moteur pour répondre au besoin exprimé par la Direction Générale.

## Structure ACCT

Obligations initiales :

1. Situation des comptes financiers ;
2. Situation des comptes bancaires ;
3. Plan de trésorerie ;
4. Relevés bancaires.

## Structure PGT

Obligations initiales :

1. Situation des dépenses urgentes ;
2. Situation des anticipations de dépenses et de recettes.

## Paramétrage initial proposé

- Fréquence : quotidienne selon le calendrier métier à confirmer ;
- Heure limite : 10 h 00 ;
- Fuseau horaire : Africa/Bamako ;
- Producteurs : agents désignés des structures concernées ;
- Validateurs : responsables désignés ;
- Destinataires : DG et DGA ;
- Livrables : un ou plusieurs fichiers ;
- Formats : configurables ;
- Notifications : automatiques ;
- Archivage : activé ;
- Regroupement : dossier quotidien de trésorerie.

Ces obligations doivent être créées comme des données de configuration et non codées directement dans les contrôleurs ou les services.

Les noms de structures, responsables et destinataires doivent être récupérés dans les référentiels existants.

---

# 17. Autres cas d'utilisation à prendre en charge

Le moteur doit également permettre, sans développement spécifique supplémentaire :

### Exemple A — Rapport hebdomadaire

Une division transmet chaque vendredi son rapport hebdomadaire à son directeur.

### Exemple B — Rapport mensuel

Une direction produit un rapport mensuel nécessitant deux niveaux de validation.

### Exemple C — États financiers trimestriels

Une structure transmet plusieurs fichiers financiers chaque trimestre.

### Exemple D — Rapport annuel d'activités

Une direction prépare un rapport annuel comprenant plusieurs annexes et contributions.

### Exemple E — Compte rendu de réunion

Un agent est chargé de produire et de soumettre un compte rendu dans un délai de 48 heures après une réunion.

### Exemple F — Directive du Directeur Général

Le DG émet une directive impliquant plusieurs structures, chacune disposant de tâches et de livrables différents.

### Exemple G — Demande ponctuelle

Une structure demande à une autre la production exceptionnelle d'un document avant une date déterminée.

### Exemple H — Dossier documentaire périodique

Plusieurs structures contribuent à un dossier consolidé consultable par la Direction Générale.

Ces exemples doivent servir aux tests fonctionnels.

---

# 18. Intégration avec les modules existants

## Annuaire institutionnel

Réutiliser les agents, fonctions, structures, responsables, affectations et délégations.

## Courrier

Permettre de créer une tâche à partir d'un courrier et de rattacher un livrable à un dossier courrier.

## Parapheur

Réutiliser les mécanismes de visa et de validation lorsque nécessaire.

## GED

Stocker et classer les documents selon les règles de conservation et les habilitations.

## ONLYOFFICE

Permettre la création et l'édition de documents à partir de modèles autorisés.

## Réunions

Permettre la génération de tâches à partir des décisions et comptes rendus.

## Notifications

Centraliser les rappels, transmissions, validations et alertes.

## Accueil Intranet

Afficher les activités pertinentes dans « Mon Travail ».

Ne pas créer de nouveaux référentiels utilisateurs, structures, rôles ou documents si des référentiels fiables existent déjà.

---

# 19. Architecture technique attendue

Adapter la conception à l'architecture réellement identifiée dans le dépôt.

Pour une application Laravel, privilégier une organisation claire des responsabilités :

- Models ;
- Form Requests ;
- Policies ;
- Services ;
- Actions ;
- Jobs ;
- Events ;
- Listeners ;
- Notifications ;
- Repositories uniquement si justifiés ;
- API Resources ;
- Controllers ;
- Tests.

Services métier envisagés :

- TaskService ;
- DirectiveService ;
- ProductionDefinitionService ;
- ProductionOccurrenceService ;
- RecurrenceService ;
- DeadlineService ;
- DeliverableService ;
- ValidationService ;
- TransmissionService ;
- ReminderService ;
- ProductionFolderService ;
- ProductionDashboardService.

Ces noms sont indicatifs.

Éviter une architecture artificiellement complexe et respecter les conventions du projet.

## Exigences techniques

- Transactions pour les opérations critiques ;
- Validation serveur ;
- Contrôle d'accès systématique ;
- Gestion des erreurs ;
- Journalisation ;
- Jobs asynchrones ;
- Idempotence ;
- Pagination ;
- Recherche ;
- Filtres ;
- Indexation adaptée ;
- Gestion des fichiers privés ;
- Protection contre les accès directs ;
- Prévention des doublons ;
- Tests automatisés.

Le système doit fonctionner avec l'infrastructure réellement disponible.

Vérifier les contraintes de l'hébergement, du scheduler, des files de jobs et du stockage avant de retenir une solution technique.

---

# 20. Modélisation des données

Proposer une modélisation cohérente, sans créer de tables redondantes.

Entités conceptuelles possibles :

- directives ;
- tasks ;
- task_assignments ;
- task_dependencies ;
- task_comments ;
- production_definitions ;
- production_schedules ;
- production_occurrences ;
- production_deliverables ;
- production_files ;
- production_validation_steps ;
- production_validations ;
- production_transmissions ;
- production_transmission_recipients ;
- production_reminders ;
- production_folders ;
- production_folder_items ;
- production_events ;
- production_exceptions.

Il ne s'agit pas d'une liste obligatoire de tables.

Analyser les modèles existants avant de proposer le schéma définitif.

Prévoir les clés étrangères, index, contraintes d'unicité, dates de validité, historiques et mécanismes de conservation.

Les opérations critiques doivent être protégées contre les exécutions concurrentes.

---

# 21. Sécurité et confidentialité

Les documents de trésorerie peuvent présenter un caractère sensible.

Prévoir :

- Authentification obligatoire ;
- Autorisation par rôle et par objet ;
- Vérification des droits sur chaque fichier ;
- Stockage privé ;
- Téléchargement contrôlé ;
- Liens temporaires si nécessaire ;
- Audit des consultations sensibles ;
- Gestion des délégations ;
- Gestion des habilitations temporaires ;
- Séparation des fonctions ;
- Contrôle des extensions et contenus ;
- Limites de taille ;
- Analyse antivirus si disponible ;
- Prévention des fuites dans les notifications ;
- Protection des journaux contenant des données sensibles.

Une notification ne doit jamais permettre de contourner les permissions de l'application.

Les droits d'accès doivent être vérifiés au moment de chaque consultation.

---

# 22. Interface utilisateur

Concevoir une interface professionnelle, moderne, cohérente avec E-Trésor et adaptée aux usages institutionnels.

## Navigation suggérée

**Tâches & Directives**

- Tableau de bord ;
- Mon Travail ;
- Directives ;
- Tâches ;
- Productions & Transmissions ;
- Productions attendues ;
- Mes validations ;
- Dossiers de production ;
- Historique ;
- Rapports et statistiques ;
- Administration des obligations.

## Écrans attendus

1. Liste des directives ;
2. Création et détail d'une directive ;
3. Liste des tâches ;
4. Création et détail d'une tâche ;
5. Mes activités ;
6. Calendrier des échéances ;
7. Référentiel des obligations ;
8. Assistant de création d'une obligation ;
9. Liste des occurrences ;
10. Détail d'une occurrence ;
11. Dépôt multi-fichiers ;
12. Validation d'une production ;
13. Transmission ;
14. Dossier consolidé ;
15. Tableau de bord responsable ;
16. Tableau de bord Direction Générale ;
17. Historique et audit ;
18. Paramétrage des rappels et échéances.

L'assistant de création d'une obligation doit être simple et guidé :

1. Informations générales ;
2. Responsables et participants ;
3. Périodicité ;
4. Livrables attendus ;
5. Validation ;
6. Destinataires et transmission ;
7. Rappels et escalades ;
8. Confidentialité ;
9. Récapitulatif et activation.

Prévoir les états vides, les erreurs, les chargements, les confirmations et les contrôles d'accès dans l'interface.

---

# 23. API et interopérabilité

Prévoir des API cohérentes avec le projet pour :

- Directives ;
- Tâches ;
- Obligations ;
- Occurrences ;
- Livrables ;
- Validations ;
- Transmissions ;
- Dossiers ;
- Tableaux de bord ;
- Paramètres.

Les API doivent être sécurisées et documentées.

Prévoir une architecture permettant ultérieurement :

- La récupération automatique de documents depuis des applications métiers ;
- L'intégration avec des systèmes comptables ;
- L'import depuis des répertoires ou services autorisés ;
- L'intégration avec des services de messagerie ;
- L'alimentation de tableaux de bord décisionnels.

Ne pas implémenter d'intégration externe fictive.

---

# 24. Règles métier essentielles

Respecter impérativement les règles suivantes :

1. Une obligation peut générer plusieurs occurrences.
2. Une occurrence appartient à une obligation identifiable.
3. Une occurrence peut comporter plusieurs fichiers.
4. Une tâche peut exister sans obligation périodique.
5. Une obligation peut être ponctuelle ou récurrente.
6. Une obligation peut nécessiter ou non une validation.
7. Une transmission doit respecter les règles de validation configurées.
8. Une transmission doit être traçable.
9. Une correction après transmission ne doit pas écraser l'historique.
10. Une obligation inactive ne génère plus d'occurrences.
11. Une modification de configuration ne doit pas altérer silencieusement les occurrences historiques.
12. Les droits de consultation dépendent des habilitations.
13. Les échéances doivent respecter les calendriers configurés.
14. Les rappels doivent être idempotents.
15. Les dossiers consolidés doivent référencer les documents sans duplication inutile.
16. Les documents ne doivent pas être accessibles par URL publique non protégée.
17. Les tableaux de bord doivent respecter les permissions.
18. Les utilisateurs doivent pouvoir justifier un retard ou une impossibilité de production.
19. La clôture doit respecter les règles de complétude.
20. Toute opération sensible doit être historisée.

---

# 25. Tests et critères de recette

Préparer des tests unitaires, fonctionnels et d'intégration.

Scénarios obligatoires :

- Création d'une tâche ponctuelle ;
- Création d'une directive avec plusieurs tâches ;
- Création d'une obligation quotidienne ;
- Création d'une obligation mensuelle ;
- Génération automatique d'une occurrence ;
- Absence de doublon après deux exécutions du scheduler ;
- Exclusion d'un jour férié ;
- Dépôt de plusieurs fichiers ;
- Rejet d'un format interdit ;
- Validation hiérarchique ;
- Rejet et correction ;
- Transmission à plusieurs destinataires ;
- Transmission rectificative ;
- Rappel avant échéance ;
- Détection d'un retard ;
- Escalade ;
- Regroupement de plusieurs productions ;
- Consultation par le DG ;
- Refus d'accès à un utilisateur non habilité ;
- Conservation des versions ;
- Désactivation d'une obligation ;
- Changement de responsable ;
- Gestion d'une délégation ;
- Reprise après échec d'un job ;
- Production ponctuelle exceptionnelle ;
- Production annuelle avec plusieurs annexes.

Tester également les six obligations ACCT/PGT comme cas d'usage initial.

---

# 26. Livrables attendus de l'agent

Avant de commencer l'implémentation, produire :

## Livrable 1 — Audit de l'existant

Présenter les modules réutilisables, les contraintes techniques, les risques et les points d'intégration.

## Livrable 2 — Analyse fonctionnelle

Décrire les acteurs, processus, cas d'utilisation, règles métier et workflows.

## Livrable 3 — Architecture proposée

Présenter les composants, responsabilités, événements et interactions.

## Livrable 4 — Modèle de données

Proposer les entités, relations, contraintes et migrations nécessaires.

## Livrable 5 — Plan d'implémentation

Découper le développement en étapes indépendantes, vérifiables et testables.

## Livrable 6 — Implémentation

Développer les fonctionnalités validées en respectant l'existant.

## Livrable 7 — Tests

Produire les tests et démontrer les scénarios de recette.

## Livrable 8 — Documentation

Documenter l'administration des obligations, les workflows, les règles de récurrence, les transmissions et les procédures d'exploitation.

---

# 27. Stratégie d'implémentation

Procéder de manière progressive.

### Étape 1 — Audit

Analyser le dépôt, les modules et les composants existants.

### Étape 2 — Architecture

Proposer une architecture et une modélisation sans modifier le code.

### Étape 3 — Socle transversal

Mettre en place les composants nécessaires à la gestion des tâches, directives et affectations.

### Étape 4 — Obligations de production

Développer le référentiel configurable des obligations.

### Étape 5 — Moteur de récurrence

Développer la génération des occurrences et les calendriers.

### Étape 6 — Livrables

Développer le dépôt multi-fichiers, le versionnement et l'intégration documentaire.

### Étape 7 — Validation et transmission

Intégrer les workflows et la transmission sécurisée.

### Étape 8 — Échéances et notifications

Développer les rappels, retards et escalades.

### Étape 9 — Tableaux de bord

Développer les interfaces agents, responsables et Direction Générale.

### Étape 10 — Configuration initiale

Configurer les obligations ACCT/PGT et les autres exemples de recette.

### Étape 11 — Tests et stabilisation

Effectuer les tests, corriger les anomalies et documenter la solution.

Ne pas effectuer de refonte générale de E-Trésor.

Ne pas modifier les fonctionnalités existantes sans justification et sans contrôle des régressions.

Présenter les choix structurants avant toute migration ou modification importante.

---

# 28. Résultat final attendu

À l'issue du développement, E-Trésor doit permettre à un administrateur habilité de créer une nouvelle obligation documentaire, de définir ses responsables, sa périodicité, ses livrables, ses validateurs, ses destinataires et ses échéances, **sans aucune modification du code source**.

Le système doit ensuite assurer automatiquement :

- La génération des activités attendues ;
- L'affectation aux responsables ;
- Le suivi des échéances ;
- La réception des fichiers ;
- La validation ;
- La transmission ;
- Les notifications ;
- L'archivage ;
- La traçabilité ;
- La production d'indicateurs.

Le module doit être utilisable aussi bien pour les situations quotidiennes de trésorerie que pour les rapports administratifs, financiers, techniques ou institutionnels.

**Principe directeur : configurer les processus plutôt que coder des cas particuliers.**

---

# 29. Consigne finale à l'agent

Commence maintenant par analyser l'application E-Trésor existante.

Présente-moi :

1. Ton diagnostic de l'architecture actuelle ;
2. Les modules et composants réutilisables ;
3. Les éventuels conflits avec les fonctionnalités existantes ;
4. L'architecture fonctionnelle et technique recommandée ;
5. Le modèle de données proposé ;
6. Les workflows principaux ;
7. Le découpage du développement en tâches ordonnées ;
8. Les questions métier réellement bloquantes.

**Attends ma validation de cette analyse avant de commencer les modifications du code.**

Ne limite jamais ta conception aux six situations ACCT/PGT : elles constituent uniquement le premier cas d'utilisation du moteur générique de productions et transmissions.