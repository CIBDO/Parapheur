<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_obligations', function (Blueprint $table) {
            if (! Schema::hasColumn('production_obligations', 'submit_to_parapheur')) {
                $table->boolean('submit_to_parapheur')->default(false)->after('auto_transmit');
            }
            if (! Schema::hasColumn('production_obligations', 'workflow_id')) {
                $table->foreignId('workflow_id')->nullable()->after('submit_to_parapheur')->constrained('workflows')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_obligations', function (Blueprint $table) {
            if (Schema::hasColumn('production_obligations', 'workflow_id')) {
                $table->dropConstrainedForeignId('workflow_id');
            }
            if (Schema::hasColumn('production_obligations', 'submit_to_parapheur')) {
                $table->dropColumn('submit_to_parapheur');
            }
        });
    }
};
