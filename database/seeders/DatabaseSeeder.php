<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTransmission;
use App\Models\DocumentType;
use App\Models\ClassificationNode;
use App\Models\Structure;
use App\Models\StructureType;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'admin.access',
            'documents.create',
            'documents.act',
            'documents.vise',
            'documents.validate',
            'dashboard.dg',
            'dashboard.direction',
            'instructions.manage',
            'ged.view',
            'ged.search',
            'ged.create',
            'ged.update',
            'ged.upload',
            'ged.download',
            'ged.comment',
            'ged.create_version',
            'ged.classify',
            'ged.archive',
            'ged.restore',
            'ged.share',
            'ged.manage_metadata',
            'ged.manage_classification',
            'ged.manage_types',
            'ged.manage_categories',
            'ged.view_audit',
            'ged.manage_retention',
            'workspace.access',
            'workspace.create_shared',
            'workspace.manage_own',
            'workspace.manage_quotas',
            'library.access',
            'library.manage_own',
            'library.moderate',
            'meetings.view',
            'meetings.create',
            'meetings.update',
            'meetings.delete',
            'meetings.manage',
            'meetings.manage_participants',
            'meetings.manage_agenda',
            'meetings.send_invitations',
            'meetings.start',
            'meetings.manage_attendance',
            'meetings.take_official_notes',
            'meetings.create_decision',
            'meetings.manage_decisions',
            'meetings.generate_minutes',
            'meetings.validate_minutes',
            'meetings.close',
            'meetings.archive',
            'meetings.view_reports',
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.delete_draft',
            'appointments.manage_requests',
            'appointments.propose_slot',
            'appointments.validate',
            'appointments.reject',
            'appointments.reschedule',
            'appointments.cancel',
            'appointments.confirm',
            'appointments.manage_participants',
            'appointments.manage_documents',
            'appointments.manage_notes',
            'appointments.manage_followups',
            'appointments.view_calendar',
            'appointments.manage_calendar',
            'appointments.manage_unavailability',
            'appointments.view_reports',
            'appointments.archive',
            'reporting.view',
            'delegations.manage',
            'mail.view',
            'mail.view_all',
            'mail.view_confidential',
            'mail.view_very_confidential',
            'mail.create',
            'mail.update',
            'mail.delete',
            'mail.assign',
            'mail.process',
            'mail.reply',
            'mail.dispatch',
            'mail.archive',
            'mail.admin',
            'mail.create_transmission_slip',
            'mail.update_transmission_slip',
            'mail.delete_transmission_slip',
            'mail.validate_transmission_slip',
            'document_template.view',
            'document_template.view_all',
            'document_template.create',
            'document_template.update',
            'document_template.delete',
            'document_template.publish',
            'ticket.view',
            'ticket.create',
            'ticket.update',
            'ticket.assign',
            'ticket.reassign',
            'ticket.take_charge',
            'ticket.comment',
            'ticket.internal_note',
            'ticket.escalate',
            'ticket.resolve',
            'ticket.close',
            'ticket.reopen',
            'ticket.cancel',
            'ticket.view_all',
            'ticket.view_team',
            'ticket.view_reports',
            'ticket.manage_sla',
            'ticket.manage_catalog',
            'ticket.manage_categories',
            'ticket.admin',
            'ticket.audit.view',
            'problem.view',
            'problem.create',
            'problem.update',
            'problem.close',
            'knowledge.view',
            'knowledge.create',
            'knowledge.review',
            'knowledge.publish',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $gedBasic = [
            'ged.view', 'ged.search', 'ged.create', 'ged.update', 'ged.upload',
            'ged.download', 'ged.comment', 'ged.create_version', 'ged.classify', 'ged.share',
        ];
        $gedManage = array_merge($gedBasic, [
            'ged.archive', 'ged.restore', 'ged.manage_metadata',
            'ged.manage_classification', 'ged.manage_types', 'ged.manage_categories', 'ged.view_audit',
        ]);

        $workspaceBasic = [
            'workspace.access', 'workspace.manage_own', 'library.access', 'library.manage_own',
        ];
        $workspaceManage = array_merge($workspaceBasic, [
            'workspace.create_shared', 'workspace.manage_quotas', 'library.moderate',
        ]);

        $mailBasic = [
            'mail.view', 'mail.create', 'mail.process',
        ];
        $mailManage = array_merge($mailBasic, [
            'mail.view_all', 'mail.update', 'mail.assign', 'mail.reply', 'mail.dispatch', 'mail.archive',
            'mail.create_transmission_slip', 'mail.update_transmission_slip', 'mail.validate_transmission_slip',
        ]);
        $mailAdmin = array_merge($mailManage, [
            'mail.view_confidential', 'mail.view_very_confidential', 'mail.delete', 'mail.admin',
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

        $appointmentManage = [
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.delete_draft',
            'appointments.manage_requests',
            'appointments.propose_slot',
            'appointments.validate',
            'appointments.reject',
            'appointments.reschedule',
            'appointments.cancel',
            'appointments.confirm',
            'appointments.manage_participants',
            'appointments.manage_documents',
            'appointments.manage_notes',
            'appointments.manage_followups',
            'appointments.view_calendar',
            'appointments.manage_calendar',
            'appointments.manage_unavailability',
            'appointments.view_reports',
            'appointments.archive',
        ];

        $roles = [
            'Administrateur' => $permissions,
            'Directeur Général' => array_merge(
                ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view', 'delegations.manage'],
                $gedManage,
                $workspaceManage,
                $appointmentManage,
                $mailAdmin,
                $ticketAdmin
            ),
            'DGA' => array_merge(
                ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view'],
                $gedManage,
                $workspaceManage,
                ['appointments.view', 'appointments.create', 'appointments.view_calendar', 'appointments.validate', 'appointments.manage_notes'],
                $mailManage,
                $ticketLead
            ),
            'Conseiller' => array_merge(
                ['documents.create', 'documents.act', 'reporting.view', 'meetings.view', 'appointments.view', 'appointments.create', 'appointments.view_calendar'],
                $gedBasic,
                $workspaceBasic,
                $mailBasic,
                $ticketBasic
            ),
            'Secrétariat DG' => array_merge(
                ['documents.create', 'documents.act', 'meetings.manage', 'meetings.view', 'meetings.create', 'meetings.take_official_notes', 'meetings.generate_minutes', 'reporting.view'],
                $gedManage,
                $workspaceManage,
                $appointmentManage,
                $mailAdmin,
                $ticketLead
            ),
            'Directeur' => array_merge(
                ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.direction', 'reporting.view', 'meetings.manage', 'meetings.view', 'appointments.view', 'appointments.create', 'appointments.view_calendar'],
                $gedManage,
                $workspaceBasic,
                $mailManage,
                $ticketLead
            ),
            'Chef de division' => array_merge(
                ['documents.create', 'documents.act', 'meetings.view', 'appointments.view', 'appointments.create'],
                $gedBasic,
                $workspaceBasic,
                $mailBasic,
                $ticketAgent
            ),
            'Chef de section' => array_merge(
                ['documents.create', 'documents.act', 'meetings.view', 'appointments.view', 'appointments.create'],
                $gedBasic,
                $workspaceBasic,
                $mailBasic,
                $ticketAgent
            ),
            'Agent' => array_merge(
                ['documents.create', 'documents.act', 'meetings.view', 'appointments.view', 'appointments.create'],
                $gedBasic,
                $workspaceBasic,
                $mailBasic,
                $ticketAgent
            ),
            'Lecteur' => array_merge(
                ['meetings.view', 'appointments.view', 'appointments.view_calendar'],
                ['ged.view', 'ged.search', 'ged.download'],
                ['workspace.access', 'library.access'],
                ['mail.view'],
                ['ticket.view', 'knowledge.view']
            ),
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($perms);
        }

        $typeDg = StructureType::query()->updateOrCreate(
            ['code' => 'DG'],
            ['name' => 'Direction Générale', 'sort_order' => 1]
        );
        $typeDir = StructureType::query()->updateOrCreate(
            ['code' => 'DIR'],
            ['name' => 'Direction', 'sort_order' => 2]
        );

        $dgtcp = Structure::query()->updateOrCreate(
            ['code' => 'DGTCP'],
            [
                'structure_type_id' => $typeDg->id,
                'name' => 'Direction Générale du Trésor et de la Comptabilité Publique',
                'sort_order' => 1,
            ]
        );

        $dg = Structure::query()->updateOrCreate(
            ['code' => 'DG'],
            [
                'parent_id' => $dgtcp->id,
                'structure_type_id' => $typeDg->id,
                'name' => 'Cabinet du Directeur Général',
                'sort_order' => 2,
            ]
        );

        $dsi = Structure::query()->updateOrCreate(
            ['code' => 'DSI'],
            [
                'parent_id' => $dgtcp->id,
                'structure_type_id' => $typeDir->id,
                'name' => 'Direction des Systèmes d\'Information',
                'sort_order' => 3,
            ]
        );

        $dfm = Structure::query()->updateOrCreate(
            ['code' => 'DFM'],
            [
                'parent_id' => $dgtcp->id,
                'structure_type_id' => $typeDir->id,
                'name' => 'Direction des Finances et du Matériel',
                'sort_order' => 4,
            ]
        );

        $docTypes = [
            ['code' => 'NOTE', 'name' => 'Note', 'sort_order' => 1],
            ['code' => 'NOTE_TECH', 'name' => 'Note technique', 'sort_order' => 2],
            ['code' => 'RAPPORT', 'name' => 'Rapport', 'sort_order' => 3],
            ['code' => 'DECISION', 'name' => 'Projet de décision', 'sort_order' => 4],
            ['code' => 'CR', 'name' => 'Compte rendu', 'sort_order' => 5],
            ['code' => 'LETTRE', 'name' => 'Projet de lettre', 'sort_order' => 6],
            ['code' => 'DOSSIER_REUNION', 'name' => 'Dossier de réunion', 'sort_order' => 7],
            ['code' => 'CONVOCATION', 'name' => 'Convocation', 'sort_order' => 8],
        ];

        foreach ($docTypes as $type) {
            DocumentType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type + ['is_active' => true]
            );
        }

        $users = [
            [
                'name' => 'Admin Parapheur',
                'first_name' => 'Admin',
                'last_name' => 'Parapheur',
                'email' => 'admin@dgtcp.local',
                'password' => 'password',
                'structure_id' => $dg->id,
                'position_title' => 'Administrateur',
                'role' => 'Administrateur',
            ],
            [
                'name' => 'Directeur Général',
                'first_name' => 'Jean',
                'last_name' => 'Kouassi',
                'email' => 'dg@dgtcp.local',
                'password' => 'password',
                'structure_id' => $dg->id,
                'position_title' => 'Directeur Général',
                'role' => 'Directeur Général',
            ],
            [
                'name' => 'Secrétariat DG',
                'first_name' => 'Awa',
                'last_name' => 'Traoré',
                'email' => 'secretariat@dgtcp.local',
                'password' => 'password',
                'structure_id' => $dg->id,
                'position_title' => 'Secrétariat DG',
                'role' => 'Secrétariat DG',
            ],
            [
                'name' => 'Directeur DSI',
                'first_name' => 'Paul',
                'last_name' => 'N\'Guessan',
                'email' => 'directeur.dsi@dgtcp.local',
                'password' => 'password',
                'structure_id' => $dsi->id,
                'position_title' => 'Directeur',
                'role' => 'Directeur',
            ],
            [
                'name' => 'Agent DSI',
                'first_name' => 'Fatou',
                'last_name' => 'Diabaté',
                'email' => 'agent.dsi@dgtcp.local',
                'password' => 'password',
                'structure_id' => $dsi->id,
                'position_title' => 'Agent',
                'role' => 'Agent',
            ],
            [
                'name' => 'Directeur DFM',
                'first_name' => 'Mariama',
                'last_name' => 'Sangaré',
                'email' => 'directeur.dfm@dgtcp.local',
                'password' => 'password',
                'structure_id' => $dfm->id,
                'position_title' => 'Directeur',
                'role' => 'Directeur',
            ],
        ];

        foreach ($users as $payload) {
            $role = $payload['role'];
            unset($payload['role']);
            $payload['is_active'] = true;

            // Mot de passe en clair : le cast "hashed" du modèle User s'occupe du hash.
            $user = User::query()->updateOrCreate(
                ['email' => $payload['email']],
                $payload
            );
            $user->syncRoles([$role]);
        }

        $workflow = Workflow::query()->updateOrCreate(
            ['code' => 'CIRCUIT_STANDARD'],
            [
                'name' => 'Circuit standard vers DG',
                'description' => 'Agent → Directeur → Secrétariat DG → DG',
                'kind' => 'predefini',
                'is_active' => true,
            ]
        );

        if ($workflow->steps()->count() === 0) {
            $workflow->steps()->createMany([
                ['step_order' => 1, 'name' => 'Agent', 'role_name' => 'Agent', 'expected_action' => 'consultation'],
                ['step_order' => 2, 'name' => 'Directeur', 'role_name' => 'Directeur', 'expected_action' => 'validation'],
                ['step_order' => 3, 'name' => 'Secrétariat DG', 'role_name' => 'Secrétariat DG', 'expected_action' => 'consultation'],
                ['step_order' => 4, 'name' => 'Directeur Général', 'role_name' => 'Directeur Général', 'expected_action' => 'validation'],
            ]);
        }

        $this->seedDemoDocuments();
        $this->seedGedReferentials(
            Structure::query()->where('code', 'DGTCP')->first(),
            Structure::query()->where('code', 'DSI')->first(),
        );
        $this->seedMailReferentials();
        $this->call(MeetingSeeder::class);
        $this->call(AppointmentSeeder::class);
        $this->call(TicketingSeeder::class);
    }

    private function seedDemoDocuments(): void
    {
        if (Document::query()->exists()) {
            return;
        }

        $note = DocumentType::query()->where('code', 'NOTE')->first();
        $rapport = DocumentType::query()->where('code', 'RAPPORT')->first();
        $dsi = Structure::query()->where('code', 'DSI')->first();
        $dfm = Structure::query()->where('code', 'DFM')->first();
        $dgUser = User::query()->where('email', 'dg@dgtcp.local')->first();
        $agent = User::query()->where('email', 'agent.dsi@dgtcp.local')->first();
        $directeur = User::query()->where('email', 'directeur.dsi@dgtcp.local')->first();
        $admin = User::query()->where('email', 'admin@dgtcp.local')->first();

        if (! $note || ! $dsi || ! $dgUser || ! $agent) {
            return;
        }

        $samples = [
            [
                'reference' => 'DSI-2026-001',
                'object' => 'Note technique — migration messagerie',
                'document_type_id' => $note->id,
                'structure_id' => $dsi->id,
                'author_id' => $agent->id,
                'current_assignee_id' => $dgUser->id,
                'status' => 'a_valider',
                'priority' => 'urgente',
                'expected_action' => 'validation',
                'folder' => 'a_valider',
                'to' => $dgUser,
            ],
            [
                'reference' => 'DSI-2026-002',
                'object' => 'Rapport trimestriel SI',
                'document_type_id' => ($rapport ?? $note)->id,
                'structure_id' => $dsi->id,
                'author_id' => $directeur?->id ?? $agent->id,
                'current_assignee_id' => $dgUser->id,
                'status' => 'a_consulter',
                'priority' => 'importante',
                'expected_action' => 'consultation',
                'folder' => 'a_consulter',
                'to' => $dgUser,
            ],
            [
                'reference' => 'DFM-2026-014',
                'object' => 'Projet de décision — acquisition matériel',
                'document_type_id' => $note->id,
                'structure_id' => $dfm?->id ?? $dsi->id,
                'author_id' => $agent->id,
                'current_assignee_id' => $directeur?->id ?? $dgUser->id,
                'status' => 'a_viser',
                'priority' => 'normale',
                'expected_action' => 'visa',
                'folder' => 'a_viser',
                'to' => $directeur ?? $dgUser,
            ],
            [
                'reference' => 'DSI-2026-003',
                'object' => 'Demande de correction — procédure backup',
                'document_type_id' => $note->id,
                'structure_id' => $dsi->id,
                'author_id' => $agent->id,
                'current_assignee_id' => $agent->id,
                'status' => 'a_corriger',
                'priority' => 'tres_urgente',
                'expected_action' => 'observations',
                'folder' => 'retournes',
                'to' => $agent,
            ],
            [
                'reference' => 'DG-2026-008',
                'object' => 'Note validée — organisation réunion cabinet',
                'document_type_id' => $note->id,
                'structure_id' => $dsi->id,
                'author_id' => $agent->id,
                'current_assignee_id' => $dgUser->id,
                'status' => 'valide',
                'priority' => 'normale',
                'expected_action' => 'validation',
                'folder' => 'traites',
                'to' => $dgUser,
            ],
            [
                'reference' => 'ADM-2026-001',
                'object' => 'Suivi paramétrage E-Tresor',
                'document_type_id' => $note->id,
                'structure_id' => $dsi->id,
                'author_id' => $admin?->id ?? $agent->id,
                'current_assignee_id' => $admin?->id ?? $agent->id,
                'status' => 'transmis',
                'priority' => 'importante',
                'expected_action' => 'information',
                'folder' => 'a_traiter',
                'to' => $admin ?? $agent,
            ],
        ];

        foreach ($samples as $sample) {
            $to = $sample['to'];
            $folder = $sample['folder'];
            unset($sample['to'], $sample['folder']);

            $document = Document::query()->create([
                ...$sample,
                'confidentiality' => 'normal',
                'document_date' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'current_version' => 1,
                'submitted_at' => now()->subDays(2),
            ]);

            DocumentTransmission::query()->create([
                'document_id' => $document->id,
                'from_user_id' => $sample['author_id'],
                'to_user_id' => $to->id,
                'folder' => $folder,
                'status' => in_array($folder, ['traites', 'archives'], true) ? 'done' : 'pending',
                'expected_action' => $sample['expected_action'],
                'message' => 'Document de démonstration',
            ]);
        }
    }

    private function seedGedReferentials(?Structure $dgtcp, ?Structure $dsi): void
    {
        $categories = [
            ['code' => 'ADMIN', 'name' => 'Administratif', 'sort_order' => 1],
            ['code' => 'TECH', 'name' => 'Technique', 'sort_order' => 2],
            ['code' => 'FIN', 'name' => 'Financier', 'sort_order' => 3],
            ['code' => 'JUR', 'name' => 'Juridique', 'sort_order' => 4],
            ['code' => 'RH', 'name' => 'Ressources humaines', 'sort_order' => 5],
        ];
        foreach ($categories as $cat) {
            DocumentCategory::query()->updateOrCreate(['code' => $cat['code']], $cat + ['is_active' => true]);
        }

        if (! $dgtcp) {
            return;
        }

        $root = ClassificationNode::query()->updateOrCreate(
            ['parent_id' => null, 'code' => 'DGTCP'],
            [
                'structure_id' => $dgtcp->id,
                'name' => 'DGTCP',
                'path' => 'DGTCP',
                'depth' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $nodes = [
            ['code' => 'DG', 'name' => 'Direction Générale', 'sort_order' => 1],
            ['code' => 'DSI', 'name' => 'DSI', 'sort_order' => 2, 'structure_id' => $dsi?->id],
            ['code' => 'CP', 'name' => 'Comptabilité publique', 'sort_order' => 3],
            ['code' => 'TRES', 'name' => 'Trésorerie', 'sort_order' => 4],
            ['code' => 'RH', 'name' => 'Ressources humaines', 'sort_order' => 5],
            ['code' => 'ARCH', 'name' => 'Archives institutionnelles', 'sort_order' => 6],
        ];

        foreach ($nodes as $n) {
            $child = ClassificationNode::query()->updateOrCreate(
                ['parent_id' => $root->id, 'code' => $n['code']],
                [
                    'structure_id' => $n['structure_id'] ?? $dgtcp->id,
                    'name' => $n['name'],
                    'path' => 'DGTCP/'.$n['code'],
                    'depth' => 1,
                    'sort_order' => $n['sort_order'],
                    'is_active' => true,
                ]
            );

            if ($n['code'] === 'DSI') {
                foreach ([
                    ['code' => 'PROJETS', 'name' => 'Projets'],
                    ['code' => 'NOTES', 'name' => 'Notes techniques'],
                    ['code' => 'RAPPORTS', 'name' => 'Rapports'],
                    ['code' => 'MARCHES', 'name' => 'Marchés'],
                ] as $i => $sub) {
                    ClassificationNode::query()->updateOrCreate(
                        ['parent_id' => $child->id, 'code' => $sub['code']],
                        [
                            'structure_id' => $dsi?->id ?? $dgtcp->id,
                            'name' => $sub['name'],
                            'path' => 'DGTCP/DSI/'.$sub['code'],
                            'depth' => 2,
                            'sort_order' => $i + 1,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $noteType = \App\Models\DocumentType::query()->where('code', 'NOTE')->first()
            ?? \App\Models\DocumentType::query()->where('code', 'NOTE_TECH')->first();
        $notesNode = ClassificationNode::query()->where('code', 'NOTES')->first();

        \App\Models\DocumentRetentionRule::query()->updateOrCreate(
            ['code' => 'DEFAULT_10Y'],
            [
                'name' => 'Conservation standard 10 ans',
                'document_type_id' => null,
                'category_id' => null,
                'retention_years' => 10,
                'final_disposition' => 'archiver',
                'is_active' => true,
                'notes' => 'Règle par défaut — aucune destruction automatique.',
            ]
        );

        if ($noteType) {
            \App\Models\DocumentRetentionRule::query()->updateOrCreate(
                ['code' => 'NOTE_15Y'],
                [
                    'name' => 'Notes — 15 ans',
                    'document_type_id' => $noteType->id,
                    'retention_years' => 15,
                    'final_disposition' => 'conserver',
                    'is_active' => true,
                ]
            );
        }

        if ($notesNode && $noteType && $dsi) {
            \App\Models\DocumentClassificationRule::query()->updateOrCreate(
                ['code' => 'AUTO_NOTE_DSI'],
                [
                    'name' => 'Notes DSI → Notes techniques',
                    'document_type_id' => $noteType->id,
                    'structure_id' => $dsi->id,
                    'target_classification_node_id' => $notesNode->id,
                    'trigger_status' => 'valide',
                    'priority' => 10,
                    'is_active' => true,
                ]
            );
        }

        if (! \App\Models\WorkspaceStoragePolicy::query()->exists()) {
            \App\Models\WorkspaceStoragePolicy::query()->create([]);
        }

        $referenceTypes = [
            ['code' => 'LOI', 'name' => 'Loi'],
            ['code' => 'DECRET', 'name' => 'Décret'],
            ['code' => 'ARRETE', 'name' => 'Arrêté'],
            ['code' => 'INSTRUCTION', 'name' => 'Instruction'],
            ['code' => 'CIRCULAIRE', 'name' => 'Circulaire'],
            ['code' => 'DECISION', 'name' => 'Décision'],
            ['code' => 'REGLEMENT', 'name' => 'Règlement'],
            ['code' => 'GUIDE', 'name' => 'Guide'],
            ['code' => 'MANUEL', 'name' => 'Manuel'],
            ['code' => 'RAPPORT', 'name' => 'Rapport'],
            ['code' => 'ETUDE', 'name' => 'Étude'],
            ['code' => 'NORME', 'name' => 'Norme'],
            ['code' => 'ARTICLE', 'name' => 'Article'],
            ['code' => 'PUBLICATION', 'name' => 'Publication'],
            ['code' => 'DOCUMENT_TECHNIQUE', 'name' => 'Document technique'],
            ['code' => 'AUTRE', 'name' => 'Autre'],
        ];

        foreach ($referenceTypes as $i => $type) {
            \App\Models\ReferenceType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedMailReferentials(): void
    {
        // Canaux de correspondance
        $channels = [
            ['code' => 'COURRIER', 'name' => 'Courrier postal', 'sort_order' => 1],
            ['code' => 'EMAIL', 'name' => 'Email', 'sort_order' => 2],
            ['code' => 'FAX', 'name' => 'Fax', 'sort_order' => 3],
            ['code' => 'REMISE', 'name' => 'Remise en main propre', 'sort_order' => 4],
            ['code' => 'PLATEFORME', 'name' => 'Plateforme électronique', 'sort_order' => 5],
        ];
        foreach ($channels as $channel) {
            \App\Models\CorrespondenceChannel::query()->updateOrCreate(
                ['code' => $channel['code']],
                $channel + ['is_active' => true]
            );
        }

        // Catégories de correspondance
        $categories = [
            ['code' => 'ADMIN', 'name' => 'Administratif', 'sort_order' => 1],
            ['code' => 'TECH', 'name' => 'Technique', 'sort_order' => 2],
            ['code' => 'FIN', 'name' => 'Financier', 'sort_order' => 3],
            ['code' => 'JUR', 'name' => 'Juridique', 'sort_order' => 4],
            ['code' => 'RH', 'name' => 'Ressources humaines', 'sort_order' => 5],
        ];
        foreach ($categories as $category) {
            \App\Models\CorrespondenceCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category + ['is_active' => true]
            );
        }

        // Qualifications
        $qualifications = [
            ['code' => 'URGENT', 'name' => 'Urgent', 'sort_order' => 1],
            ['code' => 'IMPORTANT', 'name' => 'Important', 'sort_order' => 2],
            ['code' => 'CONFIDENTIEL', 'name' => 'Confidentiel', 'sort_order' => 3],
            ['code' => 'POUR_INFO', 'name' => 'Pour information', 'sort_order' => 4],
            ['code' => 'POUR_AVIS', 'name' => 'Pour avis', 'sort_order' => 5],
        ];
        foreach ($qualifications as $qualification) {
            \App\Models\CorrespondenceQualification::query()->updateOrCreate(
                ['code' => $qualification['code']],
                $qualification + ['is_active' => true]
            );
        }

        // Actions d'affectation
        $actions = [
            ['code' => 'TRAITER', 'name' => 'Traiter', 'sort_order' => 1],
            ['code' => 'DONNER_AVIS', 'name' => 'Donner un avis', 'sort_order' => 2],
            ['code' => 'PREPARER_REPONSE', 'name' => 'Préparer une réponse', 'sort_order' => 3],
            ['code' => 'POUR_INFO', 'name' => 'Pour information', 'sort_order' => 4],
            ['code' => 'ARCHIVER', 'name' => 'Archiver', 'sort_order' => 5],
        ];
        foreach ($actions as $action) {
            \App\Models\CorrespondenceAssignmentAction::query()->updateOrCreate(
                ['code' => $action['code']],
                $action + ['is_active' => true]
            );
        }

        // Séquences de numérotation pour l'année courante
        $year = now()->year;
        $sequences = [
            ['code' => 'ARR', 'prefix' => 'ARR', 'padding' => 6, 'reset_yearly' => true],
            ['code' => 'DEP', 'prefix' => 'DEP', 'padding' => 6, 'reset_yearly' => true],
            ['code' => 'BT', 'prefix' => 'BT', 'padding' => 6, 'reset_yearly' => true],
            ['code' => 'FC', 'prefix' => 'FC', 'padding' => 6, 'reset_yearly' => true],
        ];
        foreach ($sequences as $seq) {
            \App\Models\NumberingSequence::query()->firstOrCreate(
                ['code' => $seq['code'], 'year' => $year, 'structure_id' => null],
                $seq + ['last_value' => 0]
            );
        }

        // Correspondants de démo
        $correspondents = [
            [
                'type' => 'personne_morale',
                'name' => 'Ministère des Finances',
                'organization' => 'Ministère des Finances',
                'city' => 'Abidjan',
                'country' => 'Côte d\'Ivoire',
                'is_active' => true,
            ],
            [
                'type' => 'personne_morale',
                'name' => 'BCEAO',
                'organization' => 'Banque Centrale des États de l\'Afrique de l\'Ouest',
                'city' => 'Dakar',
                'country' => 'Sénégal',
                'is_active' => true,
            ],
            [
                'type' => 'personne_physique',
                'name' => 'M. Koné Mamadou',
                'function' => 'Directeur',
                'organization' => 'Entreprise ABC',
                'city' => 'Abidjan',
                'country' => 'Côte d\'Ivoire',
                'is_active' => true,
            ],
        ];
        foreach ($correspondents as $correspondent) {
            \App\Models\Correspondent::query()->updateOrCreate(
                ['name' => $correspondent['name']],
                $correspondent
            );
        }
    }
}