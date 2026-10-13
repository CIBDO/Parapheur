<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PortalAppPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'portal_app.view',
            'portal_app.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $view = ['portal_app.view'];
        $manage = array_merge($view, ['portal_app.manage']);

        $map = [
            'Administrateur' => $manage,
            'Directeur Général' => $manage,
            'DGA' => $manage,
            'Secrétariat DG' => $manage,
            'Directeur' => $view,
            'Chef de division' => $view,
            'Chef de section' => $view,
            'Agent' => $view,
            'Conseiller' => $view,
            'Lecteur' => $view,
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
