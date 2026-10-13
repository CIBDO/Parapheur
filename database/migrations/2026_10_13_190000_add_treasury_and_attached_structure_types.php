<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['code' => 'TRESORERIE', 'name' => 'Trésorerie', 'sort_order' => 3],
            ['code' => 'SERVICE', 'name' => 'Service rattaché', 'sort_order' => 4],
        ] as $type) {
            DB::table('structure_types')->updateOrInsert(
                ['code' => $type['code']],
                $type + ['updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('structure_types')->whereIn('code', ['TRESORERIE', 'SERVICE'])->delete();
    }
};
