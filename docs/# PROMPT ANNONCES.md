# PROMPT MAÎTRE — E-TRÉSOR
## Conception et implémentation du module « Annonces & Communications internes »

### 1. Rôle et mission

Tu interviens en qualité d'architecte logiciel senior, analyste fonctionnel, expert Laravel, Vue.js, gestion des communications institutionnelles, sécurité applicative et expérience utilisateur.

Tu travailles sur **E-Trésor**, plateforme intranet de la Direction Générale du Trésor et de la Comptabilité Publique (DGTCP) du Mali.

Ta mission consiste à analyser l'application existante, puis à concevoir et implémenter un module professionnel intitulé :

**« Annonces & Communications internes »**

Le module doit permettre la création, la validation, la publication, la diffusion ciblée, la consultation et le suivi des annonces institutionnelles internes.

**Consigne impérative : commence par auditer l'existant avant toute modification du code.**

Identifie les modules déjà disponibles : authentification, annuaire, structures, rôles, notifications, GED, courrier, parapheur, tâches, réunions et accueil intranet.

Réutilise les services existants. Ne développe pas un deuxième système de notifications, de gestion documentaire ou de gestion des utilisateurs.

---

# 2. Contexte et objectifs

La DGTCP dispose déjà d'un site web institutionnel consacré à la communication publique.

E-Trésor doit donc répondre à un besoin différent : faciliter la circulation des informations internes, administratives et opérationnelles.

Le module doit notamment permettre :

- La diffusion des annonces de la Direction Générale ;
- La communication interne entre structures ;
- La publication des informations de service ;
- La diffusion des alertes et annonces urgentes ;
- La communication sur les réunions, formations et événements internes ;
- La diffusion des changements organisationnels ;
- La publication des informations relatives aux applications et services numériques ;
- La diffusion ciblée selon les directions, services, fonctions et groupes ;
- La confirmation de lecture des communications importantes ;
- La consultation des archives des annonces.

Le module ne doit pas devenir un réseau social, un forum généraliste ou une copie du site web public.

---

# 3. Architecture fonctionnelle

Le module doit être organisé autour de six composantes :

1. Référentiel des annonces ;
2. Création et édition ;
3. Validation et publication ;
4. Ciblage et diffusion ;
5. Consultation et accusés de lecture ;
6. Administration, suivi et statistiques.

Prévoir une interface simple pour les agents et une interface de gestion adaptée aux responsables de publication.

---

# 4. Typologie des annonces

Mettre en place un référentiel configurable de catégories.

Exemples :

- Annonce de la Direction Générale ;
- Information administrative ;
- Note d'information ;
- Communication de service ;
- Alerte opérationnelle ;
- Maintenance informatique ;
- Indisponibilité d'une application ;
- Réunion ou événement ;
- Formation ;
- Changement organisationnel ;
- Information RH non confidentielle ;
- Consigne de sécurité ;
- Rappel institutionnel ;
- Autre catégorie configurable.

Les catégories ne doivent pas être codées en dur.

Chaque catégorie peut disposer de règles de validation, de diffusion et de conservation.

---

# 5. Création d'une annonce

Chaque annonce doit comporter :

- Référence unique ;
- Titre ;
- Résumé facultatif ;
- Contenu détaillé ;
- Catégorie ;
- Structure émettrice ;
- Auteur ;
- Responsable de publication ;
- Niveau d'importance ;
- Niveau de confidentialité ;
- Date de création ;
- Date de publication ;
- Date d'expiration ;
- Destinataires ;
- Image illustrative facultative ;
- Pièces jointes ;
- Liens internes ;
- Statut ;
- Paramètres de lecture obligatoire ;
- Historique.

Prévoir un éditeur de contenu enrichi permettant les titres, paragraphes, listes, liens et tableaux simples.

Sécuriser le contenu HTML afin d'éviter les injections de scripts.

Prévoir l'enregistrement en brouillon et l'aperçu avant publication.

---

# 6. Niveaux d'importance

Prévoir les niveaux suivants, configurables :

- Information ;
- Normal ;
- Important ;
- Urgent ;
- Critique.

Ces niveaux doivent influencer l'affichage et les notifications selon des règles explicites.

Une annonce urgente ne doit pas automatiquement être envoyée à tous les agents.

L'importance et le public destinataire sont deux notions distinctes.

---

# 7. Ciblage des destinataires

