<?php

namespace Database\Seeders;

use App\Models\TaskType;
use Illuminate\Database\Seeder;

class TaskTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'administrative', 'name' => 'Tâche administrative', 'sort_order' => 10],
            ['code' => 'technique', 'name' => 'Tâche technique', 'sort_order' => 20],
            ['code' => 'documentaire', 'name' => 'Tâche documentaire', 'sort_order' => 30],
            ['code' => 'validation', 'name' => 'Tâche de validation', 'sort_order' => 40],
            ['code' => 'production', 'name' => 'Tâche de production', 'sort_order' => 50],
            ['code' => 'transmission', 'name' => 'Tâche de transmission', 'sort_order' => 60],
            ['code' => 'controle', 'name' => 'Tâche de contrôle', 'sort_order' => 70],
            ['code' => 'suivi', 'name' => 'Tâche de suivi', 'sort_order' => 80],
            ['code' => 'reunion', 'name' => 'Tâche issue d\'une réunion', 'sort_order' => 90],
            ['code' => 'courrier', 'name' => 'Tâche issue d\'un courrier', 'sort_order' => 100],
            ['code' => 'directive', 'name' => 'Tâche issue d\'une directive', 'sort_order' => 110],
        ];

        foreach ($types as $type) {
            TaskType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
