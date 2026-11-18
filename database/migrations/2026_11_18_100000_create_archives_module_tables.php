<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'archive_holds',
            'archive_records',
            'archive_transfer_items',
            'archive_transfers',
            'archive_classification_nodes',
            'archive_series',
            'archive_fonds',
        ];

        // Reprise après coupure MySQL : tables orphelines partielles → reset propre
        $existing = collect($tables)->filter(fn (string $t) => Schema::hasTable($t));
        if ($existing->isNotEmpty() && $existing->count() < count($tables)) {
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }
        }

        if (! Schema::hasTable('archive_fonds')) {
            Schema::create('archive_fonds', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64);
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['code']);
                $table->index(['structure_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('archive_series')) {
            Schema::create('archive_series', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fonds_id')->constrained('archive_fonds')->cascadeOnDelete();
                $table->string('code', 64);
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['fonds_id', 'code']);
            });
        }

        if (! Schema::hasTable('archive_classification_nodes')) {
            Schema::create('archive_classification_nodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('archive_classification_nodes')->nullOnDelete();
                $table->foreignId('fonds_id')->nullable()->constrained('archive_fonds')->nullOnDelete();
                $table->foreignId('series_id')->nullable()->constrained('archive_series')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('level', 32)->default('serie');
                $table->string('code', 64);
                $table->string('name');
                $table->string('path', 512);
                $table->unsignedSmallInteger('depth')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['path', 'version'], 'archive_class_nodes_path_version_unique');
                $table->index(['parent_id', 'code']);
            });
        }

        if (! Schema::hasTable('archive_transfers')) {
            Schema::create('archive_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('status', 32)->default('brouillon')->index();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('producer_unit_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('controlled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title')->nullable();
                $table->string('typology', 64)->nullable();
                $table->text('notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('controlled_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('taken_over_at')->nullable();
                $table->timestamp('conserved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('archive_transfer_items')) {
            Schema::create('archive_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transfer_id')->constrained('archive_transfers')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
                $table->string('title')->nullable();
                $table->string('checksum', 128)->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['transfer_id', 'document_id']);
            });
        }

        if (! Schema::hasTable('archive_records')) {
            Schema::create('archive_records', function (Blueprint $table) {
                $table->id();
                $table->string('permanent_id', 64)->unique();
                $table->string('reference', 128)->nullable()->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 32)->default('en_versement')->index();
                $table->string('confidentiality', 32)->default('normal')->index();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('producer_unit_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('fonds_id')->nullable()->constrained('archive_fonds')->nullOnDelete();
                $table->foreignId('series_id')->nullable()->constrained('archive_series')->nullOnDelete();
                $table->foreignId('classification_node_id')->nullable()->constrained('archive_classification_nodes')->nullOnDelete();
                $table->foreignId('transfer_id')->nullable()->constrained('archive_transfers')->nullOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
                $table->string('checksum', 128)->nullable();
                $table->string('typology', 64)->nullable();
                $table->date('date_start')->nullable();
                $table->date('date_end')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('conserved_at')->nullable();
                $table->timestamps();

                $table->index(['structure_id', 'status']);
                $table->index(['document_id']);
            });
        }

        if (! Schema::hasTable('archive_holds')) {
            Schema::create('archive_holds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('record_id')->constrained('archive_records')->cascadeOnDelete();
                $table->string('reason');
                $table->string('hold_type', 64)->default('administratif');
                $table->foreignId('placed_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('placed_at');
                $table->timestamp('lifted_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['record_id', 'lifted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_holds');
        Schema::dropIfExists('archive_records');
        Schema::dropIfExists('archive_transfer_items');
        Schema::dropIfExists('archive_transfers');
        Schema::dropIfExists('archive_classification_nodes');
        Schema::dropIfExists('archive_series');
        Schema::dropIfExists('archive_fonds');
    }
};
