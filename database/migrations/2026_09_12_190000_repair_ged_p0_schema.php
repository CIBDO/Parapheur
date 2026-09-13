<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Répare l’état DB si un rollback partiel a retiré des objets GED P0
 * tout en laissant la migration marquée comme « Ran ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_categories')) {
            Schema::create('document_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('classification_nodes')) {
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
        }

        Schema::table('documents', function (Blueprint $table) {
            $cols = [
                'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
                'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
                'summary' => fn (Blueprint $t) => $t->text('summary')->nullable(),
                'dossier_number' => fn (Blueprint $t) => $t->string('dossier_number')->nullable()->index(),
                'origin' => fn (Blueprint $t) => $t->string('origin')->default('parapheur')->index(),
                'language' => fn (Blueprint $t) => $t->string('language')->nullable(),
                'source' => fn (Blueprint $t) => $t->string('source')->nullable(),
                'archive_status' => fn (Blueprint $t) => $t->string('archive_status')->default('actif')->index(),
                'retention_years' => fn (Blueprint $t) => $t->unsignedSmallInteger('retention_years')->nullable(),
                'text_extraction_status' => fn (Blueprint $t) => $t->string('text_extraction_status')->nullable(),
                'ocr_status' => fn (Blueprint $t) => $t->string('ocr_status')->nullable(),
                'ocr_processed_at' => fn (Blueprint $t) => $t->timestamp('ocr_processed_at')->nullable(),
                'indexed_at' => fn (Blueprint $t) => $t->timestamp('indexed_at')->nullable(),
                'antivirus_status' => fn (Blueprint $t) => $t->string('antivirus_status')->nullable(),
            ];

            foreach ($cols as $name => $adder) {
                if (! Schema::hasColumn('documents', $name)) {
                    $adder($table);
                }
            }
        });

        if (! Schema::hasColumn('documents', 'category_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('documents', 'owner_structure_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreignId('owner_structure_id')->nullable()->constrained('structures')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('documents', 'classification_node_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreignId('classification_node_id')->nullable()->constrained('classification_nodes')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('document_versions', 'is_official')) {
            Schema::table('document_versions', function (Blueprint $table) {
                $table->boolean('is_official')->default(false);
            });
        }
        if (! Schema::hasColumn('document_versions', 'change_source')) {
            Schema::table('document_versions', function (Blueprint $table) {
                $table->string('change_source')->nullable();
            });
        }
    }

    public function down(): void
    {
        // no-op repair
    }
};
