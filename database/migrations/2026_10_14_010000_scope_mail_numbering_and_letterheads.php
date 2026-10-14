<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_sequences', function (Blueprint $table) {
            $table->dropUnique(['code', 'year']);
            $table->unique(['code', 'year', 'structure_id']);
        });

        Schema::create('structure_document_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ministry_line')->nullable();
            $table->string('header_title')->nullable();
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

        Schema::table('correspondences', function (Blueprint $table) {
            $table->json('letterhead_snapshot')->nullable();
            $table->string('signatory_left')->nullable();
            $table->string('signatory_right')->nullable();
            $table->foreignId('signatory_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signatory_function')->nullable();
            $table->string('signatory_capacity')->nullable();
            $table->timestamp('letterhead_frozen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signatory_user_id');
            $table->dropColumn([
                'letterhead_snapshot',
                'signatory_left',
                'signatory_right',
                'signatory_function',
                'signatory_capacity',
                'letterhead_frozen_at',
            ]);
        });

        Schema::dropIfExists('structure_document_profiles');

        Schema::table('numbering_sequences', function (Blueprint $table) {
            $table->dropUnique(['code', 'year', 'structure_id']);
            $table->unique(['code', 'year']);
        });
    }
};