Le ciblage est une fonctionnalité essentielle.

Permettre la diffusion :

- À tous les agents de la DGTCP ;
- À une ou plusieurs directions ;
- À une division ;
- À une section ;
- À un service ;
- À une structure déconcentrée ;
- À une fonction ;
- À un groupe institutionnel ;
- À une liste d'agents ;
- À un agent particulier ;
- À des groupes dynamiques, selon les possibilités de l'Annuaire.

Les destinataires doivent être sélectionnés depuis l'Annuaire institutionnel.

Prévoir les règles d'inclusion et d'exclusion, ainsi que la prévisualisation de l'audience avant publication.

Les annonces ciblées ne doivent pas apparaître dans les recherches, les API ou les notifications des utilisateurs non autorisés.

Les destinataires effectivement concernés au moment de la publication doivent être historisés lorsque la traçabilité l'exige.

---

# 8. Gestion des annonces confidentielles

Distinguer :

- Annonce générale ;
- Annonce interne ciblée ;
- Annonce restreinte ;
- Communication confidentielle.

Les communications confidentielles doivent respecter les habilitations définies.

Ne jamais utiliser les annonces comme un moyen de contourner les workflows du courrier confidentiel ou du parapheur.

Lorsqu'une communication constitue un acte administratif formel nécessitant visa, signature, enregistrement ou preuve de notification, elle doit être traitée par le module institutionnel approprié.

Le module Annonces peut alors diffuser un avis et un lien sécurisé vers le document officiel.

---

# 9. Workflow de validation et publication

Prévoir des circuits configurables.

### Circuit simple

Auteur habilité → Publication.

### Circuit avec validation

Rédacteur → Responsable de structure → Publication.

### Circuit institutionnel

Rédacteur → Responsable de communication → Autorité habilitée → Publication.

Actions possibles :

- Enregistrer ;
- Soumettre ;
- Approuver ;
- Rejeter ;
- Demander une correction ;
- Programmer ;
- Publier ;
- Dépublier ;
- Archiver ;
- Annuler.

Statuts proposés :

- Brouillon ;
- En validation ;
- À corriger ;
- Approuvée ;
- Programmée ;
- Publiée ;
- Expirée ;
- Archivée ;
- Rejetée ;
- Annulée.

Une annonce ne doit pas être modifiée silencieusement après publication.

Prévoir un historique des révisions et, pour les changements substantiels, une nouvelle validation selon la politique configurée.

Réutiliser le moteur de workflow existant lorsque possible.

---

# 10. Publication programmée

Permettre :

- La publication immédiate ;
- La publication différée ;
- La définition d'une date d'expiration ;
- La dépublication manuelle ;
- La prolongation autorisée ;
- La programmation de rappels.

Les tâches automatiques doivent être idempotentes.

Une annonce programmée ne doit pas être visible avant sa date de publication, sauf aux personnes habilitées à la gérer.

Le fuseau horaire par défaut doit être Africa/Bamako.

---

# 11. Affichage sur l'accueil intranet

Intégrer les annonces à l'accueil personnalisé de E-Trésor.

Prévoir plusieurs modes d'affichage :

### Bandeau prioritaire

Pour les communications critiques ou urgentes.

### Bloc « Annonces importantes »

Pour les annonces prioritaires non expirées.

### Fil des communications internes

Pour les annonces récentes destinées à l'utilisateur.

### Espace « Toutes les annonces »

Pour la consultation complète, avec filtres et recherche.

Éviter les bandeaux permanents trop nombreux.

Ne pas encombrer l'accueil avec des informations anciennes ou non pertinentes.

L'affichage doit tenir compte de l'importance, de la date, du ciblage et des droits d'accès.

---

# 12. Accusés de lecture

Certaines annonces doivent pouvoir exiger une confirmation explicite de lecture.

Prévoir deux mécanismes distincts :

### Consultation

Enregistrement de l'ouverture de l'annonce, lorsque cela est autorisé.

### Accusé de lecture

Action volontaire par laquelle l'agent confirme avoir pris connaissance de l'annonce.

Une simple ouverture de page ne doit pas être assimilée à un accusé de lecture explicite.

Informations à conserver :

- Annonce ;
- Agent ;
- Date de consultation ;
- Date de confirmation ;
- Version consultée ;
- Statut de confirmation.

Prévoir :

