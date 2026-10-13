<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_importances', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('color')->default('primary');
            $table->unsignedSmallInteger('rank')->default(0);
            $table->boolean('banner_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('announcement_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('validation_circuit')->default('direct');
            $table->foreignId('default_importance_id')->nullable()->constrained('announcement_importances')->nullOnDelete();
            $table->string('default_confidentiality')->default('general');
            $table->unsignedInteger('retention_days')->nullable();
            $table->boolean('acknowledgement_required')->default(false);
            $table->boolean('revalidate_on_change')->default(false);
            $table->boolean('notify_on_publish')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('category_id')->constrained('announcement_categories')->restrictOnDelete();
            $table->foreignId('importance_id')->constrained('announcement_importances')->restrictOnDelete();
            $table->foreignId('structure_id')->constrained('structures')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cover_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->longText('body')->nullable();
            $table->string('confidentiality')->default('general')->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedTinyInteger('approval_step')->default(0);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('acknowledgement_required')->default(false);
            $table->boolean('banner_enabled')->default(false);
            $table->unsignedSmallInteger('banner_priority')->default(0);
            $table->timestamp('banner_starts_at')->nullable();
            $table->timestamp('banner_ends_at')->nullable();
            $table->boolean('banner_scroll')->default(true);
            $table->unsignedSmallInteger('banner_duration_seconds')->default(8);
            $table->boolean('dismissible')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('announcement_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('mode')->default('include');
            $table->string('target_type');
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_name')->nullable();
            $table->boolean('include_descendants')->default(false);
            $table->timestamps();
            $table->index(['announcement_id', 'mode']);
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('snapshotted_at')->nullable();
            $table->unique(['announcement_id', 'user_id', 'version'], 'ann_recipients_version_uq');
        });

        Schema::create('announcement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->longText('body')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['announcement_id', 'version']);
        });

        Schema::create('announcement_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['announcement_id', 'document_id'], 'ann_attachments_document_uq');
        });

        Schema::create('announcement_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unique(['announcement_id', 'user_id', 'version'], 'ann_reads_version_uq');
        });

        Schema::create('announcement_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('acknowledged_at')->nullable();
            $table->unique(['announcement_id', 'user_id', 'version'], 'ann_acks_version_uq');
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('dismissed_at')->nullable();
            $table->unique(['announcement_id', 'user_id', 'version'], 'ann_dismissals_version_uq');
        });

        Schema::create('announcement_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['announcement_id', 'user_id'], 'ann_favorites_user_uq');
        });

        Schema::create('announcement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['announcement_id', 'created_at']);
        });

        Schema::create('announcement_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dedupe_key')->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_notification_logs');
        Schema::dropIfExists('announcement_events');
        Schema::dropIfExists('announcement_favorites');
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcement_acknowledgements');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcement_links');
        Schema::dropIfExists('announcement_attachments');
        Schema::dropIfExists('announcement_approvals');
        Schema::dropIfExists('announcement_revisions');
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcement_audiences');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('announcement_categories');
        Schema::dropIfExists('announcement_importances');
    }
};
