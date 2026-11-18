<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archive_retention_rules')) {
            Schema::create('archive_retention_rules', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('typology', 64)->nullable();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('series_id')->nullable()->constrained('archive_series')->nullOnDelete();
                $table->string('trigger_event', 64)->default('cloture'); // cloture|versement|date_fin|decision
                $table->unsignedSmallInteger('current_years')->default(0);
                $table->unsignedSmallInteger('intermediate_years')->default(0);
                $table->string('final_disposition', 64)->default('conservation_definitive');
                // conservation_definitive|transfert|reevaluation|elimination
                $table->string('legal_basis')->nullable();
                $table->string('authority')->nullable();
                $table->unsignedSmallInteger('version')->default(1);
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_validated')->default(false);
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();

                $table->unique(['code', 'version'], 'archive_retention_rules_code_version_unique');
                $table->index(['is_active', 'is_validated']);
            });
        }

        if (! Schema::hasTable('archive_retention_events')) {
            Schema::create('archive_retention_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('record_id')->constrained('archive_records')->cascadeOnDelete();
                $table->foreignId('rule_id')->nullable()->constrained('archive_retention_rules')->nullOnDelete();
                $table->string('event_type', 64); // applied|due|reevaluated|hold|disposition_proposed
                $table->date('due_at')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['record_id', 'event_type']);
                $table->index(['due_at']);
            });
        }

        if (! Schema::hasTable('archive_communication_requests')) {
            Schema::create('archive_communication_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('status', 32)->default('demande'); // demande|autorisee|refusee|expiree|revoquee
                $table->foreignId('record_id')->constrained('archive_records')->cascadeOnDelete();
                $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('purpose')->nullable();
                $table->string('access_mode', 32)->default('consultation'); // consultation|telechargement|extrait
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('decision_notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'expires_at']);
            });
        }

        if (! Schema::hasTable('archive_access_grants')) {
            Schema::create('archive_access_grants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('communication_request_id')->nullable()->constrained('archive_communication_requests')->nullOnDelete();
                $table->foreignId('record_id')->constrained('archive_records')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('ability', 32)->default('view'); // view|download
                $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('granted_at');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['record_id', 'user_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('archive_disposition_requests')) {
            Schema::create('archive_disposition_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('status', 32)->default('proposition'); 
                // proposition|en_validation|autorisee|refusee|executee|annulee
                $table->string('disposition_type', 64)->default('elimination');
                // conservation_definitive|transfert|reevaluation|elimination
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('justification')->nullable();
                $table->string('legal_basis')->nullable();
                $table->string('pv_reference', 128)->nullable();
                $table->string('evidence_path')->nullable();
                $table->timestamp('proposed_at')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('refused_at')->nullable();
                $table->text('refusal_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'structure_id']);
            });
        }

        if (! Schema::hasTable('archive_disposition_items')) {
            Schema::create('archive_disposition_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('disposition_request_id')->constrained('archive_disposition_requests')->cascadeOnDelete();
                $table->foreignId('record_id')->constrained('archive_records')->cascadeOnDelete();
                $table->foreignId('box_id')->nullable()->constrained('archive_boxes')->nullOnDelete();
                $table->string('status', 32)->default('inscrit'); // inscrit|bloque_gel|execute|exclu
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['disposition_request_id', 'record_id'], 'archive_disposition_items_unique');
            });
        }

        // Recréation si échec précédent (index MySQL > 64 car. sans nom court).
        if (Schema::hasTable('archive_disposition_approvals')) {
            $indexNames = collect(Schema::getIndexes('archive_disposition_approvals'))->pluck('name');
            if (! $indexNames->contains('arch_disp_appr_req_decision_idx')) {
                Schema::drop('archive_disposition_approvals');
            }
        }

        if (! Schema::hasTable('archive_disposition_approvals')) {
            Schema::create('archive_disposition_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('disposition_request_id')->constrained('archive_disposition_requests')->cascadeOnDelete();
                $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
                $table->string('decision', 32); // autorise|refuse
                $table->text('comment')->nullable();
                $table->timestamp('decided_at');
                $table->timestamps();

                $table->index(['disposition_request_id', 'decision'], 'arch_disp_appr_req_decision_idx');
            });
        }

        if (Schema::hasTable('archive_records') && ! Schema::hasColumn('archive_records', 'retention_rule_id')) {
            Schema::table('archive_records', function (Blueprint $table) {
                $table->foreignId('retention_rule_id')->nullable()->after('location_id')
                    ->constrained('archive_retention_rules')->nullOnDelete();
                $table->date('disposition_due_at')->nullable()->after('retention_rule_id');
                $table->string('final_disposition', 64)->nullable()->after('disposition_due_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('archive_records', 'retention_rule_id')) {
            Schema::table('archive_records', function (Blueprint $table) {
                $table->dropConstrainedForeignId('retention_rule_id');
                $table->dropColumn(['disposition_due_at', 'final_disposition']);
            });
        }

        Schema::dropIfExists('archive_disposition_approvals');
        Schema::dropIfExists('archive_disposition_items');
        Schema::dropIfExists('archive_disposition_requests');
        Schema::dropIfExists('archive_access_grants');
        Schema::dropIfExists('archive_communication_requests');
        Schema::dropIfExists('archive_retention_events');
        Schema::dropIfExists('archive_retention_rules');
    }
};
