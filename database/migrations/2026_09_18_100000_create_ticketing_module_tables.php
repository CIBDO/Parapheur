<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('pauses_sla')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(4);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_impact_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_urgency_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_priority_matrix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impact_id')->constrained('ticket_impact_levels')->cascadeOnDelete();
            $table->foreignId('urgency_id')->constrained('ticket_urgency_levels')->cascadeOnDelete();
            $table->foreignId('priority_id')->constrained('ticket_priorities')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['impact_id', 'urgency_id']);
        });

        Schema::create('ticket_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_tags', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sla_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->json('weekdays')->nullable();
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->boolean('is_24_7')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sla_calendar_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_calendar_id')->constrained('sla_calendars')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_closed')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['sla_calendar_id', 'date']);
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('priority_id')->constrained('ticket_priorities')->cascadeOnDelete();
            $table->foreignId('sla_calendar_id')->nullable()->constrained('sla_calendars')->nullOnDelete();
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedTinyInteger('warning_percent')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_team_id')->constrained('support_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('level', 10)->default('N1');
            $table->boolean('is_lead')->default(false);
            $table->timestamps();
            $table->unique(['support_team_id', 'user_id']);
        });

        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_catalog_id')->constrained('service_catalogs')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();
            $table->foreignId('ticket_category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $table->foreignId('default_priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_item_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_item_id')->constrained('service_items')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('label');
            $table->string('field_type', 30)->default('text');
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_item_id', 'code']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 40)->index();
            $table->string('confidentiality', 20)->default('NORMAL')->index();
            $table->string('source', 50)->nullable();
            $table->string('location_label')->nullable();
            $table->foreignId('channel_id')->nullable()->constrained('ticket_channels')->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();
            $table->foreignId('ticket_category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained('service_items')->nullOnDelete();
            $table->foreignId('impact_id')->nullable()->constrained('ticket_impact_levels')->nullOnDelete();
            $table->foreignId('urgency_id')->nullable()->constrained('ticket_urgency_levels')->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->unsignedBigInteger('application_id')->nullable()->index();
            $table->unsignedBigInteger('asset_id')->nullable()->index();
            $table->unsignedBigInteger('instruction_id')->nullable()->index();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->json('custom_fields')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('reopen_count')->default(0);
            $table->boolean('is_major_incident')->default(false);
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('due_response_at')->nullable()->index();
            $table->timestamp('due_resolution_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['support_team_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index(['requester_id', 'status']);
        });

        Schema::create('ticket_tag_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('ticket_tag_id')->constrained('ticket_tags')->cascadeOnDelete();
            $table->unique(['ticket_id', 'ticket_tag_id']);
        });

        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->default('assign');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_priority_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('from_priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
            $table->foreignId('to_priority_id')->constrained('ticket_priorities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('minutes')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('worked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_satisfactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::create('ticket_slas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->timestamp('response_met_at')->nullable();
            $table->timestamp('resolution_met_at')->nullable();
            $table->timestamp('warning_sent_at')->nullable();
            $table->timestamp('response_breached_at')->nullable();
            $table->timestamp('resolution_breached_at')->nullable();
            $table->timestamps();
            $table->unique('ticket_id');
        });

        Schema::create('ticket_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('from_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 30)->default('functional');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticketing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_settings');
        Schema::dropIfExists('ticket_escalations');
        Schema::dropIfExists('ticket_slas');
        Schema::dropIfExists('ticket_satisfactions');
        Schema::dropIfExists('ticket_worklogs');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_priority_histories');
        Schema::dropIfExists('ticket_status_histories');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_assignments');
        Schema::dropIfExists('ticket_tag_ticket');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('service_item_fields');
        Schema::dropIfExists('service_items');
        Schema::dropIfExists('service_catalogs');
        Schema::dropIfExists('support_team_members');
        Schema::dropIfExists('support_teams');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('sla_calendar_exceptions');
        Schema::dropIfExists('sla_calendars');
        Schema::dropIfExists('ticket_tags');
        Schema::dropIfExists('ticket_channels');
        Schema::dropIfExists('ticket_priority_matrix');
        Schema::dropIfExists('ticket_urgency_levels');
        Schema::dropIfExists('ticket_impact_levels');
        Schema::dropIfExists('ticket_priorities');
        Schema::dropIfExists('ticket_statuses');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('ticket_types');
    }
};
