<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30); // requester|observer|assignee|supplier
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id', 'role']);
            $table->index(['ticket_id', 'role']);
        });

        Schema::create('ticket_asset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'asset_id']);
        });

        Schema::create('ticket_solutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('solution_type', 30)->default('solution'); // solution|workaround
            $table->string('status', 30)->default('proposed'); // proposed|accepted|refused
            $table->text('content');
            $table->text('refusal_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
        });

        Schema::create('ticket_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('instruction_id')->nullable()->constrained('instructions')->nullOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('status', 20)->default('todo'); // todo|doing|done
            $table->string('category', 100)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamp('planned_start_at')->nullable();
            $table->timestamp('planned_end_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('is_active');
            $table->string('approval_template', 100)->nullable()->after('requires_approval');
        });

        Schema::create('ticket_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('status', 30)->default('pending'); // pending|accepted|refused
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
        });

        Schema::table('sla_policies', function (Blueprint $table) {
            $table->foreignId('escalate_to_team_id')->nullable()->after('warning_percent')
                ->constrained('support_teams')->nullOnDelete();
            $table->boolean('auto_escalate_on_breach')->default(false)->after('escalate_to_team_id');
        });

        Schema::create('ola_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('support_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->foreignId('sla_calendar_id')->nullable()->constrained('sla_calendars')->nullOnDelete();
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedTinyInteger('warning_percent')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ticket_olas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('ola_policy_id')->constrained('ola_policies')->cascadeOnDelete();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('response_met_at')->nullable();
            $table->timestamp('resolution_met_at')->nullable();
            $table->timestamp('response_breached_at')->nullable();
            $table->timestamp('resolution_breached_at')->nullable();
            $table->timestamp('warning_sent_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->timestamps();
            $table->unique('ticket_id');
        });

        Schema::create('ticket_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ticket_worklog_id')->nullable()->constrained('ticket_worklogs')->nullOnDelete();
            $table->string('name');
            $table->string('cost_type', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->text('note')->nullable();
            $table->date('cost_date')->nullable();
            $table->timestamps();
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_costs');
        Schema::dropIfExists('ticket_olas');
        Schema::dropIfExists('ola_policies');

        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escalate_to_team_id');
            $table->dropColumn('auto_escalate_on_breach');
        });

        Schema::dropIfExists('ticket_approvals');

        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn(['requires_approval', 'approval_template']);
        });

        Schema::dropIfExists('ticket_tasks');
        Schema::dropIfExists('ticket_solutions');
        Schema::dropIfExists('ticket_asset');
        Schema::dropIfExists('ticket_actors');
    }
};