- Liste des agents concernés ;
- Nombre de confirmations ;
- Taux de confirmation ;
- Relances ciblées ;
- Historique ;
- Export autorisé.

Les accusés de lecture ne doivent pas être présentés comme une signature électronique ou une preuve juridique de notification sans validation du cadre applicable.

---

# 13. Notifications

Réutiliser le système central de notifications de E-Trésor.

Canaux envisageables :

- Notification dans l'application ;
- Courrier électronique professionnel ;
- WhatsApp professionnel si autorisé ;
- Autres canaux ultérieurs.

Prévoir des règles selon :

- Importance ;
- Catégorie ;
- Audience ;
- Date de publication ;
- Lecture obligatoire ;
- Préférences autorisées.

Pour les annonces sensibles, les notifications externes doivent contenir uniquement les informations minimales et un lien sécurisé.

Éviter les envois massifs non contrôlés.

Prévoir une file de traitement, la gestion des échecs et la prévention des doublons.

---

# 14. Pièces jointes et GED

Permettre :

- Le dépôt de plusieurs fichiers ;
- Le rattachement d'un document GED ;
- La prévisualisation autorisée ;
- Le téléchargement sécurisé ;
- Le versionnement ;
- La gestion des droits ;
- L'archivage.

Éviter de dupliquer physiquement un document déjà présent dans la GED.

Une annonce peut renvoyer vers une note de service, un document de référence, un programme de formation ou une procédure institutionnelle.

Les droits de consultation du document doivent être contrôlés indépendamment des droits de lecture de l'annonce.

---

# 15. Recherche et consultation

Prévoir :

- Recherche par mots-clés ;
- Filtre par catégorie ;
- Filtre par structure ;
- Filtre par importance ;
- Filtre par période ;
- Filtre par statut ;
- Filtre par annonces non lues ;
- Filtre par lecture obligatoire ;
- Tri chronologique ;
- Pagination ;
- Archives.

Les résultats doivent respecter les droits de visibilité.

Une annonce expirée peut rester consultable dans les archives si les règles de conservation et d'habilitation le permettent.

---

# 16. Interactions avec les annonces

Ne pas développer un réseau social.

Prévoir, selon les besoins :

- Marquer comme lu ;
- Confirmer la lecture ;
- Ajouter aux favoris ;
- Copier un lien interne sécurisé ;
- Télécharger les pièces jointes ;
- Consulter les documents associés.

Les commentaires publics ne doivent pas être activés par défaut.

Si des retours sont nécessaires, prévoir un mécanisme contrôlé ou une orientation vers le module Tâches, Ticketing ou Courrier.

---

# 17. Statistiques et pilotage

Prévoir des indicateurs :

- Nombre d'annonces publiées ;
- Répartition par catégorie ;
- Répartition par structure ;
- Annonces actives ;
- Annonces expirées ;
- Annonces urgentes ;
- Nombre de destinataires ;
- Consultations ;
- Confirmations de lecture ;
- Taux de confirmation ;
- Annonces en attente de validation ;
- Délais de publication.

Prévoir des tableaux de bord adaptés :

- Administrateur ;
- Responsable de communication ;
- Responsable de structure ;
- Direction Générale.

Les indicateurs doivent respecter les habilitations et éviter d'exposer inutilement des informations nominatives.

---

# 18. Permissions et rôles

Prévoir des permissions granulaires :

- announcement.view ;
- announcement.create ;
- announcement.update ;
- announcement.submit ;
- announcement.approve ;
- announcement.reject ;
- announcement.publish ;
- announcement.schedule ;
- announcement.unpublish ;
- announcement.archive ;
- announcement.manage_categories ;
- announcement.manage_audiences ;
- announcement.view_statistics ;
- announcement.view_read_receipts ;
- announcement.export ;
- announcement.administer.

Adapter les noms aux conventions existantes.

Distinguer le rôle de rédacteur, de validateur, de publieur et d'administrateur.

L'administration technique ne doit pas donner automatiquement accès au contenu confidentiel.

Utiliser des Policies et des vérifications serveur sur chaque action.

---

# 19. Modèle de données

Proposer une modélisation normalisée.

Entités conceptuelles possibles :

- announcements ;
- announcement_categories ;
- announcement_revisions ;
- announcement_audiences ;
- announcement_recipients ;
- announcement_attachments ;
- announcement_approvals ;
- announcement_reads ;
- announcement_acknowledgements ;
- announcement_publications ;
- announcement_events.

