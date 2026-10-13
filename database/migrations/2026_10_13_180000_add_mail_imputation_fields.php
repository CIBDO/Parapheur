<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondents', function (Blueprint $table) {
            $table->string('sigle', 50)->nullable()->after('name');
            $table->index('sigle');
        });

        Schema::table('correspondences', function (Blueprint $table) {
            $table->string('ministry_number', 100)->nullable()->after('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('correspondents', function (Blueprint $table) {
            $table->dropIndex(['sigle']);
            $table->dropColumn('sigle');
        });

        Schema::table('correspondences', function (Blueprint $table) {
            $table->dropColumn('ministry_number');
        });
    }
};
