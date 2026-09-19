<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_type_id')->nullable()->constrained('asset_types')->nullOnDelete();
            $table->string('inventory_number', 100)->nullable()->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('status', 40)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('OPEN')->index();
            $table->text('root_cause')->nullable();
            $table->text('workaround')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('problem_ticket_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained('problems')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unique(['problem_id', 'ticket_id']);
        });

        Schema::create('known_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->nullable()->constrained('problems')->nullOnDelete();
            $table->string('title');
            $table->text('symptoms')->nullable();
            $table->text('workaround')->nullable();
            $table->text('solution')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_article_ticket_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unique(['knowledge_article_id', 'ticket_id'], 'ka_ticket_unique');
        });

        Schema::create('ticket_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('related_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('relation_type', 40)->default('related');
            $table->timestamps();
            $table->unique(['ticket_id', 'related_ticket_id', 'relation_type'], 'ticket_rel_unique');
        });

        Schema::create('ticket_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('database_enabled')->default(true);
            $table->boolean('mail_enabled')->default(true);
            $table->json('muted_events')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('application_id')->references('id')->on('applications')->nullOnDelete();
            $table->foreign('asset_id')->references('id')->on('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
            $table->dropForeign(['asset_id']);
        });

        Schema::dropIfExists('ticket_notification_preferences');
        Schema::dropIfExists('ticket_relations');
        Schema::dropIfExists('knowledge_article_ticket_links');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('knowledge_categories');
        Schema::dropIfExists('known_errors');
        Schema::dropIfExists('problem_ticket_links');
        Schema::dropIfExists('problems');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_types');
        Schema::dropIfExists('applications');
    }
};
