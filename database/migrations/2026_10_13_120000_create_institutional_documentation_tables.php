<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_domains', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('kind')->default('institutional'); // institutional|structure|restricted
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path')->nullable()->index();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->boolean('inherit_permissions')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->string('default_publication_workflow')->default('controlled'); // simple|controlled|hierarchical
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['parent_id', 'code']);
            $table->index(['kind', 'is_archived']);
        });

        Schema::create('document_folder_access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_folder_id')->constrained('document_folders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->cascadeOnDelete();
            $table->string('role_name')->nullable();
            $table->string('ability')->default('view');
            $table->boolean('is_exception')->default(false);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['document_folder_id', 'ability']);
        });

        Schema::create('document_publication_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('step')->default(1);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // submit|approve|reject|publish|return|archive
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });

        Schema::table('document_types', function (Blueprint $table) {
            if (! Schema::hasColumn('document_types', 'scope')) {
                $table->string('scope')->default('general')->after('name'); // general|institutional|parapheur
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'folder_id')) {
                $table->foreignId('folder_id')->nullable()->after('classification_node_id')
                    ->constrained('document_folders')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'domain_id')) {
                $table->foreignId('domain_id')->nullable()->after('category_id')
                    ->constrained('document_domains')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'publication_status')) {
                $table->string('publication_status')->nullable()->after('archive_status')
                    ->default('brouillon');
            }
            if (! Schema::hasColumn('documents', 'publication_workflow')) {
                $table->string('publication_workflow')->nullable()->after('publication_status');
            }
            if (! Schema::hasColumn('documents', 'document_owner_id')) {
                $table->foreignId('document_owner_id')->nullable()->after('author_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('documents', 'effective_at')) {
                $table->date('effective_at')->nullable()->after('document_date');
            }
            if (! Schema::hasColumn('documents', 'expires_at')) {
                $table->date('expires_at')->nullable()->after('effective_at');
            }
            if (! Schema::hasColumn('documents', 'exercise_year')) {
                $table->unsignedSmallInteger('exercise_year')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('documents', 'period_label')) {
                $table->string('period_label')->nullable()->after('exercise_year');
            }
            if (! Schema::hasColumn('documents', 'extra_metadata')) {
                $table->json('extra_metadata')->nullable()->after('keywords');
            }

            $table->index('publication_status');
            $table->index('folder_id');
            $table->index('domain_id');
            $table->index('exercise_year');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            foreach ([
                'folder_id', 'domain_id', 'document_owner_id',
            ] as $fk) {
                if (Schema::hasColumn('documents', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach ([
                'publication_status', 'publication_workflow', 'published_at',
                'effective_at', 'expires_at', 'exercise_year', 'period_label', 'extra_metadata',
            ] as $col) {
                if (Schema::hasColumn('documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'scope')) {
                $table->dropColumn('scope');
            }
        });

        Schema::dropIfExists('document_publication_approvals');
        Schema::dropIfExists('document_folder_access_rules');
        Schema::dropIfExists('document_folders');
        Schema::dropIfExists('document_domains');
    }
};
