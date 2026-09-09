<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('title')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('title');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->foreignId('structure_id')->nullable()->after('is_active');
            $table->string('position_title')->nullable()->after('structure_id');
        });

        Schema::create('structure_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('structure_type_id')->nullable()->constrained('structure_types')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('structure_id')->references('id')->on('structures')->nullOnDelete();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->string('object');
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->foreignId('structure_id')->constrained('structures');
            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('current_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('brouillon')->index();
            $table->string('priority')->default('normale')->index();
            $table->string('confidentiality')->default('normal')->index();
            $table->string('expected_action')->default('consultation');
            $table->date('document_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->json('keywords')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->boolean('is_main')->default(true);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->text('change_note')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->string('kind')->default('piece_jointe'); // piece_jointe | annexe | complement
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('kind')->default('predefini'); // libre | predefini
            $table->boolean('is_active')->default(true);
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name');
            $table->string('role_name')->nullable();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('expected_action')->default('validation');
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->unique(['workflow_id', 'step_order']);
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->string('kind')->default('libre');
            $table->string('status')->default('en_cours');
            $table->unsignedSmallInteger('current_step_order')->default(1);
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_transmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->string('expected_action');
            $table->string('folder')->default('a_traiter');
            $table->string('status')->default('pending'); // pending | seen | done | returned
            $table->text('message')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'folder', 'status']);
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->foreignId('transmission_id')->nullable()->constrained('document_transmissions')->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('delegator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('kind')->default('general'); // general | avis | observation
            $table->text('body');
            $table->boolean('is_instruction_source')->default(false);
            $table->timestamps();
        });

        Schema::create('visas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('delegator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('vised_at');
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('delegator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision')->default('valide'); // valide | rejete
            $table->text('comment')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        Schema::create('instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('meeting_decision_id')->nullable();
            $table->foreignId('issuer_id')->constrained('users');
            $table->foreignId('assignee_id')->constrained('users');
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('priority')->default('normale');
            $table->string('status')->default('a_faire')->index();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('instruction_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instruction_id')->constrained('instructions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('status')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('meeting_date');
            $table->time('meeting_time')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('chair_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->text('agenda')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('planifiee');
            $table->timestamps();
        });

        Schema::table('instructions', function (Blueprint $table) {
            // meeting_decision_id FK added after meeting_decisions exists
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('participant');
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::create('meeting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('agenda_label')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'document_id']);
        });

        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status')->default('ouverte');
            $table->timestamps();
        });

        Schema::table('instructions', function (Blueprint $table) {
            $table->foreign('meeting_decision_id')->references('id')->on('meeting_decisions')->nullOnDelete();
        });

        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('document_type_ids')->nullable();
            $table->json('allowed_actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('delegations');
        Schema::table('instructions', function (Blueprint $table) {
            $table->dropForeign(['meeting_decision_id']);
        });
        Schema::dropIfExists('meeting_decisions');
        Schema::dropIfExists('meeting_documents');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('instruction_updates');
        Schema::dropIfExists('instructions');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('visas');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('document_transmissions');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('document_attachments');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['structure_id']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'title',
                'phone',
                'is_active',
                'structure_id',
                'position_title',
            ]);
        });
        Schema::dropIfExists('structures');
        Schema::dropIfExists('structure_types');
    }
};
