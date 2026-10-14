<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondence_dispatches', function (Blueprint $table) {
            $table->json('lines')->nullable()->after('observations');
            $table->json('presentation')->nullable()->after('lines');
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_dispatches', function (Blueprint $table) {
            $table->dropColumn(['lines', 'presentation']);
        });
    }
};
