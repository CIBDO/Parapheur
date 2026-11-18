<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ArchivesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ArchivesClassificationSeeder::class,
            ArchivesRetentionTemplateSeeder::class,
        ]);
    }
}
