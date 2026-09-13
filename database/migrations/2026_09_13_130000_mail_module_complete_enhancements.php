<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter colonnes manquantes à document_print_logs si la table existe
        if (Schema::hasTable('document_print_logs')) {
            if (! Schema::hasColumn('document_print_logs', 'reason')) {
                Schema::table('document_print_logs', function (Blueprint $table) {
                    $table->string('reason')->nullable()->after('page_count');
                });
            }

            if (! Schema::hasColumn('document_print_logs', 'is_reprint')) {
                Schema::table('document_print_logs', function (Blueprint $table) {
                    $table->boolean('is_reprint')->default(false)->after('reason');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('document_print_logs')) {
            Schema::table('document_print_logs', function (Blueprint $table) {
                if (Schema::hasColumn('document_print_logs', 'reason')) {
                    $table->dropColumn('reason');
                }
                if (Schema::hasColumn('document_print_logs', 'is_reprint')) {
                    $table->dropColumn('is_reprint');
                }
            });
        }
    }
};
