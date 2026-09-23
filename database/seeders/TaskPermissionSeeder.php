<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TaskPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'task.view', 'task.create', 'task.update', 'task.assign', 'task.reassign',
            'task.take_charge', 'task.comment', 'task.complete', 'task.validate', 'task.return',
            'task.cancel', 'task.view_team', 'task.view_all', 'task.view_reports', 'task.manage',
            'task.audit.view', 'instruction.view', 'instruction.create', 'instruction.assign',
            'instruction.update', 'instruction.close', 'instruction.cancel',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

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

        $map = [
            'Administrateur' => $permissions,
            'Directeur Général' => $taskAdmin,
            'DGA' => $taskAdmin,
            'Secrétariat DG' => $taskLead,
            'Directeur' => $taskLead,
            'Chef de division' => $taskLead,
            'Chef de section' => $taskBasic,
            'Agent' => $taskBasic,
            'Conseiller' => $taskBasic,
            'Lecteur' => ['task.view', 'instruction.view'],
        ];

        foreach ($map as $roleName => $perms) {
            $role = Role::findOrCreate($roleName);
            foreach ($perms as $perm) {
                if (! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }
}
