<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('visibility')->default('private');
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->unsignedBigInteger('storage_used_bytes')->default(0);
            $table->timestamp('storage_recalculated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'owner_id']);
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('workspace_folders')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('path');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'parent_id']);
            $table->index(['workspace_id', 'path']);
        });

        Schema::create('workspace_document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('workspace_folders')->nullOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'document_id']);
            $table->index('folder_id');
        });

        Schema::create('workspace_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('workspace_folders')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->cascadeOnDelete();
            $table->foreignId('grantee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ability');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['grantee_user_id', 'ability']);
            $table->index(['workspace_id', 'document_id']);
            $table->index(['workspace_id', 'folder_id']);
        });

        Schema::create('workspace_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('favoritable_type');
            $table->unsignedBigInteger('favoritable_id');
            $table->timestamps();

            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'workspace_favorites_unique');
            $table->index(['favoritable_type', 'favoritable_id']);
        });

        Schema::create('workspace_storage_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('default_personal_quota_bytes')->default(10737418240);
            $table->unsignedBigInteger('default_shared_quota_bytes')->default(21474836480);
            $table->unsignedBigInteger('max_upload_bytes')->default(104857600);
            $table->json('allowed_extensions')->nullable();
            $table->json('denied_extensions')->nullable();
            $table->unsignedSmallInteger('trash_retention_days')->default(30);
            $table->unsignedTinyInteger('warn_threshold_percent')->default(80);
            $table->boolean('block_on_exceed')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_quota_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->unsignedBigInteger('quota_bytes');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_quota_overrides');
        Schema::dropIfExists('workspace_storage_policies');
        Schema::dropIfExists('workspace_favorites');
        Schema::dropIfExists('workspace_shares');
        Schema::dropIfExists('workspace_document_links');
        Schema::dropIfExists('workspace_folders');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
