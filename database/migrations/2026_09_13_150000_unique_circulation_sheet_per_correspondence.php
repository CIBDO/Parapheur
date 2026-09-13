<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conserver une fiche par courrier (priorité : avec document, sinon la plus ancienne).
        $duplicates = DB::table('circulation_sheets')
            ->select('correspondence_id')
            ->groupBy('correspondence_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('correspondence_id');

        foreach ($duplicates as $correspondenceId) {
            $keepers = DB::table('circulation_sheets')
                ->where('correspondence_id', $correspondenceId)
                ->orderByRaw('CASE WHEN document_id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->pluck('id');

            $keepId = $keepers->first();
            if (! $keepId) {
                continue;
            }

            DB::table('circulation_sheets')
                ->where('correspondence_id', $correspondenceId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('circulation_sheets', function (Blueprint $table) {
            $table->unique('correspondence_id', 'circulation_sheets_correspondence_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('circulation_sheets', function (Blueprint $table) {
            $table->dropUnique('circulation_sheets_correspondence_id_unique');
        });
    }
};
