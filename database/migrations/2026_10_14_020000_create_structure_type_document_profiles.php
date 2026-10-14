<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_type_document_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_type_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ministry_line')->nullable();
            $table->string('header_title')->nullable();
            $table->boolean('append_structure_name')->default(true);
            $table->text('footer_text')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('republic_line')->nullable();
            $table->string('motto')->nullable();
            $table->string('signatory_left_label')->nullable();
            $table->string('signatory_right_label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_type_document_profiles');
    }
};
