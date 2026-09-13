<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('classification_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('classification_nodes')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('path')->nullable()->index();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['parent_id', 'code']);
        });

        Schema::create('document_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        Schema::create('document_tag_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_tag_id')->constrained('document_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'document_tag_id']);
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('target_document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('relation_type')->default('related_to');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['source_document_id', 'target_document_id', 'relation_type'], 'document_links_unique');
        });

        Schema::create('document_access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->cascadeOnDelete();
            $table->string('role_name')->nullable();
            $table->string('ability')->default('view'); // view|download|comment|edit|share
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'ability']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('title')->nullable()->after('object');
            $table->text('description')->nullable()->after('title');
            $table->text('summary')->nullable()->after('description');
            $table->string('dossier_number')->nullable()->after('reference');
            $table->foreignId('category_id')->nullable()->after('document_type_id')->constrained('document_categories')->nullOnDelete();
            $table->foreignId('owner_structure_id')->nullable()->after('structure_id')->constrained('structures')->nullOnDelete();
            $table->foreignId('classification_node_id')->nullable()->after('owner_structure_id')->constrained('classification_nodes')->nullOnDelete();
            $table->string('origin')->default('parapheur')->after('keywords'); // parapheur|ged|meeting|appointment|instruction
            $table->string('language')->nullable()->after('origin');
            $table->string('source')->nullable()->after('language');
            $table->string('archive_status')->default('actif')->after('archived_at'); // actif|a_archiver|archive|gele|a_verser|verse
            $table->unsignedSmallInteger('retention_years')->nullable()->after('archive_status');
            $table->string('text_extraction_status')->nullable()->after('retention_years');
            $table->string('ocr_status')->nullable()->after('text_extraction_status');
            $table->timestamp('ocr_processed_at')->nullable()->after('ocr_status');
            $table->timestamp('indexed_at')->nullable()->after('ocr_processed_at');
            $table->string('antivirus_status')->nullable()->after('indexed_at');

            $table->index('dossier_number');
            $table->index('origin');
            $table->index('archive_status');
            $table->index('category_id');
            $table->index('classification_node_id');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->boolean('is_official')->default(false)->after('is_main');
            $table->string('change_source')->nullable()->after('change_note'); // upload|onlyoffice|restore|import
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn(['is_official', 'change_source']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['owner_structure_id']);
            $table->dropForeign(['classification_node_id']);
            $table->dropColumn([
                'title',
                'description',
                'summary',
                'dossier_number',
                'category_id',
                'owner_structure_id',
                'classification_node_id',
                'origin',
                'language',
                'source',
                'archive_status',
                'retention_years',
                'text_extraction_status',
                'ocr_status',
                'ocr_processed_at',
                'indexed_at',
                'antivirus_status',
            ]);
        });

        Schema::dropIfExists('document_access_rules');
        Schema::dropIfExists('document_links');
        Schema::dropIfExists('document_tag_document');
        Schema::dropIfExists('document_tags');
        Schema::dropIfExists('classification_nodes');
        Schema::dropIfExists('document_categories');
    }
};
