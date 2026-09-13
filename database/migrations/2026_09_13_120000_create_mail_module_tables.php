<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiels
        Schema::create('correspondence_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('correspondence_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('correspondence_qualifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('correspondence_assignment_actions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Correspondants
        Schema::create('correspondents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // personne_physique, personne_morale, structure_interne, structure_externe
            $table->string('name');
            $table->string('function')->nullable();
            $table->string('organization')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('name');
        });

        Schema::create('correspondent_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondent_id')->constrained('correspondents')->cascadeOnDelete();
            $table->string('type', 50); // email, phone, fax, mobile
            $table->string('value');
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['correspondent_id', 'type']);
        });

        // Séquences de numérotation
        Schema::create('numbering_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50); // ARR, DEP, BT, FC
            $table->year('year');
            $table->string('prefix', 20);
            $table->integer('padding')->default(6);
            $table->integer('last_value')->default(0);
            $table->boolean('reset_yearly')->default(true);
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->timestamps();

            // Note: MySQL unique with nullable columns - NULL values are considered distinct
            $table->unique(['code', 'year']);
            $table->index('structure_id');
        });

        // Correspondances (courriers)
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            // Direction et médium
            $table->enum('direction', ['entrant', 'sortant', 'interne']);
            $table->enum('medium', ['physique', 'electronique', 'hybride']);
            $table->string('status', 50);

            // Numérotation
            $table->string('arrival_number', 50)->nullable()->unique();
            $table->string('departure_number', 50)->nullable()->unique();

            // Dates
            $table->timestamp('received_at')->nullable();
            $table->date('correspondence_date')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->date('due_date')->nullable();

            // Contenu
            $table->string('external_reference')->nullable();
            $table->string('subject');
            $table->text('summary')->nullable();
            $table->text('observations')->nullable();

            // Référentiels
            $table->foreignId('channel_id')->nullable()->constrained('correspondence_channels')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('correspondence_categories')->nullOnDelete();
            $table->foreignId('qualification_id')->nullable()->constrained('correspondence_qualifications')->nullOnDelete();

            // Priorité et confidentialité (alignés sur DocumentPriority, DocumentConfidentiality)
            $table->string('priority', 50)->nullable();
            $table->string('confidentiality', 50)->nullable();

            // Rattachements
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Métadonnées
            $table->integer('piece_count')->default(1);
            $table->json('keywords')->nullable();
            $table->boolean('requires_reply')->default(false);
            $table->boolean('is_registered')->default(false);

            // Liens
            $table->foreignId('reply_to_correspondence_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->foreignId('parapheur_document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['direction', 'status']);
            $table->index(['received_at', 'correspondence_date']);
            $table->index(['structure_id', 'status']);
            $table->index('registered_by');
            $table->index('owner_user_id');
            $table->index('due_date');
        });

        // Parties (expéditeurs, destinataires)
        Schema::create('correspondence_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('correspondent_id')->nullable()->constrained('correspondents')->nullOnDelete();
            $table->enum('role', ['from', 'to', 'cc', 'ampliation', 'info']);
            $table->string('name')->nullable();
            $table->string('function')->nullable();
            $table->string('organization')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['correspondence_id', 'role']);
        });

        // Affectations
        Schema::create('correspondence_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('action_id')->nullable()->constrained('correspondence_assignment_actions')->nullOnDelete();
            $table->foreignId('instruction_id')->nullable()->constrained('instructions')->nullOnDelete();
            $table->string('status', 50)->default('transmis');
            $table->text('instruction_text')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('taken_charge_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index(['correspondence_id', 'status']);
            $table->index(['to_user_id', 'status']);
            $table->index('to_structure_id');
        });

        // Événements (append-only log)
        Schema::create('correspondence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['correspondence_id', 'created_at']);
            $table->index('event_type');
        });

        // Liens entre correspondances
        Schema::create('correspondence_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('target_correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->string('link_type', 50); // reponse, suite, annexe, reference
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_correspondence_id', 'target_correspondence_id', 'link_type'], 'correspondence_links_unique');
            $table->index(['source_correspondence_id', 'link_type']);
        });

        // Rappels
        Schema::create('correspondence_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('reminder_date');
            $table->string('type', 50)->default('deadline'); // deadline, follow_up
            $table->text('note')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['correspondence_id', 'reminder_date']);
            $table->index(['user_id', 'reminder_date', 'is_sent']);
        });

        // Expéditions
        Schema::create('correspondence_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->timestamp('dispatched_at');
            $table->string('method', 50); // courrier, coursier, fax, email, plateforme
            $table->string('tracking_number')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['correspondence_id', 'dispatched_at']);
        });

        // Accusés de réception
        Schema::create('correspondence_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->string('acknowledged_by_name')->nullable();
            $table->string('method', 50)->nullable(); // signature, email, plateforme
            $table->text('observations')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('correspondence_id');
        });

        // Modèles de documents (créé AVANT les bordereaux pour éviter les FKs circulaires)
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('kind', 50); // bordereau_transmission, fiche_circulation, accuse_reception, lettre, note, formulaire, autre
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('confidentiality', 50)->nullable();
            $table->boolean('is_active')->default(true);
            // current_published_version_id sera ajouté après la création de document_template_versions
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['kind', 'is_active']);
            $table->index('structure_id');
        });

        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('status', 50)->default('brouillon'); // brouillon, en_revision, valide, publie, remplace
            $table->string('disk', 50);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->bigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'version_number']);
            $table->index(['status', 'published_at']);
        });

        // Ajout du FK current_published_version_id maintenant que document_template_versions existe
        Schema::table('document_templates', function (Blueprint $table) {
            $table->foreignId('current_published_version_id')->nullable()->after('is_active')->constrained('document_template_versions')->nullOnDelete();
        });

        // Bordereaux de transmission (créé APRÈS document_template_versions)
        Schema::create('transmission_slips', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->nullable()->unique();
            $table->string('status', 50)->default('brouillon');
            $table->foreignId('from_structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('to_structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('nature', 100)->nullable(); // pour_traitement, pour_info, pour_avis
            $table->text('observations')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('document_template_versions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('transmitted_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['from_structure_id', 'to_structure_id']);
        });

        Schema::create('transmission_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmission_slip_id')->constrained('transmission_slips')->cascadeOnDelete();
            $table->foreignId('correspondence_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('object')->nullable();
            $table->integer('piece_count')->default(1);
            $table->text('observations')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['transmission_slip_id', 'display_order']);
        });

        // Fiches de circulation
        Schema::create('circulation_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->string('number', 50)->nullable();
            $table->string('status', 50)->default('en_cours'); // en_cours, cloture
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('document_template_versions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['correspondence_id', 'status']);
        });

        // Génération de documents
        Schema::create('document_generation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_version_id')->constrained('document_template_versions')->cascadeOnDelete();
            $table->foreignId('correspondence_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->foreignId('transmission_slip_id')->nullable()->constrained('transmission_slips')->nullOnDelete();
            $table->foreignId('generated_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['template_version_id', 'created_at']);
        });

        // Logs d'impression
        Schema::create('document_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('correspondence_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->foreignId('transmission_slip_id')->nullable()->constrained('transmission_slips')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('printer_name')->nullable();
            $table->integer('page_count')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_print_logs');
        Schema::dropIfExists('document_generation_events');
        Schema::dropIfExists('circulation_sheets');
        Schema::dropIfExists('transmission_slip_items');
        Schema::dropIfExists('transmission_slips');
        
        // Supprimer le FK avant de drop les tables
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropForeign(['current_published_version_id']);
            $table->dropColumn('current_published_version_id');
        });
        
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('correspondence_acknowledgements');
        Schema::dropIfExists('correspondence_dispatches');
        Schema::dropIfExists('correspondence_reminders');
        Schema::dropIfExists('correspondence_links');
        Schema::dropIfExists('correspondence_events');
        Schema::dropIfExists('correspondence_assignments');
        Schema::dropIfExists('correspondence_parties');
        Schema::dropIfExists('correspondences');
        Schema::dropIfExists('numbering_sequences');
        Schema::dropIfExists('correspondent_contacts');
        Schema::dropIfExists('correspondents');
        Schema::dropIfExists('correspondence_assignment_actions');
        Schema::dropIfExists('correspondence_qualifications');
        Schema::dropIfExists('correspondence_categories');
        Schema::dropIfExists('correspondence_channels');
    }
};
