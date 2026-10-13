<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AnnouncementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'announcement.view',
            'announcement.create',
            'announcement.update',
            'announcement.submit',
            'announcement.approve',
            'announcement.reject',
            'announcement.publish',
            'announcement.schedule',
            'announcement.unpublish',
            'announcement.archive',
            'announcement.manage_categories',
            'announcement.view_statistics',
            'announcement.view_read_receipts',
            'announcement.export',
            'announcement.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $read = ['announcement.view'];
        $author = array_merge($read, [
            'announcement.create',
            'announcement.update',
            'announcement.submit',
        ]);
        $chief = array_merge($author, [
            'announcement.view_statistics',
            'announcement.view_read_receipts',
        ]);
        $director = array_merge($chief, [
            'announcement.approve',
            'announcement.reject',
            'announcement.publish',
            'announcement.schedule',
            'announcement.unpublish',
            'announcement.archive',
            'announcement.export',
        ]);
        $secretariat = array_merge($director, [
            'announcement.manage_categories',
        ]);
        $admin = array_merge($secretariat, [
            'announcement.manage',
        ]);

        $map = [
            'Administrateur' => $admin,
            'Directeur Général' => $admin,
            'DGA' => $admin,
            'Secrétariat DG' => $secretariat,
            'Directeur' => $director,
            'Chef de division' => $chief,
            'Chef de section' => $author,
            'Agent' => $read,
            'Conseiller' => $read,
            'Lecteur' => $read,
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
