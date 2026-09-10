<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentTransmission;
use App\Models\DocumentType;
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
            'reporting.view',
            'delegations.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $roles = [
            'Administrateur' => $permissions,
            'Directeur Général' => ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view', 'delegations.manage'],
            'DGA' => ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view'],
            'Conseiller' => ['documents.create', 'documents.act', 'reporting.view', 'meetings.view'],
            'Secrétariat DG' => ['documents.create', 'documents.act', 'meetings.manage', 'meetings.view', 'meetings.create', 'meetings.take_official_notes', 'meetings.generate_minutes', 'reporting.view'],
            'Directeur' => ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.direction', 'reporting.view', 'meetings.manage', 'meetings.view'],
            'Chef de division' => ['documents.create', 'documents.act', 'meetings.view'],
            'Chef de section' => ['documents.create', 'documents.act', 'meetings.view'],
            'Agent' => ['documents.create', 'documents.act', 'meetings.view'],
            // Consultation uniquement (cahier des charges §6)
            'Lecteur' => ['meetings.view'],
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
        $this->call(MeetingSeeder::class);
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
                'object' => 'Suivi paramétrage e-Parapheur',
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
}
