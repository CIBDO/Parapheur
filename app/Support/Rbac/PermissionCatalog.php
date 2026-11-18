<?php

namespace App\Support\Rbac;

use App\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Source de vérité déclarative pour permissions (codes stables) et matrices de rôles.
 */
class PermissionCatalog
{
    /** @return list<string> */
    public static function permissionNames(): array
    {
        return array_keys(self::permissions());
    }

    /**
     * @return array<string, array{label: string, module: string, is_system?: bool, description?: string}>
     */
    public static function permissions(): array
    {
        $defs = [];

        $add = function (string $module, array $items, bool $system = false) use (&$defs): void {
            foreach ($items as $name => $label) {
                if (is_int($name)) {
                    $name = $label;
                    $label = self::defaultLabel($name);
                }
                $defs[$name] = [
                    'label' => $label,
                    'module' => $module,
                    'is_system' => $system || in_array($name, self::systemPermissionNames(), true),
                ];
            }
        };

        $add('admin', [
            'admin.access' => 'Accès administration',
        ], true);

        $add('documents', [
            'documents.create' => 'Créer des documents',
            'documents.act' => 'Traiter des documents',
            'documents.vise' => 'Viser des documents',
            'documents.validate' => 'Valider des documents',
        ], true);

        $add('dashboard', [
            'dashboard.dg' => 'Tableau de bord DG',
            'dashboard.direction' => 'Tableau de bord direction',
        ]);

        $add('task', [
            'task.view' => 'Voir les tâches',
            'task.create' => 'Créer une tâche',
            'task.update' => 'Modifier une tâche',
            'task.assign' => 'Imputer une tâche',
            'task.reassign' => 'Réaffecter une tâche',
            'task.take_charge' => 'Prendre en charge une tâche',
            'task.comment' => 'Commenter une tâche',
            'task.complete' => 'Terminer une tâche',
            'task.validate' => 'Valider une tâche',
            'task.return' => 'Retourner une tâche',
            'task.cancel' => 'Annuler une tâche',
            'task.view_team' => 'Voir les tâches du périmètre org (équipe / hiérarchie)',
            'task.view_all' => 'Voir toutes les tâches (plateforme)',
            'task.view_reports' => 'Rapports tâches',
            'task.manage' => 'Administrer les tâches',
            'task.audit.view' => 'Audit des tâches',
        ]);

        $add('instruction', [
            'instruction.view' => 'Voir les instructions',
            'instruction.create' => 'Créer une instruction',
            'instruction.assign' => 'Affecter une instruction',
            'instruction.update' => 'Modifier une instruction',
            'instruction.close' => 'Clôturer une instruction',
            'instruction.cancel' => 'Annuler une instruction',
            'instructions.manage' => 'Gérer les instructions',
        ]);

        $add('ged', [
            'ged.view' => 'Consulter la GED',
            'ged.search' => 'Rechercher en GED',
            'ged.create' => 'Créer en GED',
            'ged.update' => 'Modifier en GED',
            'ged.upload' => 'Téléverser en GED',
            'ged.download' => 'Télécharger depuis la GED',
            'ged.comment' => 'Commenter en GED',
            'ged.create_version' => 'Créer une version GED',
            'ged.classify' => 'Classer en GED',
            'ged.archive' => 'Archiver en GED',
            'ged.restore' => 'Restaurer en GED',
            'ged.share' => 'Partager en GED',
            'ged.manage_metadata' => 'Gérer les métadonnées GED',
            'ged.manage_classification' => 'Gérer la classification GED',
            'ged.manage_types' => 'Gérer les types GED',
            'ged.manage_categories' => 'Gérer les catégories GED',
            'ged.manage_domains' => 'Gérer les domaines GED',
            'ged.manage_folders' => 'Gérer les dossiers GED',
            'ged.validate' => 'Valider en GED',
            'ged.publish' => 'Publier en GED',
            'ged.manage_workflows' => 'Gérer les workflows GED',
            'ged.view_audit' => 'Audit GED',
            'ged.manage_retention' => 'Gérer la rétention GED',
        ]);

        $add('archives', [
            'archives.consulter' => 'Consulter les archives institutionnelles',
            'archives.rechercher' => 'Rechercher dans les archives',
            'archives.verser' => 'Verser aux archives',
            'archives.valider_versement' => 'Valider un versement',
            'archives.rejeter_versement' => 'Rejeter un versement',
            'archives.classer' => 'Classer une archive',
            'archives.telecharger' => 'Télécharger une archive',
            'archives.communiquer' => 'Communiquer une archive',
            'archives.gerer_conservation' => 'Gérer la conservation',
            'archives.proposer_elimination' => 'Proposer une élimination',
            'archives.autoriser_elimination' => 'Autoriser une élimination',
            'archives.executer_elimination' => 'Exécuter une élimination',
            'archives.gerer_magasins' => 'Gérer les magasins',
            'archives.gerer_emplacements' => 'Gérer les emplacements',
            'archives.gerer_boites' => 'Gérer les boîtes',
            'archives.gerer_prets' => 'Gérer les prêts',
            'archives.gerer_recolement' => 'Gérer le récolement',
            'archives.exporter' => 'Exporter des archives',
            'archives.auditer' => 'Auditer les archives',
            'archives.administrer' => 'Administrer les archives',
        ]);

        $add('workspace', [
            'workspace.access' => 'Accéder à Mon espace',
            'workspace.create_shared' => 'Créer un espace partagé',
            'workspace.manage_own' => 'Gérer son espace',
            'workspace.manage_quotas' => 'Gérer les quotas',
            'library.access' => 'Accéder à la bibliothèque',
            'library.manage_own' => 'Gérer sa bibliothèque',
            'library.moderate' => 'Modérer la bibliothèque',
        ]);

        $add('meetings', [
            'meetings.view' => 'Voir les réunions',
            'meetings.create' => 'Créer une réunion',
            'meetings.update' => 'Modifier une réunion',
            'meetings.delete' => 'Supprimer une réunion',
            'meetings.manage' => 'Gérer les réunions',
            'meetings.manage_participants' => 'Gérer les participants',
            'meetings.manage_agenda' => 'Gérer l’ordre du jour',
            'meetings.send_invitations' => 'Envoyer les invitations',
            'meetings.start' => 'Démarrer une réunion',
            'meetings.manage_attendance' => 'Gérer les présences',
            'meetings.take_official_notes' => 'Prendre des notes officielles',
            'meetings.create_decision' => 'Créer une décision',
            'meetings.manage_decisions' => 'Gérer les décisions',
            'meetings.generate_minutes' => 'Générer un procès-verbal',
            'meetings.validate_minutes' => 'Valider un procès-verbal',
            'meetings.close' => 'Clôturer une réunion',
            'meetings.archive' => 'Archiver une réunion',
            'meetings.view_reports' => 'Rapports réunions',
        ]);

        $add('appointments', [
            'appointments.view' => 'Voir les rendez-vous',
            'appointments.create' => 'Créer un rendez-vous',
            'appointments.update' => 'Modifier un rendez-vous',
            'appointments.delete_draft' => 'Supprimer un brouillon RDV',
            'appointments.manage_requests' => 'Gérer les demandes RDV',
            'appointments.propose_slot' => 'Proposer un créneau',
            'appointments.validate' => 'Valider un rendez-vous',
            'appointments.reject' => 'Rejeter un rendez-vous',
            'appointments.reschedule' => 'Reprogrammer un rendez-vous',
            'appointments.cancel' => 'Annuler un rendez-vous',
            'appointments.confirm' => 'Confirmer un rendez-vous',
            'appointments.manage_participants' => 'Gérer les participants RDV',
            'appointments.manage_documents' => 'Gérer les documents RDV',
            'appointments.manage_notes' => 'Gérer les notes RDV',
            'appointments.manage_followups' => 'Gérer les suivis RDV',
            'appointments.view_calendar' => 'Voir le calendrier RDV',
            'appointments.manage_calendar' => 'Gérer le calendrier RDV',
            'appointments.manage_unavailability' => 'Gérer les indisponibilités',
            'appointments.view_reports' => 'Rapports rendez-vous',
            'appointments.archive' => 'Archiver un rendez-vous',
        ]);

        $add('reporting', [
            'reporting.view' => 'Consulter le reporting',
            'delegations.manage' => 'Gérer les délégations',
        ]);

        $add('mail', [
            'mail.view' => 'Voir le courrier',
            'mail.view_all' => 'Voir tout le courrier',
            'mail.view_confidential' => 'Voir le courrier confidentiel',
            'mail.view_very_confidential' => 'Voir le courrier très confidentiel',
            'mail.create' => 'Créer un courrier',
            'mail.update' => 'Modifier un courrier',
            'mail.delete' => 'Supprimer un courrier',
            'mail.assign' => 'Affecter un courrier',
            'mail.process' => 'Traiter un courrier',
            'mail.reply' => 'Répondre à un courrier',
            'mail.dispatch' => 'Expédier un courrier',
            'mail.archive' => 'Archiver un courrier',
            'mail.admin' => 'Administrer le courrier',
            'mail.create_transmission_slip' => 'Créer un bordereau',
            'mail.update_transmission_slip' => 'Modifier un bordereau',
            'mail.delete_transmission_slip' => 'Supprimer un bordereau',
            'mail.validate_transmission_slip' => 'Valider un bordereau',
            'mail.register' => 'Enregistrer un courrier',
            'mail.transmit' => 'Transmettre un courrier',
            'mail.validate' => 'Valider un courrier',
            'mail.sign' => 'Signer un courrier',
            'mail.close' => 'Clôturer un courrier',
            'mail.download' => 'Télécharger un courrier',
            'mail.export' => 'Exporter le courrier',
            'mail.manage_registers' => 'Gérer les registres',
        ]);

        $add('document_template', [
            'document_template.view' => 'Voir les modèles',
            'document_template.view_all' => 'Voir tous les modèles',
            'document_template.create' => 'Créer un modèle',
            'document_template.update' => 'Modifier un modèle',
            'document_template.delete' => 'Supprimer un modèle',
            'document_template.publish' => 'Publier un modèle',
        ]);

        $add('ticket', [
            'ticket.view' => 'Voir les tickets',
            'ticket.create' => 'Créer un ticket',
            'ticket.update' => 'Modifier un ticket',
            'ticket.assign' => 'Affecter un ticket',
            'ticket.reassign' => 'Réaffecter un ticket',
            'ticket.take_charge' => 'Prendre en charge un ticket',
            'ticket.comment' => 'Commenter un ticket',
            'ticket.internal_note' => 'Note interne ticket',
            'ticket.escalate' => 'Escalader un ticket',
            'ticket.resolve' => 'Résoudre un ticket',
            'ticket.close' => 'Clôturer un ticket',
            'ticket.reopen' => 'Rouvrir un ticket',
            'ticket.cancel' => 'Annuler un ticket',
            'ticket.view_all' => 'Voir tous les tickets',
            'ticket.view_team' => 'Voir les tickets de l’équipe',
            'ticket.view_reports' => 'Rapports tickets',
            'ticket.manage_sla' => 'Gérer les SLA',
            'ticket.manage_catalog' => 'Gérer le catalogue tickets',
            'ticket.manage_categories' => 'Gérer les catégories tickets',
            'ticket.admin' => 'Administrer les tickets',
            'ticket.audit.view' => 'Audit tickets',
            'problem.view' => 'Voir les problèmes',
            'problem.create' => 'Créer un problème',
            'problem.update' => 'Modifier un problème',
            'problem.close' => 'Clôturer un problème',
            'knowledge.view' => 'Voir la base de connaissances',
            'knowledge.create' => 'Créer un article',
            'knowledge.review' => 'Réviser un article',
            'knowledge.publish' => 'Publier un article',
        ]);

        $add('production', [
            'production.view' => 'Voir les productions',
            'production.view_all' => 'Voir toutes les productions',
            'production.manage' => 'Gérer les productions',
            'production.validate' => 'Valider une production',
            'production.transmit' => 'Transmettre une production',
        ]);

        $add('announcement', [
            'announcement.view' => 'Voir les annonces',
            'announcement.create' => 'Créer une annonce',
            'announcement.update' => 'Modifier une annonce',
            'announcement.submit' => 'Soumettre une annonce',
            'announcement.approve' => 'Approuver une annonce',
            'announcement.reject' => 'Rejeter une annonce',
            'announcement.publish' => 'Publier une annonce',
            'announcement.schedule' => 'Planifier une annonce',
            'announcement.unpublish' => 'Dépublier une annonce',
            'announcement.archive' => 'Archiver une annonce',
            'announcement.manage_categories' => 'Gérer les catégories d’annonces',
            'announcement.view_statistics' => 'Statistiques annonces',
            'announcement.view_read_receipts' => 'Accusés de lecture',
            'announcement.export' => 'Exporter les annonces',
            'announcement.manage' => 'Administrer les annonces',
        ]);

        $add('portal_app', [
            'portal_app.view' => 'Voir le portail applications',
            'portal_app.manage' => 'Gérer le portail applications',
        ]);

        return $defs;
    }