Adapter le schéma aux tables et services déjà présents.

Prévoir :

- Index ;
- Contraintes ;
- Clés étrangères ;
- Historique ;
- Suppression logique si nécessaire ;
- Dates de publication ;
- Dates d'expiration ;
- Gestion des versions ;
- Audit.

Ne pas créer de doublons avec les modèles de documents, notifications et utilisateurs existants.

---

# 20. Architecture technique

Respecter l'architecture réelle du projet.

Pour Laravel, prévoir si nécessaire :

- AnnouncementController ;
- AnnouncementCategoryController ;
- AnnouncementPolicy ;
- AnnouncementService ;
- AnnouncementAudienceResolver ;
- AnnouncementPublicationService ;
- AnnouncementReadService ;
- AnnouncementNotificationService ;
- PublishScheduledAnnouncementsJob ;
- ExpireAnnouncementsJob ;
- Form Requests ;
- API Resources ;
- Tests.

Ces noms sont indicatifs.

Utiliser des transactions pour les publications critiques.

Garantir l'idempotence des publications programmées et des notifications.

Prévoir la journalisation des erreurs et la reprise des traitements.

---

# 21. Interface utilisateur

Créer une interface moderne, institutionnelle et cohérente avec le design de E-Trésor.

### Menu proposé

**Annonces**

- Fil d'actualités internes ;
- Annonces importantes ;
- Mes annonces ;
- Annonces à confirmer ;
- Toutes les annonces ;
- Archives ;
- Gestion des annonces ;
- Catégories ;
- Statistiques.

Les menus de gestion ne doivent être visibles que pour les utilisateurs habilités.

### Écrans attendus

1. Fil des annonces ;
2. Détail d'une annonce ;
3. Création d'une annonce ;
4. Aperçu avant publication ;
5. Gestion des brouillons ;
6. Validation ;
7. Publication programmée ;
8. Sélection des destinataires ;
9. Gestion des accusés de lecture ;
10. Historique ;
11. Statistiques ;
12. Administration des catégories.

Prévoir des interfaces responsive, les états vides, les messages d'erreur et les confirmations.

---

# 22. Scénarios fonctionnels à tester

### Scénario 1

Le Cabinet du DG publie une annonce destinée à l'ensemble des agents.

### Scénario 2

La DSI annonce une maintenance programmée de E-Trésor à une population ciblée.

### Scénario 3

Une direction publie une annonce visible uniquement par ses agents.

### Scénario 4

La Direction Générale publie une communication urgente nécessitant un accusé de lecture.

### Scénario 5

Une annonce est programmée pour une date ultérieure.

### Scénario 6

Une annonce expire et disparaît de l'accueil sans être supprimée de l'historique.

### Scénario 7

Un agent non habilité tente d'accéder à une annonce confidentielle par URL directe.

### Scénario 8

Une annonce est rejetée pendant la validation, puis corrigée et soumise de nouveau.

### Scénario 9

Une annonce comporte plusieurs pièces jointes issues de la GED.

### Scénario 10

Un responsable consulte les taux de confirmation des annonces relevant de son périmètre.

### Scénario 11

Une annonce déjà publiée fait l'objet d'une correction substantielle avec historisation.

### Scénario 12

Une annonce est envoyée à une structure dont les agents ont changé d'affectation.

Tester la cohérence du ciblage et des destinataires historiques.

---

# 23. Exigences non fonctionnelles

- Sécurité des accès ;
- Confidentialité ;
- Performance ;
- Pagination ;
- Compatibilité mobile ;
- Accessibilité ;
- Journalisation ;
- Résilience des notifications ;
- Maintenabilité ;
- Tests automatisés ;
- Prévention des doublons ;
- Traçabilité ;
- Conservation des historiques ;
- Compatibilité avec l'infrastructure de production.

Ne pas ajouter de dépendances importantes sans justification.

---

# 24. Priorisation

## Phase 1 — MVP

- Catégories ;
- Création d'annonces ;
- Ciblage par structure et agents ;
- Publication ;
- Affichage sur l'accueil ;
- Dates de publication et d'expiration ;
- Pièces jointes ;
- Notifications internes ;
- Permissions ;
- Historique essentiel.

## Phase 2 — Gestion avancée

