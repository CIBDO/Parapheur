<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondence_dispatches', function (Blueprint $table) {
            $table->string('number', 50)->nullable()->after('correspondence_id');
            $table->foreignId('document_id')->nullable()->after('observations')->constrained('documents')->nullOnDelete();
        });

        Schema::table('correspondence_acknowledgements', function (Blueprint $table) {
            $table->string('number', 50)->nullable()->after('correspondence_id');
            $table->foreignId('document_id')->nullable()->after('registered_by')->constrained('documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_dispatches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_id');
            $table->dropColumn('number');
        });

        Schema::table('correspondence_acknowledgements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_id');
            $table->dropColumn('number');
        });
    }
};
