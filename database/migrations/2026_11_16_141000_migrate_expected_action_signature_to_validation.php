<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conformité Mali : retirer l’usage métier de « signature » (pas de SE électronique).
 * Les valeurs existantes sont migrées vers « validation ».
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'documents' => 'expected_action',
            'document_transmissions' => 'expected_action',
            'workflow_steps' => 'expected_action',
        ];

        foreach ($tables as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->where($column, 'signature')->update([$column => 'validation']);
        }
    }

    public function down(): void
    {
        // Irreversible volontairement : ne pas réintroduire « signature ».
    }
};
