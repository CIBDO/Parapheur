<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_obligations')) {
            Schema::create('production_obligations', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('label');
                $table->text('description')->nullable();
                $table->string('category')->nullable()->index();
                $table->string('domain')->nullable();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('producer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deputy_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('calendar_id')->nullable()->constrained('sla_calendars')->nullOnDelete();
                $table->string('frequency')->default('daily');
                $table->json('schedule_config')->nullable();
                $table->time('deadline_time')->nullable();
                $table->string('timezone')->default('Africa/Bamako');
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('confidentiality')->default('normal');
                $table->string('deliverable_type')->default('files');
                $table->json('allowed_formats')->nullable();
                $table->unsignedTinyInteger('min_files')->default(1);
                $table->unsignedTinyInteger('max_files')->nullable();
                $table->unsignedInteger('max_file_size_kb')->nullable();
                $table->foreignId('template_document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->boolean('validation_required')->default(true);
                $table->string('validation_mode')->default('sequential');
                $table->boolean('auto_transmit')->default(false);
                $table->boolean('archive_enabled')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_obligation_histories')) {
            Schema::create('production_obligation_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_obligation_id')->constrained('production_obligations')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event');
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('production_obligation_actors')) {
            Schema::create('production_obligation_actors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_obligation_id')->constrained('production_obligations')->cascadeOnDelete();
                $table->string('role'); // contributor|validator|recipient
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->string('role_name')->nullable();
                $table->unsignedSmallInteger('step_order')->default(0);
                $table->timestamps();
                $table->index(['production_obligation_id', 'role']);
            });
        }

        if (! Schema::hasTable('production_reminder_rules')) {
            Schema::create('production_reminder_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_obligation_id')->constrained('production_obligations')->cascadeOnDelete();
                $table->string('offset_type'); // before|after|at_deadline
                $table->integer('offset_minutes')->default(0);
                $table->string('target')->default('producer'); // producer|deputy|validator|recipient|coordinator|hierarchy
                $table->boolean('escalate')->default(false);
                $table->unsignedSmallInteger('repeat_every_minutes')->nullable();
                $table->string('stop_when')->default('transmitted'); // deposited|validated|transmitted|closed
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_suspensions')) {
            Schema::create('production_suspensions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_obligation_id')->constrained('production_obligations')->cascadeOnDelete();
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->string('reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_occurrences')) {
            Schema::create('production_occurrences', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('production_obligation_id')->constrained('production_obligations')->cascadeOnDelete();
                $table->string('period_key');
                $table->date('reference_date');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->timestamp('deadline_at')->nullable()->index();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->string('status')->default('a_produire')->index();
                $table->timestamp('deposited_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('transmitted_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('delay_justification')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['production_obligation_id', 'period_key'], 'prod_occ_obligation_period_unique');
            });
        }

        if (! Schema::hasTable('production_files')) {
            Schema::create('production_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_occurrence_id')->constrained('production_occurrences')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('logical_version')->default(1);
                $table->string('description')->nullable();
                $table->string('status')->default('draft');
                $table->string('checksum')->nullable();
                $table->timestamp('transmitted_at')->nullable();
                $table->timestamps();
                $table->index(['production_occurrence_id', 'document_id']);
            });
        }

        if (! Schema::hasTable('production_validations')) {
            Schema::create('production_validations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_occurrence_id')->constrained('production_occurrences')->cascadeOnDelete();
                $table->unsignedSmallInteger('step_order')->default(1);
                $table->foreignId('validator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('decision'); // pending|approved|rejected|correction
                $table->text('motif')->nullable();
                $table->text('comment')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_transmissions')) {
            Schema::create('production_transmissions', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('production_occurrence_id')->constrained('production_occurrences')->cascadeOnDelete();
                $table->foreignId('transmitted_by')->constrained('users')->cascadeOnDelete();
                $table->string('channel')->default('app');
                $table->boolean('is_rectificative')->default(false);
                $table->foreignId('previous_transmission_id')->nullable()->constrained('production_transmissions')->nullOnDelete();
                $table->string('status')->default('sent');
                $table->timestamp('transmitted_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_transmission_recipients')) {
            Schema::create('production_transmission_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('production_transmission_id');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->string('resolution_source')->nullable();
                $table->string('delivery_status')->default('pending');
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamps();
                $table->foreign('production_transmission_id', 'prod_tx_recip_tx_fk')
                    ->references('id')->on('production_transmissions')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('production_transmission_files')) {
            Schema::create('production_transmission_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('production_transmission_id');
                $table->unsignedBigInteger('production_file_id');
                $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
                $table->timestamps();
                $table->foreign('production_transmission_id', 'prod_tx_files_tx_fk')
                    ->references('id')->on('production_transmissions')->cascadeOnDelete();
                $table->foreign('production_file_id', 'prod_tx_files_file_fk')
                    ->references('id')->on('production_files')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('production_folders')) {
            Schema::create('production_folders', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->string('title');
                $table->string('kind')->default('manual'); // manual|template|daily_treasury
                $table->date('period_date')->nullable()->index();
                $table->string('period_key')->nullable();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_folder_items')) {
            Schema::create('production_folder_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_folder_id')->constrained('production_folders')->cascadeOnDelete();
                $table->foreignId('production_occurrence_id')->constrained('production_occurrences')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['production_folder_id', 'production_occurrence_id'], 'prod_folder_occ_unique');
            });
        }

        if (! Schema::hasTable('production_folder_templates')) {
            Schema::create('production_folder_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('label');
                $table->json('obligation_codes')->nullable();
                $table->string('frequency')->default('daily');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_events')) {
            Schema::create('production_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_occurrence_id')->nullable()->constrained('production_occurrences')->nullOnDelete();
                $table->foreignId('production_obligation_id')->nullable()->constrained('production_obligations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event');
                $table->json('properties')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('production_reminder_logs')) {
            Schema::create('production_reminder_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_occurrence_id')->constrained('production_occurrences')->cascadeOnDelete();
                $table->foreignId('production_reminder_rule_id')->nullable()->constrained('production_reminder_rules')->nullOnDelete();
                $table->string('kind');
                $table->string('dedupe_key')->unique();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_generation_runs')) {
            Schema::create('production_generation_runs', function (Blueprint $table) {
                $table->id();
                $table->date('target_date');
                $table->string('status')->default('running');
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_generation_runs');
        Schema::dropIfExists('production_reminder_logs');
        Schema::dropIfExists('production_events');
        Schema::dropIfExists('production_folder_templates');
        Schema::dropIfExists('production_folder_items');
        Schema::dropIfExists('production_folders');
        Schema::dropIfExists('production_transmission_files');
        Schema::dropIfExists('production_transmission_recipients');
        Schema::dropIfExists('production_transmissions');
        Schema::dropIfExists('production_validations');
        Schema::dropIfExists('production_files');
        Schema::dropIfExists('production_occurrences');
        Schema::dropIfExists('production_suspensions');
        Schema::dropIfExists('production_reminder_rules');
        Schema::dropIfExists('production_obligation_actors');
        Schema::dropIfExists('production_obligation_histories');
        Schema::dropIfExists('production_obligations');
    }
};
