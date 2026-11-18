<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('archive_records')) {
            Schema::table('archive_records', function (Blueprint $table) {
                if (! Schema::hasColumn('archive_records', 'medium')) {
                    $table->string('medium', 32)->default('electronique')->after('typology');
                }
                if (! Schema::hasColumn('archive_records', 'physical_reference')) {
                    $table->string('physical_reference', 128)->nullable()->after('medium');
                }
            });
        }

        if (Schema::hasTable('archive_transfers')) {
            Schema::table('archive_transfers', function (Blueprint $table) {
                if (! Schema::hasColumn('archive_transfers', 'source_module')) {
                    $table->string('source_module', 64)->nullable()->after('notes');
                }
                if (! Schema::hasColumn('archive_transfers', 'source_ref')) {
                    $table->string('source_ref', 128)->nullable()->after('source_module');
                }
                if (! Schema::hasColumn('archive_transfers', 'medium')) {
                    $table->string('medium', 32)->default('electronique')->after('source_ref');
                }
            });
        }

        if (! Schema::hasTable('archive_imports')) {
            Schema::create('archive_imports', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('import_type', 32); // records|physical
                $table->string('status', 32)->default('preview'); // preview|committed|failed
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('original_filename')->nullable();
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('valid_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->unsignedInteger('committed_count')->default(0);
                $table->json('column_mapping')->nullable();
                $table->json('preview_rows')->nullable();
                $table->json('report')->nullable();
                $table->timestamp('committed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'structure_id'], 'arch_imports_status_structure_idx');
            });
        }

        if (! Schema::hasTable('archive_preventive_events')) {
            Schema::create('archive_preventive_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('archive_storage_locations')->nullOnDelete();
                $table->foreignId('box_id')->nullable()->constrained('archive_boxes')->nullOnDelete();
                $table->string('event_type', 64);
                // incident|infiltration|degradation|infestation|incendie|securite|nettoyage|intervention
                $table->string('severity', 32)->default('info'); // info|warning|critical
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('occurred_at');
                $table->timestamp('resolved_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['event_type', 'severity'], 'arch_prev_events_type_sev_idx');
                $table->index(['location_id', 'occurred_at'], 'arch_prev_events_loc_at_idx');
            });
        }

        if (! Schema::hasTable('archive_climate_readings')) {
            Schema::create('archive_climate_readings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('archive_storage_locations')->cascadeOnDelete();
                $table->decimal('temperature_c', 5, 2)->nullable();
                $table->decimal('humidity_pct', 5, 2)->nullable();
                $table->string('source', 32)->default('manual'); // manual|sensor
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('measured_at');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['location_id', 'measured_at'], 'arch_climate_loc_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_climate_readings');
        Schema::dropIfExists('archive_preventive_events');
        Schema::dropIfExists('archive_imports');

        if (Schema::hasTable('archive_transfers')) {
            Schema::table('archive_transfers', function (Blueprint $table) {
                $drop = [];
                foreach (['source_module', 'source_ref', 'medium'] as $col) {
                    if (Schema::hasColumn('archive_transfers', $col)) {
                        $drop[] = $col;
                    }
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('archive_records')) {
            Schema::table('archive_records', function (Blueprint $table) {
                if (Schema::hasColumn('archive_records', 'medium')) {
                    $table->dropColumn(['medium', 'physical_reference']);
                }
            });
        }
    }
};
