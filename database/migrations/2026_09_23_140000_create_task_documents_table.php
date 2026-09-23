<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_documents')) {
            Schema::create('task_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->foreignId('linked_by')->constrained('users')->cascadeOnDelete();
                $table->string('role')->default('working'); // working|proof|final|ged_link
                $table->boolean('submitted_to_ged')->default(false);
                $table->timestamp('submitted_to_ged_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['task_id', 'document_id']);
                $table->index('role');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_documents');
    }
};
