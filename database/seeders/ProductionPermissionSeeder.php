<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'production.view',
            'production.view_all',
            'production.manage',
            'production.validate',
            'production.transmit',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $basic = ['production.view', 'production.validate', 'production.transmit'];
        $lead = array_merge($basic, ['production.view_all']);
        $admin = array_merge($lead, ['production.manage']);

        $map = [
            'Administrateur' => $admin,
            'Directeur Général' => $admin,
            'DGA' => $admin,
            'Secrétariat DG' => $lead,
            'Directeur' => $lead,
            'Chef de division' => $basic,
            'Chef de section' => $basic,
            'Agent' => ['production.view', 'production.transmit'],
            'Conseiller' => ['production.view'],
            'Lecteur' => ['production.view'],
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
