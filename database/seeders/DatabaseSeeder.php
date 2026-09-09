<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Structure;
use App\Models\StructureType;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
            'meetings.manage',
            'reporting.view',
            'delegations.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $roles = [
            'Administrateur' => $permissions,
            'Directeur Général' => ['documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view', 'delegations.manage'],
            'DGA' => ['documents.act', 'documents.vise', 'documents.validate', 'dashboard.dg', 'instructions.manage', 'meetings.manage', 'reporting.view'],
            'Conseiller' => ['documents.act', 'reporting.view'],
            'Secrétariat DG' => ['documents.create', 'documents.act', 'meetings.manage', 'reporting.view'],
            'Directeur' => ['documents.create', 'documents.act', 'documents.vise', 'documents.validate', 'dashboard.direction', 'reporting.view', 'meetings.manage'],
            'Chef de division' => ['documents.create', 'documents.act'],
            'Chef de section' => ['documents.create', 'documents.act'],
            'Agent' => ['documents.create', 'documents.act'],
            'Lecteur' => [],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($perms);
        }

        $typeDg = StructureType::query()->create(['code' => 'DG', 'name' => 'Direction Générale', 'sort_order' => 1]);
        $typeDir = StructureType::query()->create(['code' => 'DIR', 'name' => 'Direction', 'sort_order' => 2]);

        $dgtcp = Structure::query()->create([
            'structure_type_id' => $typeDg->id,
            'code' => 'DGTCP',
            'name' => 'Direction Générale du Trésor et de la Comptabilité Publique',
            'sort_order' => 1,
        ]);

        $dg = Structure::query()->create([
            'parent_id' => $dgtcp->id,
            'structure_type_id' => $typeDg->id,
            'code' => 'DG',
            'name' => 'Cabinet du Directeur Général',
            'sort_order' => 2,
        ]);

        $dsi = Structure::query()->create([
            'parent_id' => $dgtcp->id,
            'structure_type_id' => $typeDir->id,
            'code' => 'DSI',
            'name' => 'Direction des Systèmes d\'Information',
            'sort_order' => 3,
        ]);

        $dfm = Structure::query()->create([
            'parent_id' => $dgtcp->id,
            'structure_type_id' => $typeDir->id,
            'code' => 'DFM',
            'name' => 'Direction des Finances et du Matériel',
            'sort_order' => 4,
        ]);

        $docTypes = [
            ['code' => 'NOTE', 'name' => 'Note', 'sort_order' => 1],
            ['code' => 'NOTE_TECH', 'name' => 'Note technique', 'sort_order' => 2],
            ['code' => 'RAPPORT', 'name' => 'Rapport', 'sort_order' => 3],
            ['code' => 'DECISION', 'name' => 'Projet de décision', 'sort_order' => 4],
            ['code' => 'CR', 'name' => 'Compte rendu', 'sort_order' => 5],
            ['code' => 'LETTRE', 'name' => 'Projet de lettre', 'sort_order' => 6],
            ['code' => 'DOSSIER_REUNION', 'name' => 'Dossier de réunion', 'sort_order' => 7],
        ];

        foreach ($docTypes as $type) {
            DocumentType::query()->create($type + ['is_active' => true]);
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
            $payload['password'] = Hash::make($payload['password']);
            $payload['is_active'] = true;
            $user = User::query()->create($payload);
            $user->assignRole($role);
        }

        $workflow = Workflow::query()->create([
            'code' => 'CIRCUIT_STANDARD',
            'name' => 'Circuit standard vers DG',
            'description' => 'Agent → Directeur → Secrétariat DG → DG',
            'kind' => 'predefini',
            'is_active' => true,
        ]);

        $workflow->steps()->createMany([
            ['step_order' => 1, 'name' => 'Agent', 'role_name' => 'Agent', 'expected_action' => 'consultation'],
            ['step_order' => 2, 'name' => 'Directeur', 'role_name' => 'Directeur', 'expected_action' => 'validation'],
            ['step_order' => 3, 'name' => 'Secrétariat DG', 'role_name' => 'Secrétariat DG', 'expected_action' => 'consultation'],
            ['step_order' => 4, 'name' => 'Directeur Général', 'role_name' => 'Directeur Général', 'expected_action' => 'validation'],
        ]);
    }
}
