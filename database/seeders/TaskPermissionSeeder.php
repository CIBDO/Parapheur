<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** @deprecated Utiliser RbacSeeder / PermissionCatalog */
class TaskPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RbacSeeder::class);
    }
}
