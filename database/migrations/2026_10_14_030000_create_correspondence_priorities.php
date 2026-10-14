<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('color', 30)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('correspondence_priorities')->insert([
            ['code' => 'normale', 'name' => 'Normale', 'color' => 'info', 'sort_order' => 1, 'is_urgent' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'importante', 'name' => 'Importante', 'color' => 'warning', 'sort_order' => 2, 'is_urgent' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'urgente', 'name' => 'Urgente', 'color' => 'error', 'sort_order' => 3, 'is_urgent' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'tres_urgente', 'name' => 'Très urgente', 'color' => 'error', 'sort_order' => 4, 'is_urgent' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_priorities');
    }
};