- Workflows de validation ;
- Publication programmée ;
- Accusés de lecture ;
- Relances ;
- Statistiques ;
- Modèles d'annonces ;
- Intégration GED renforcée ;
- Gestion avancée des audiences.

## Phase 3 — Optimisation

- Intégration multicanale ;
- Modèles de communication récurrents ;
- Analyses de portée ;
- Assistance IA pour la rédaction et la classification, sous contrôle humain ;
- Intégration avancée avec les autres modules.

---

# 25. Livrables attendus

Produire successivement :

1. Audit technique et fonctionnel de l'existant ;
2. Cartographie des composants réutilisables ;
3. Spécifications fonctionnelles ;
4. Architecture technique ;
5. Modèle de données ;
6. Matrice des rôles et permissions ;
7. Workflows ;
8. Maquettes ou description détaillée des écrans ;
9. Plan d'implémentation ;
10. Code source ;
11. Tests automatisés ;
12. Documentation d'administration et d'utilisation.

---

# 26. Consigne finale

Commence par analyser le projet E-Trésor existant.

Présente-moi :

1. Les fonctionnalités déjà disponibles ;
2. Les composants à réutiliser ;
3. Les risques de duplication ;
4. L'architecture proposée ;
5. Le schéma de données ;
6. Le workflow de publication ;
7. La stratégie de ciblage ;
8. Les écrans à développer ;
9. Le plan d'implémentation par étapes.

**Attends ma validation avant de modifier le code.**

Le résultat attendu est un module d'annonces institutionnelles internes, sécurisé, ciblé, traçable, ergonomique et parfaitement intégré à E-Trésor.

Il doit répondre aux besoins de communication de la DGTCP sans reproduire les fonctionnalités du site web public.

## Exigence prioritaire — Bandeau horizontal des annonces

Le module « Annonces & Communications internes » doit intégrer un **bandeau d'annonces horizontal immédiatement sous le menu principal de E-Trésor**.

### Positionnement

- Le bandeau doit apparaître sous le menu principal et avant le contenu des pages.
- Il doit être intégré au layout global de l'application et non uniquement à la page d'accueil.
- Il doit être visible dans les différents modules : Courrier, Parapheur, GED, Tâches, Réunions, etc.
- Il doit respecter la responsivité sur ordinateur, tablette et mobile.
- Si aucune annonce active ne concerne l'utilisateur, masquer automatiquement le bandeau sans laisser d'espace vide.

### Fonctionnement

- Afficher les annonces actives et autorisées pour l'utilisateur connecté.
- Prévoir un défilement horizontal automatique, configurable et non agressif.
- Permettre le défilement manuel avec des flèches.
- Suspendre le défilement lors du survol, du focus clavier ou d'une interaction.
- Permettre de cliquer sur une annonce pour afficher son contenu complet dans une fenêtre adaptée ou sur sa page de détail.
- Afficher éventuellement une icône, un libellé de catégorie et un indicateur d'importance.
- Afficher en priorité les annonces urgentes et importantes.
- Prévoir un lien « Toutes les annonces ».
- Permettre à l'utilisateur de masquer temporairement une annonce non obligatoire.
- Ne pas autoriser la fermeture définitive des annonces à lecture obligatoire avant leur confirmation explicite, sans empêcher la navigation dans l'application.
- Respecter les préférences d'accessibilité, notamment la réduction des animations.

### Administration

Ajouter aux paramètres des annonces :

- Afficher dans le bandeau : Oui / Non ;
- Ordre de priorité ;
- Date de début d'affichage ;
- Date de fin d'affichage ;
- Couleur ou style selon l'importance ;
- Défilement activé ou désactivé ;
- Durée d'affichage ;
- Fermeture autorisée ou non ;
- Lecture obligatoire ou facultative.

### Architecture

Créer un composant réutilisable, par exemple `AnnouncementBanner.vue`, intégré au layout principal.

Le composant doit récupérer uniquement les annonces actives, publiées, non expirées et accessibles à l'utilisateur connecté.

Réutiliser l'API, les permissions et les services du module Annonces. Éviter les appels API répétés à chaque changement de page et gérer correctement l'invalidation du cache.

Le bandeau doit rester léger et ne pas ralentir le chargement de E-Trésor.

**Résultat attendu :** un bandeau institutionnel discret, professionnel, animé avec modération, permettant de diffuser les communications internes importantes sans perturber le travail quotidien des agents.