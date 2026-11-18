<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archive_packages')) {
            Schema::create('archive_packages', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('package_type', 16); // sip|aip|dip
                $table->string('status', 32)->default('ready'); // drafting|ready|sealed|replicated|failed
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('transfer_id')->nullable()->constrained('archive_transfers')->nullOnDelete();
                $table->foreignId('record_id')->nullable()->constrained('archive_records')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('disk', 32)->default('archives');
                $table->string('path')->nullable();
                $table->string('manifest_checksum', 128)->nullable();
                $table->string('package_checksum', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('format_profile', 64)->nullable(); // original|pdfa_target
                $table->boolean('is_immutable')->default(false);
                $table->timestamp('sealed_at')->nullable();
                $table->timestamp('replicated_at')->nullable();
                $table->string('replica_disk', 32)->nullable();
                $table->string('replica_path')->nullable();
                $table->json('manifest')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['package_type', 'status']);
                $table->index(['record_id']);
            });
        }

        if (! Schema::hasTable('archive_package_items')) {
            Schema::create('archive_package_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->constrained('archive_packages')->cascadeOnDelete();
                $table->foreignId('record_id')->nullable()->constrained('archive_records')->nullOnDelete();
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
                $table->string('relative_path');
                $table->string('checksum', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('mime_type', 128)->nullable();
                $table->timestamps();

                $table->index(['package_id']);
            });
        }

        if (! Schema::hasTable('archive_integrity_checks')) {
            Schema::create('archive_integrity_checks', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 32)->default('record'); // record|package|batch
                $table->foreignId('record_id')->nullable()->constrained('archive_records')->nullOnDelete();
                $table->foreignId('package_id')->nullable()->constrained('archive_packages')->nullOnDelete();
                $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete();
                $table->string('expected_checksum', 128)->nullable();
                $table->string('actual_checksum', 128)->nullable();
                $table->string('result', 32); // ok|mismatch|missing|error
                $table->text('message')->nullable();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('checked_at');
                $table->timestamps();

                $table->index(['result', 'checked_at']);
                $table->index(['record_id']);
            });
        }

        if (! Schema::hasTable('archive_preservation_events')) {
            Schema::create('archive_preservation_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('record_id')->nullable()->constrained('archive_records')->nullOnDelete();
                $table->foreignId('package_id')->nullable()->constrained('archive_packages')->nullOnDelete();
                $table->string('event_type', 64);
                // packaged_sip|packaged_aip|packaged_dip|integrity_ok|integrity_fail|replicated|format_migration|restitution
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['event_type', 'occurred_at']);
            });
        }

        if (Schema::hasTable('archive_records') && ! Schema::hasColumn('archive_records', 'preservation_status')) {
            Schema::table('archive_records', function (Blueprint $table) {
                $table->string('preservation_status', 32)->default('active')->after('final_disposition');
                // active|packaged|verified|at_risk|migrated
                $table->timestamp('last_integrity_at')->nullable()->after('preservation_status');
                $table->string('last_integrity_result', 32)->nullable()->after('last_integrity_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('archive_records', 'preservation_status')) {
            Schema::table('archive_records', function (Blueprint $table) {
                $table->dropColumn(['preservation_status', 'last_integrity_at', 'last_integrity_result']);
            });
        }

        Schema::dropIfExists('archive_preservation_events');
        Schema::dropIfExists('archive_integrity_checks');
        Schema::dropIfExists('archive_package_items');
        Schema::dropIfExists('archive_packages');
    }
};
