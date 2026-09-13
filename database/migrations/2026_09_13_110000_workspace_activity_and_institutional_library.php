<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action'); // folder_created, document_added, version_published, member_added, shared, ...
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::table('bibliographic_references', function (Blueprint $table) {
            if (! Schema::hasColumn('bibliographic_references', 'proposed_at')) {
                $table->timestamp('proposed_at')->nullable()->after('publication_status');
            }
            if (! Schema::hasColumn('bibliographic_references', 'proposed_by')) {
                $table->foreignId('proposed_by')->nullable()->after('proposed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bibliographic_references', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('proposed_by');
            }
            if (! Schema::hasColumn('bibliographic_references', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bibliographic_references', 'review_note')) {
                $table->string('review_note')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('bibliographic_references', 'structure_id')) {
                $table->foreignId('structure_id')->nullable()->after('workspace_id')->constrained('structures')->nullOnDelete();
            }
        });

        Schema::table('reference_collections', function (Blueprint $table) {
            if (! Schema::hasColumn('reference_collections', 'is_institutional')) {
                $table->boolean('is_institutional')->default(false)->after('visibility');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_activities');

        Schema::table('bibliographic_references', function (Blueprint $table) {
            foreach (['proposed_at', 'proposed_by', 'reviewed_at', 'reviewed_by', 'review_note', 'structure_id'] as $col) {
                if (Schema::hasColumn('bibliographic_references', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('reference_collections', function (Blueprint $table) {
            if (Schema::hasColumn('reference_collections', 'is_institutional')) {
                $table->dropColumn('is_institutional');
            }
        });
    }
};
