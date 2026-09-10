<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointment_types')) {
            Schema::create('appointment_types', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->unsignedSmallInteger('default_duration_minutes')->default(30);
                $table->boolean('blocks_calendar')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('appointment_type_id')->nullable()->constrained('appointment_types')->nullOnDelete();
                $table->string('subject');
                $table->text('reason')->nullable();
                $table->text('description')->nullable();

                $table->date('requested_date')->nullable();
                $table->time('requested_start_time')->nullable();
                $table->time('requested_end_time')->nullable();
                $table->json('proposed_availabilities')->nullable();

                $table->dateTime('start_at')->nullable();
                $table->dateTime('end_at')->nullable();
                $table->dateTime('previous_start_at')->nullable();
                $table->dateTime('previous_end_at')->nullable();

                $table->string('location')->nullable();
                $table->string('meeting_mode')->default('presentiel');
                $table->string('visio_url')->nullable();
                $table->string('external_address')->nullable();
                $table->string('external_host_organization')->nullable();
                $table->string('external_contact')->nullable();
                $table->text('logistics_info')->nullable();

                $table->string('origin_type')->default('secretariat');
                $table->string('origin_reference')->nullable();
                $table->string('intake_channel')->nullable();

                $table->string('requester_type')->nullable();
                $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requester_name')->nullable();
                $table->string('requester_organization')->nullable();
                $table->string('requester_position')->nullable();
                $table->string('requester_email')->nullable();
                $table->string('requester_phone')->nullable();

                $table->foreignId('director_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('secretariat_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('redirected_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('redirected_to_structure_id')->nullable()->constrained('structures')->nullOnDelete();

                $table->string('priority')->default('normale');
                $table->string('confidentiality')->default('normal');
                $table->string('status')->default('brouillon');
                $table->boolean('blocks_calendar')->default(true);

                $table->text('context_note')->nullable();
                $table->text('points_to_discuss')->nullable();
                $table->text('decision_note')->nullable();
                $table->text('internal_note')->nullable();
                $table->text('result_summary')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->boolean('rejection_communicable')->default(false);
                $table->text('cancellation_reason')->nullable();
                $table->text('reschedule_reason')->nullable();
                $table->text('complement_request')->nullable();

                $table->foreignId('parent_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
                $table->foreignId('converted_meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
                $table->foreignId('linked_document_id')->nullable()->constrained('documents')->nullOnDelete();

                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('validated_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('archived_at')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'start_at']);
                $table->index(['director_id', 'start_at']);
                $table->index(['confidentiality', 'status']);
                $table->index('requested_date');
            });
        }

        if (! Schema::hasTable('appointment_participants')) {
            Schema::create('appointment_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('participation_type')->default('interne');
                $table->string('role')->default('participant');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('organization')->nullable();
                $table->string('position')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('expected_presence')->default(true);
                $table->boolean('actual_presence')->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('appointment_documents')) {
            Schema::create('appointment_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->string('kind')->default('piece_jointe');
                $table->string('label')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->foreignId('attached_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['appointment_id', 'document_id']);
            });
        }

        if (! Schema::hasTable('appointment_notes')) {
            Schema::create('appointment_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
                $table->string('visibility')->default('institutionnelle');
                $table->text('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appointment_followups')) {
            Schema::create('appointment_followups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->string('kind');
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('priority')->default('normale');
                $table->date('due_date')->nullable();
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('instruction_id')->nullable()->constrained('instructions')->nullOnDelete();
                $table->foreignId('followup_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
                $table->foreignId('followup_meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
                $table->string('status')->default('ouverte');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('calendar_unavailabilities')) {
            Schema::create('calendar_unavailabilities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind')->default('indisponibilite');
                $table->string('title');
                $table->text('description')->nullable();
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('location')->nullable();
                $table->string('confidentiality')->default('normal');
                $table->boolean('blocks_calendar')->default(true);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'start_at', 'end_at']);
            });
        }

        if (! Schema::hasTable('appointment_reminder_logs')) {
            Schema::create('appointment_reminder_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->string('reminder_key');
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->unique(['appointment_id', 'reminder_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminder_logs');
        Schema::dropIfExists('calendar_unavailabilities');
        Schema::dropIfExists('appointment_followups');
        Schema::dropIfExists('appointment_notes');
        Schema::dropIfExists('appointment_documents');
        Schema::dropIfExists('appointment_participants');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_types');
    }
};
