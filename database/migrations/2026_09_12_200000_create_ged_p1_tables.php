<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'document_id']);
        });

        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['user_id', 'document_id']);
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::create('document_index_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
            $table->longText('content')->nullable();
            $table->string('extraction_method')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'document_version_id']);
        });

        Schema::create('document_retention_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('retention_years')->default(10);
            $table->string('final_disposition')->default('archiver'); // archiver|conserver|detruire_apres_validation
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('document_classification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->unsignedBigInteger('target_classification_node_id');
            $table->foreign('target_classification_node_id', 'dcr_target_node_fk')
                ->references('id')->on('classification_nodes')->cascadeOnDelete();
            $table->string('trigger_status')->default('valide'); // valide|traite|archive
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'legal_hold_at')) {
                $table->timestamp('legal_hold_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'legal_hold_reason')) {
                $table->string('legal_hold_reason')->nullable();
            }
            if (! Schema::hasColumn('documents', 'retention_rule_id')) {
                $table->unsignedBigInteger('retention_rule_id')->nullable();
            }
            if (! Schema::hasColumn('documents', 'retention_until')) {
                $table->date('retention_until')->nullable();
            }
        });

        if (Schema::hasColumn('documents', 'retention_rule_id')) {
            try {
                Schema::table('documents', function (Blueprint $table) {
                    $table->foreign('retention_rule_id', 'documents_retention_rule_fk')
                        ->references('id')->on('document_retention_rules')->nullOnDelete();
                });
            } catch (\Throwable) {
                // déjà présent
            }
        }

        // FULLTEXT MySQL/MariaDB uniquement (SQLite des tests : LIKE sur content)
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE document_index_contents ADD FULLTEXT document_index_contents_ft (content)');
            try {
                DB::statement('ALTER TABLE documents ADD FULLTEXT documents_meta_ft (reference, object, title, description)');
            } catch (\Throwable) {
                // Colonnes nullable / longueur : ignorer si le moteur refuse
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('ALTER TABLE documents DROP INDEX documents_meta_ft');
            } catch (\Throwable) {
            }
            try {
                DB::statement('ALTER TABLE document_index_contents DROP INDEX document_index_contents_ft');
            } catch (\Throwable) {
            }
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['retention_rule_id']);
            $table->dropColumn(['legal_hold_at', 'legal_hold_reason', 'retention_rule_id', 'retention_until']);
        });

        Schema::dropIfExists('document_classification_rules');
        Schema::dropIfExists('document_retention_rules');
        Schema::dropIfExists('document_index_contents');
        Schema::dropIfExists('document_views');
        Schema::dropIfExists('document_favorites');
    }
};
