<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archive_storage_locations')) {
            Schema::create('archive_storage_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->string('type', 32); // site|building|room|zone|rack|bay|face|shelf|slot|cabinet
                $table->string('code', 64);
                $table->string('name');
                $table->string('full_code', 255);
                $table->string('status', 32)->default('disponible'); // disponible|occupe|indisponible|quarantaine
                $table->decimal('capacity_linear_meters', 10, 2)->nullable();
                $table->decimal('occupied_linear_meters', 10, 2)->default(0);
                $table->unsignedInteger('capacity_boxes')->nullable();
                $table->unsignedInteger('occupied_boxes')->default(0);
                $table->decimal('width_cm', 8, 2)->nullable();
                $table->decimal('height_cm', 8, 2)->nullable();
                $table->decimal('depth_cm', 8, 2)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['structure_id', 'full_code'], 'archive_locations_structure_fullcode_unique');
                $table->index(['parent_id', 'type']);
                $table->index(['structure_id', 'status']);
            });
        }

        if (! Schema::hasTable('archive_boxes')) {
            Schema::create('archive_boxes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('label')->nullable();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('producer_unit_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('fonds_id')->nullable()->constrained('archive_fonds')->nullOnDelete();
                $table->foreignId('series_id')->nullable()->constrained('archive_series')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->string('typology', 64)->nullable();
                $table->string('physical_state', 32)->default('bon'); // bon|moyen|degrade|quarantaine
                $table->string('status', 32)->default('en_magasin'); // en_magasin|en_pret|en_transfert|eliminee
                $table->date('date_start')->nullable();
                $table->date('date_end')->nullable();
                $table->unsignedInteger('folder_count')->default(0);
                $table->decimal('linear_meters', 8, 2)->nullable();
                $table->decimal('width_cm', 8, 2)->nullable();
                $table->decimal('height_cm', 8, 2)->nullable();
                $table->decimal('depth_cm', 8, 2)->nullable();
                $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('deposited_at')->nullable();
                $table->unsignedSmallInteger('retention_years')->nullable();
                $table->string('final_disposition', 64)->nullable();
                $table->string('qr_token', 64)->unique();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['structure_id', 'status']);
                $table->index(['location_id']);
            });
        }

        if (! Schema::hasTable('archive_box_contents')) {
            Schema::create('archive_box_contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('box_id')->constrained('archive_boxes')->cascadeOnDelete();
                $table->foreignId('record_id')->nullable()->constrained('archive_records')->nullOnDelete();
                $table->string('folder_code', 64)->nullable();
                $table->string('title')->nullable();
                $table->date('date_start')->nullable();
                $table->date('date_end')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['box_id', 'record_id'], 'archive_box_contents_box_record_unique');
            });
        }

        if (! Schema::hasTable('archive_movements')) {
            Schema::create('archive_movements', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('movement_type', 64); // entree|affectation|deplacement|transfert_salle|transfert_structure|sortie|pret|retour|quarantaine|reintegration|transfert_definitif|elimination
                $table->foreignId('box_id')->nullable()->constrained('archive_boxes')->nullOnDelete();
                $table->foreignId('record_id')->nullable()->constrained('archive_records')->nullOnDelete();
                $table->foreignId('from_location_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->foreignId('to_location_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('motif')->nullable();
                $table->text('notes')->nullable();
                $table->string('justification_path')->nullable();
                $table->timestamp('moved_at');
                $table->timestamps();

                $table->index(['box_id', 'moved_at']);
                $table->index(['movement_type']);
            });
        }

        if (! Schema::hasTable('archive_loans')) {
            Schema::create('archive_loans', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('status', 32)->default('demande'); // demande|verifie|valide|prepare|sorti|remis|en_retard|retourne|clos|refuse
                $table->foreignId('box_id')->nullable()->constrained('archive_boxes')->nullOnDelete();
                $table->foreignId('record_id')->nullable()->constrained('archive_records')->nullOnDelete();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('purpose')->nullable();
                $table->unsignedSmallInteger('loan_days')->default(7);
                $table->date('due_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('prepared_at')->nullable();
                $table->timestamp('checked_out_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->string('condition_out', 32)->nullable();
                $table->string('condition_in', 32)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'due_at']);
                $table->index(['requester_id']);
            });
        }

        if (! Schema::hasTable('archive_inventories')) {
            Schema::create('archive_inventories', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('title');
                $table->string('status', 32)->default('brouillon'); // brouillon|en_cours|en_validation|clos
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedInteger('expected_count')->default(0);
                $table->unsignedInteger('found_count')->default(0);
                $table->unsignedInteger('missing_count')->default(0);
                $table->unsignedInteger('unexpected_count')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('archive_inventory_items')) {
            Schema::create('archive_inventory_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_id')->constrained('archive_inventories')->cascadeOnDelete();
                $table->foreignId('box_id')->nullable()->constrained('archive_boxes')->nullOnDelete();
                $table->foreignId('expected_location_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->foreignId('found_location_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->string('scanned_code', 128)->nullable();
                $table->string('result', 32)->default('attendu'); // attendu|trouve|manquant|deplace|inattendu
                $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('scanned_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['inventory_id', 'result']);
            });
        }

        if (! Schema::hasColumn('archive_records', 'box_id')) {
            Schema::table('archive_records', function (Blueprint $table) {
                $table->foreignId('box_id')->nullable()->after('transfer_id')->constrained('archive_boxes')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->after('box_id')->constrained('archive_storage_locations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('archive_records', 'box_id')) {
            Schema::table('archive_records', function (Blueprint $table) {
                $table->dropConstrainedForeignId('box_id');
                $table->dropConstrainedForeignId('location_id');
            });
        }

        Schema::dropIfExists('archive_inventory_items');
        Schema::dropIfExists('archive_inventories');
        Schema::dropIfExists('archive_loans');
        Schema::dropIfExists('archive_movements');
        Schema::dropIfExists('archive_box_contents');
        Schema::dropIfExists('archive_boxes');
        Schema::dropIfExists('archive_storage_locations');
    }
};
