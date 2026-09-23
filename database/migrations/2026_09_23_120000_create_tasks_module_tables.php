<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructions', function (Blueprint $table) {
            if (! Schema::hasColumn('instructions', 'reference')) {
                $table->string('reference')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('instructions', 'confidentiality')) {
                $table->string('confidentiality')->default('normal')->after('priority');
            }
            if (! Schema::hasColumn('instructions', 'source_kind')) {
                $table->string('source_kind')->default('manual')->after('confidentiality');
            }
            if (! Schema::hasColumn('instructions', 'source_type')) {
                $table->string('source_type')->nullable()->after('source_kind');
            }
            if (! Schema::hasColumn('instructions', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('instructions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('due_date');
            }
            if (! Schema::hasColumn('instructions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('instructions', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('instructions', 'is_personal')) {
                $table->boolean('is_personal')->default(false)->after('cancel_reason');
            }
        });

        try {
            Schema::table('instructions', function (Blueprint $table) {
                $table->index(['source_type', 'source_id']);
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('instructions', function (Blueprint $table) {
                $table->index('is_personal');
            });
        } catch (\Throwable) {
        }

        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->foreignId('instruction_id')->nullable()->constrained('instructions')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('validator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('taken_charge_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('brouillon')->index();
                $table->string('priority')->default('normale');
                $table->string('confidentiality')->default('normal');
                $table->string('source_kind')->default('manual');
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedTinyInteger('progress')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('taken_charge_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->boolean('is_personal')->default(false);
                $table->json('tags')->nullable();
                $table->timestamps();
                $table->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('task_contributors')) {
            Schema::create('task_contributors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role')->default('contributor');
                $table->timestamps();
                $table->unique(['task_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('task_comments')) {
            Schema::create('task_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_attachments')) {
            Schema::create('task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->string('disk');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('checksum')->nullable();
                $table->string('kind')->default('attachment');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_histories')) {
            Schema::create('task_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->text('comment')->nullable();
                $table->json('properties')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('task_completions')) {
            Schema::create('task_completions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('summary')->nullable();
                $table->text('result')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_validations')) {
            Schema::create('task_validations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('validator_id')->constrained('users')->cascadeOnDelete();
                $table->string('decision');
                $table->text('motif')->nullable();
                $table->text('comment')->nullable();
                $table->timestamp('new_due_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_reminders')) {
            Schema::create('task_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->timestamp('remind_at')->nullable();
                $table->string('kind');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['task_id', 'kind']);
                $table->index('remind_at');
            });
        }

        if (! Schema::hasTable('instruction_recipients')) {
            Schema::create('instruction_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('instruction_id')->constrained('instructions')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->timestamps();
                $table->index(['instruction_id', 'user_id']);
                $table->index(['instruction_id', 'structure_id']);
            });
        }

        if (! Schema::hasTable('task_dependencies')) {
            Schema::create('task_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('related_task_id')->constrained('tasks')->cascadeOnDelete();
                $table->string('relation');
                $table->timestamps();
                $table->unique(['task_id', 'related_task_id', 'relation']);
            });
        }

        if (Schema::hasTable('ticket_tasks') && ! Schema::hasColumn('ticket_tasks', 'task_id')) {
            Schema::table('ticket_tasks', function (Blueprint $table) {
                $table->foreignId('task_id')->nullable()->after('instruction_id')
                    ->constrained('tasks')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_tasks') && Schema::hasColumn('ticket_tasks', 'task_id')) {
            Schema::table('ticket_tasks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('task_id');
            });
        }

        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('instruction_recipients');
        Schema::dropIfExists('task_reminders');
        Schema::dropIfExists('task_validations');
        Schema::dropIfExists('task_completions');
        Schema::dropIfExists('task_histories');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_contributors');
        Schema::dropIfExists('tasks');

        Schema::table('instructions', function (Blueprint $table) {
            $columns = [
                'reference',
                'confidentiality',
                'source_kind',
                'source_type',
                'source_id',
                'started_at',
                'cancelled_at',
                'cancel_reason',
                'is_personal',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('instructions', $column)
            ));

            if ($existing !== []) {
                try {
                    $table->dropIndex(['source_type', 'source_id']);
                } catch (\Throwable) {
                }
                try {
                    $table->dropIndex(['is_personal']);
                } catch (\Throwable) {
                }

                $table->dropColumn($existing);
            }
        });
    }
};
