<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('allowed_actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['delegate_id', 'is_active']);
            $table->index(['delegator_id', 'is_active']);
        });

        Schema::create('task_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('reason')->nullable();
            $table->foreignId('notified_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at');
            $table->timestamps();

            $table->unique(['task_id', 'level']);
            $table->index('escalated_at');
        });

        if (Schema::hasTable('task_comments') && ! Schema::hasColumn('task_comments', 'mentions')) {
            Schema::table('task_comments', function (Blueprint $table) {
                $table->json('mentions')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('task_comments') && Schema::hasColumn('task_comments', 'mentions')) {
            Schema::table('task_comments', function (Blueprint $table) {
                $table->dropColumn('mentions');
            });
        }

        Schema::dropIfExists('task_escalations');
        Schema::dropIfExists('task_delegations');
    }
};
