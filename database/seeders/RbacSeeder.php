<?php

namespace Database\Seeders;

use App\Support\Rbac\PermissionCatalog;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        PermissionCatalog::sync();
    }
}
