<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->boolean('is_access_boundary')->default(false)->after('is_active');
            $table->foreignId('boundary_structure_id')->nullable()->after('is_access_boundary')->constrained('structures')->nullOnDelete();
        });

        Schema::create('structure_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->boolean('can_sign')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['structure_id', 'code']);
        });

        Schema::create('user_structure_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_position_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['structure_id', 'status']);
        });

        Schema::create('position_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('holder_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('structure_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 50);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('organizational_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('scope_type', 30);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['structure_id', 'scope_type']);
        });

        Schema::create('user_work_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organizational_role_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('correspondences', function (Blueprint $table) {
            $table->foreignId('unit_structure_id')->nullable()->after('structure_id')->constrained('structures')->nullOnDelete();
        });

        Schema::create('correspondence_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visibility', 30)->default('shared_document');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->timestamps();

            $table->index(['correspondence_id', 'structure_id']);
            $table->index(['correspondence_id', 'user_id']);
        });

        Schema::table('correspondence_events', function (Blueprint $table) {
            $table->string('visibility', 20)->default('partage')->after('comment');
        });

        app(\App\Services\Organization\StructureBoundaryService::class)->markDefaultBoundaries();
        app(\App\Services\Organization\StructureBoundaryService::class)->refreshAll();
    }

    public function down(): void
    {
        Schema::table('correspondence_events', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });

        Schema::dropIfExists('correspondence_participants');

        Schema::table('correspondences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_structure_id');
        });

        Schema::dropIfExists('user_work_contexts');
        Schema::dropIfExists('organizational_role_assignments');
        Schema::dropIfExists('structure_change_logs');
        Schema::dropIfExists('position_delegations');
        Schema::dropIfExists('user_structure_assignments');
        Schema::dropIfExists('structure_positions');

        Schema::table('structures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boundary_structure_id');
            $table->dropColumn('is_access_boundary');
        });
    }
};
