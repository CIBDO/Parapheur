<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_types')) {
            Schema::create('task_types', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'task_type_id')) {
                $table->foreignId('task_type_id')->nullable()->after('instruction_id')->constrained('task_types')->nullOnDelete();
            }
        });

        Schema::table('instructions', function (Blueprint $table) {
            if (! Schema::hasColumn('instructions', 'kind')) {
                $table->string('kind')->default('imputation')->after('reference')->index();
            }
            if (! Schema::hasColumn('instructions', 'coordinator_id')) {
                $table->foreignId('coordinator_id')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('instructions', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('due_date');
            }
            if (! Schema::hasColumn('instructions', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('instructions')->nullOnDelete();
            }
        });

        // Directives peuvent n'avoir qu'un coordinateur / plusieurs destinataires
        try {
            Schema::table('instructions', function (Blueprint $table) {
                $table->dropForeign(['assignee_id']);
            });
            Schema::table('instructions', function (Blueprint $table) {
                $table->foreignId('assignee_id')->nullable()->change();
                $table->foreign('assignee_id')->references('id')->on('users')->nullOnDelete();
            });
        } catch (\Throwable) {
            // SQLite / drivers without doctrine/dbal: keep existing constraint; directives still pass assignee_id
        }
    }

    public function down(): void
    {
        Schema::table('instructions', function (Blueprint $table) {
            if (Schema::hasColumn('instructions', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
            if (Schema::hasColumn('instructions', 'issued_at')) {
                $table->dropColumn('issued_at');
            }
            if (Schema::hasColumn('instructions', 'coordinator_id')) {
                $table->dropConstrainedForeignId('coordinator_id');
            }
            if (Schema::hasColumn('instructions', 'kind')) {
                $table->dropColumn('kind');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'task_type_id')) {
                $table->dropConstrainedForeignId('task_type_id');
            }
        });

        Schema::dropIfExists('task_types');
    }
};
