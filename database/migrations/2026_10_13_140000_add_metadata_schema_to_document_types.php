<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (! Schema::hasColumn('document_types', 'metadata_schema')) {
                $table->json('metadata_schema')->nullable()->after('scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'metadata_schema')) {
                $table->dropColumn('metadata_schema');
            }
        });
    }
};