    /** @return list<string> */
    public static function systemPermissionNames(): array
    {
        return [
            'admin.access',
            'documents.create',
            'documents.act',
        ];
    }

    /**
     * @return array<string, array{name: string, is_system: bool, home_route: string|null, permissions: list<string>|'*'}>
     */
    public static function roles(): array
    {
        $gedBasic = [
            'ged.view', 'ged.search', 'ged.create', 'ged.update', 'ged.upload',
            'ged.download', 'ged.comment', 'ged.create_version', 'ged.classify', 'ged.share',
        ];
        $gedManage = array_merge($gedBasic, [
            'ged.archive', 'ged.restore', 'ged.manage_metadata',
            'ged.manage_classification', 'ged.manage_types', 'ged.manage_categories',
            'ged.manage_domains', 'ged.manage_folders', 'ged.validate', 'ged.publish',
            'ged.manage_workflows', 'ged.view_audit', 'ged.manage_retention',
        ]);

        $archivesBasic = [
            'archives.consulter', 'archives.rechercher', 'archives.verser', 'archives.telecharger',
        ];
        $archivesManage = array_merge($archivesBasic, [
            'archives.valider_versement', 'archives.rejeter_versement', 'archives.classer',
            'archives.communiquer', 'archives.gerer_conservation', 'archives.exporter',
            'archives.auditer', 'archives.administrer',
            'archives.gerer_magasins', 'archives.gerer_emplacements', 'archives.gerer_boites',
            'archives.gerer_prets', 'archives.gerer_recolement',
            'archives.proposer_elimination', 'archives.autoriser_elimination', 'archives.executer_elimination',
        ]);

        $workspaceBasic = [
            'workspace.access', 'workspace.manage_own', 'library.access', 'library.manage_own',
        ];
        $workspaceManage = array_merge($workspaceBasic, [
            'workspace.create_shared', 'workspace.manage_quotas', 'library.moderate',
        ]);

        $mailBasic = [
            'mail.view', 'mail.create', 'mail.process', 'mail.register', 'mail.download',
        ];
        $mailManage = array_merge($mailBasic, [
            'mail.view_all', 'mail.update', 'mail.assign', 'mail.reply', 'mail.dispatch', 'mail.archive',
            'mail.transmit', 'mail.validate', 'mail.sign', 'mail.close', 'mail.export',
            'mail.create_transmission_slip', 'mail.update_transmission_slip', 'mail.validate_transmission_slip',
        ]);
        $mailAdmin = array_merge($mailManage, [
            'mail.view_confidential', 'mail.view_very_confidential', 'mail.delete', 'mail.admin',
            'mail.manage_registers',
            'mail.delete_transmission_slip', 'document_template.view', 'document_template.view_all',
            'document_template.create', 'document_template.update', 'document_template.delete', 'document_template.publish',
        ]);

        $ticketBasic = [
            'ticket.view', 'ticket.create', 'ticket.comment', 'ticket.close', 'ticket.reopen',
        ];
        $ticketAgent = array_merge($ticketBasic, [
            'ticket.update', 'ticket.take_charge', 'ticket.internal_note', 'ticket.resolve',
            'ticket.view_team', 'knowledge.view',
        ]);
        $ticketLead = array_merge($ticketAgent, [
            'ticket.assign', 'ticket.reassign', 'ticket.escalate', 'ticket.cancel',
            'ticket.view_reports', 'problem.view', 'problem.create', 'problem.update',
        ]);
        $ticketAdmin = array_merge($ticketLead, [
            'ticket.view_all', 'ticket.admin', 'ticket.manage_sla', 'ticket.manage_catalog',
            'ticket.manage_categories', 'ticket.audit.view',
            'problem.close', 'knowledge.create', 'knowledge.review', 'knowledge.publish',
        ]);

        $taskBasic = [
            'task.view', 'task.create', 'task.update', 'task.take_charge', 'task.comment',
            'task.complete', 'instruction.view',
        ];
        $taskLead = array_merge($taskBasic, [
            'task.assign', 'task.reassign', 'task.validate', 'task.return', 'task.cancel',
            'task.view_team', 'task.view_reports',
            'instruction.create', 'instruction.assign', 'instruction.update', 'instruction.close',
        ]);
        $taskAdmin = array_merge($taskLead, [
            'task.view_all', 'task.manage', 'task.audit.view', 'instructions.manage', 'instruction.cancel',
        ]);

        $appointmentManage = [
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete_draft',
            'appointments.manage_requests', 'appointments.propose_slot', 'appointments.validate',
            'appointments.reject', 'appointments.reschedule', 'appointments.cancel', 'appointments.confirm',
            'appointments.manage_participants', 'appointments.manage_documents', 'appointments.manage_notes',
            'appointments.manage_followups', 'appointments.view_calendar', 'appointments.manage_calendar',
            'appointments.manage_unavailability', 'appointments.view_reports', 'appointments.archive',
        ];

        $productionBasic = ['production.view', 'production.validate', 'production.transmit'];
        $productionLead = array_merge($productionBasic, ['production.view_all']);
        $productionAdmin = array_merge($productionLead, ['production.manage']);

        $announcementRead = ['announcement.view'];
        $announcementAuthor = array_merge($announcementRead, [
            'announcement.create', 'announcement.update', 'announcement.submit',
        ]);
        $announcementChief = array_merge($announcementAuthor, [
            'announcement.view_statistics', 'announcement.view_read_receipts',
        ]);
        $announcementDirector = array_merge($announcementChief, [
            'announcement.approve', 'announcement.reject', 'announcement.publish',
            'announcement.schedule', 'announcement.unpublish', 'announcement.archive', 'announcement.export',
        ]);
        $announcementSecretariat = array_merge($announcementDirector, [
            'announcement.manage_categories',
        ]);
        $announcementAdmin = array_merge($announcementSecretariat, [
            'announcement.manage',
        ]);

        $portalView = ['portal_app.view'];
        $portalManage = ['portal_app.view', 'portal_app.manage'];

        $merge = fn (array ...$groups): array => array_values(array_unique(array_merge(...$groups)));

        return [
            'admin' => [
                'name' => 'Administrateur',
                'is_system' => true,
                'home_route' => '/parapheur/admin',
                'permissions' => '*',
            ],
            'dg' => [
                'name' => 'Directeur Général',
                'is_system' => true,
                'home_route' => '/parapheur/dg',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view', 'delegations.manage'],
                    $gedManage,
                    $archivesManage,
                    $workspaceManage,
                    $appointmentManage,
                    $mailAdmin,
                    $ticketAdmin,
                    $taskAdmin,
                    $productionAdmin,
                    $announcementAdmin,
                    $portalManage
                ),
            ],
            'dga' => [
                'name' => 'DGA',
                'is_system' => true,
                'home_route' => '/parapheur/dg',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view'],
                    $gedManage,
                    $archivesManage,
                    $workspaceManage,
                    ['appointments.view', 'appointments.create', 'appointments.view_calendar', 'appointments.validate', 'appointments.manage_notes'],
                    $mailManage,
                    $ticketLead,
                    $taskAdmin,
                    $productionAdmin,
                    $announcementAdmin,
                    $portalManage
                ),
            ],
            'conseiller' => [
                'name' => 'Conseiller',
                'is_system' => true,
                'home_route' => '/parapheur',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'reporting.view', 'meetings.view', 'appointments.view', 'appointments.create', 'appointments.view_calendar'],
                    $gedBasic,
                    $archivesBasic,
                    $workspaceBasic,
                    $mailBasic,
                    $ticketBasic,
                    $taskBasic,
                    ['production.view'],
                    $announcementRead,
                    $portalView
                ),
            ],
            'secretariat_dg' => [
                'name' => 'Secrétariat DG',
                'is_system' => true,
                'home_route' => '/parapheur',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'meetings.manage', 'meetings.view', 'meetings.create', 'meetings.take_official_notes', 'meetings.generate_minutes', 'reporting.view'],
                    $gedManage,
                    $archivesManage,
                    $workspaceManage,
                    $appointmentManage,
                    $mailAdmin,
                    $ticketLead,
                    $taskLead,
                    $productionLead,
                    $announcementSecretariat,
                    $portalManage
                ),
            ],
            'directeur' => [
                'name' => 'Directeur',
                'is_system' => true,
                'home_route' => '/parapheur',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.direction', 'reporting.view', 'meetings.manage', 'meetings.view', 'appointments.view', 'appointments.create', 'appointments.view_calendar'],
                    $gedManage,
                    $archivesManage,
                    $workspaceBasic,
                    $mailManage,
                    $ticketLead,
                    $taskLead,
                    $productionLead,
                    $announcementDirector,
                    $portalView
                ),
            ],
            'chef_division' => [
                'name' => 'Chef de division',
                'is_system' => true,
                'home_route' => '/parapheur',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'meetings.view', 'appointments.view', 'appointments.create'],
                    $gedBasic,
                    $archivesBasic,
                    $workspaceBasic,
                    $mailBasic,
                    $ticketAgent,
                    $taskLead,
                    $productionBasic,
                    $announcementChief,
                    $portalView
                ),
            ],
            'chef_section' => [
                'name' => 'Chef de section',
                'is_system' => true,
                'home_route' => '/parapheur',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'meetings.view', 'appointments.view', 'appointments.create'],
                    $gedBasic,
                    $archivesBasic,
                    $workspaceBasic,
                    $mailBasic,
                    $ticketAgent,
                    $taskBasic,
                    $productionBasic,
                    $announcementAuthor,
                    $portalView
                ),
            ],
            'agent' => [
                'name' => 'Agent',
                'is_system' => true,
                'home_route' => '/parapheur',
                'permissions' => $merge(
                    ['documents.create', 'documents.act', 'meetings.view', 'appointments.view', 'appointments.create'],
                    $gedBasic,
                    $archivesBasic,
                    $workspaceBasic,
                    $mailBasic,
                    $ticketAgent,
                    $taskBasic,
                    ['production.view', 'production.transmit'],
                    $announcementRead,
                    $portalView
                ),
            ],
            'lecteur' => [
                'name' => 'Lecteur',
                'is_system' => true,
                'home_route' => '/parapheur',
                'permissions' => $merge(
                    ['meetings.view', 'appointments.view', 'appointments.view_calendar'],
                    ['ged.view', 'ged.search', 'ged.download'],
                    ['archives.consulter', 'archives.rechercher'],
                    ['workspace.access', 'library.access'],
                    ['mail.view'],
                    ['ticket.view', 'knowledge.view'],
                    ['task.view', 'instruction.view'],
                    ['production.view'],
                    $announcementRead,
                    $portalView
                ),
            ],
        ];
    }

    public static function sync(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::permissions() as $name => $meta) {
            $permission = Permission::findOrCreate($name, 'web');
            $permission->fill([
                'label' => $meta['label'],
                'module' => $meta['module'],
                'description' => $meta['description'] ?? null,
                'is_system' => (bool) ($meta['is_system'] ?? false),
            ])->save();
        }

        $allNames = self::permissionNames();

        foreach (self::roles() as $code => $roleDef) {
            $role = Role::findOrCreate($roleDef['name'], 'web');
            $role->fill([
                'code' => $code,
                'is_system' => (bool) ($roleDef['is_system'] ?? false),
                'home_route' => $roleDef['home_route'] ?? '/parapheur',
            ])->save();

            $perms = $roleDef['permissions'] === '*'
                ? $allNames
                : array_values(array_unique($roleDef['permissions']));

            $role->syncPermissions($perms);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public static function defaultLabel(string $name): string
    {
        $parts = explode('.', $name);
        $action = str_replace('_', ' ', array_pop($parts));
        $module = implode(' · ', $parts);

        return ucfirst(trim($module.' — '.$action));
    }

    public static function moduleLabel(string $module): string
    {
        return match ($module) {
            'admin' => 'Administration',
            'documents' => 'Documents',
            'dashboard' => 'Tableaux de bord',
            'task' => 'Tâches',
            'instruction' => 'Instructions',
            'ged' => 'GED',
            'archives' => 'Archives institutionnelles',
            'workspace' => 'Mon espace',
            'meetings' => 'Réunions',
            'appointments' => 'Rendez-vous',
            'reporting' => 'Reporting',
            'mail' => 'Courrier',
            'document_template' => 'Modèles',
            'ticket' => 'Ticketing',
            'production' => 'Productions',
            'announcement' => 'Annonces',
            'portal_app' => 'Portail apps',
            default => ucfirst($module),
        };
    }
}
