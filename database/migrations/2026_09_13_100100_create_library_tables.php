<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bibliographic_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('reference_type_id')->constrained('reference_types')->restrictOnDelete();
            $table->string('title');
            $table->string('institutional_author')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->date('publication_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('publisher')->nullable();
            $table->string('organization')->nullable();
            $table->string('country')->nullable();
            $table->string('language')->nullable();
            $table->text('abstract')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_title')->nullable();
            $table->timestamp('accessed_at')->nullable();
            $table->string('doi')->nullable();
            $table->string('isbn')->nullable();
            $table->string('issn')->nullable();
            $table->string('external_id')->nullable();
            $table->string('visibility')->default('private');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('content_hash')->nullable();
            $table->string('publication_status')->nullable()->default('personal');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'workspace_id']);
            $table->index('content_hash');
        });

        Schema::create('reference_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->timestamps();
        });

        Schema::create('reference_author_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_id')->constrained('bibliographic_references')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('reference_authors')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['reference_id', 'author_id']);
        });

        Schema::create('reference_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('visibility')->default('private');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reference_collection_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('reference_collections')->cascadeOnDelete();
            $table->foreignId('reference_id')->constrained('bibliographic_references')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['collection_id', 'reference_id']);
        });

        Schema::create('reference_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('reference_tag_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_id')->constrained('bibliographic_references')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('reference_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reference_id', 'tag_id']);
        });

        Schema::create('reference_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_id')->constrained('bibliographic_references')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->unique(['reference_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_notes');
        Schema::dropIfExists('reference_tag_links');
        Schema::dropIfExists('reference_tags');
        Schema::dropIfExists('reference_collection_links');
        Schema::dropIfExists('reference_collections');
        Schema::dropIfExists('reference_author_links');
        Schema::dropIfExists('reference_authors');
        Schema::dropIfExists('bibliographic_references');
        Schema::dropIfExists('reference_types');
    }
};
