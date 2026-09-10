<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_types')) {
            Schema::create('meeting_types', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meeting_recurrences')) {
            Schema::create('meeting_recurrences', function (Blueprint $table) {
                $table->id();
                $table->string('frequency');
                $table->unsignedSmallInteger('interval')->default(1);
                $table->unsignedTinyInteger('weekday')->nullable();
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->unsignedSmallInteger('occurrences_limit')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'reference')) {
                $table->string('reference')->nullable()->after('id');
            }
            if (! Schema::hasColumn('meetings', 'object')) {
                $table->string('object')->nullable()->after('title');
            }
            if (! Schema::hasColumn('meetings', 'description')) {
                $table->text('description')->nullable()->after('object');
            }
            if (! Schema::hasColumn('meetings', 'meeting_type_id')) {
                $table->foreignId('meeting_type_id')->nullable()->after('description')->constrained('meeting_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'end_time')) {
                $table->time('end_time')->nullable()->after('meeting_time');
            }
            if (! Schema::hasColumn('meetings', 'visio_url')) {
                $table->string('visio_url')->nullable()->after('location');
            }
            if (! Schema::hasColumn('meetings', 'structure_id')) {
                $table->foreignId('structure_id')->nullable()->after('chair_id')->constrained('structures')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'secretary_id')) {
                $table->foreignId('secretary_id')->nullable()->after('structure_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'organizer_id')) {
                $table->foreignId('organizer_id')->nullable()->after('secretary_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'confidentiality')) {
                $table->string('confidentiality')->default('normal')->after('status');
            }
            if (! Schema::hasColumn('meetings', 'priority')) {
                $table->string('priority')->default('normale')->after('confidentiality');
            }
            if (! Schema::hasColumn('meetings', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('priority');
            }
            if (! Schema::hasColumn('meetings', 'parent_meeting_id')) {
                $table->foreignId('parent_meeting_id')->nullable()->after('is_recurring')->constrained('meetings')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'recurrence_id')) {
                $table->foreignId('recurrence_id')->nullable()->after('parent_meeting_id')->constrained('meeting_recurrences')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'observations')) {
                $table->text('observations')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'previous_meeting_date')) {
                $table->date('previous_meeting_date')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'previous_meeting_time')) {
                $table->time('previous_meeting_time')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'previous_location')) {
                $table->string('previous_location')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'ended_at')) {
                $table->timestamp('ended_at')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(0);
            }
            if (! Schema::hasColumn('meetings', 'current_agenda_item_id')) {
                $table->unsignedBigInteger('current_agenda_item_id')->nullable();
            }
            if (! Schema::hasColumn('meetings', 'convocation_document_id')) {
                $table->foreignId('convocation_document_id')->nullable()->constrained('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'minutes_document_id')) {
                $table->foreignId('minutes_document_id')->nullable()->constrained('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('meetings', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $this->addIndexIfMissing('meetings', 'meetings_status_index', ['status']);
        $this->addIndexIfMissing('meetings', 'meetings_meeting_date_index', ['meeting_date']);
        $this->addIndexIfMissing('meetings', 'meetings_structure_id_index', ['structure_id']);
        $this->addIndexIfMissing('meetings', 'meetings_chair_id_index', ['chair_id']);

        if (! Schema::hasTable('meeting_agenda_items')) {
            Schema::create('meeting_agenda_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
                $table->unsignedSmallInteger('item_number')->default(1);
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('confidentiality')->nullable();
                $table->string('status')->default('prevu');
                $table->text('preparatory_notes')->nullable();
                $table->boolean('is_follow_up')->default(false);
                $table->timestamps();

                $table->index(['meeting_id', 'sort_order']);
            });
        }

        if (! $this->foreignKeyExists('meetings', 'meetings_current_agenda_item_id_foreign')) {
            try {
                Schema::table('meetings', function (Blueprint $table) {
                    $table->foreign('current_agenda_item_id')->references('id')->on('meeting_agenda_items')->nullOnDelete();
                });
            } catch (\Throwable) {
                // SQLite / FK déjà présente
            }
        }

        Schema::table('meeting_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_participants', 'participation_type')) {
                $table->string('participation_type')->default('interne')->after('role');
            }
            if (! Schema::hasColumn('meeting_participants', 'is_required')) {
                $table->boolean('is_required')->default(true);
            }
            if (! Schema::hasColumn('meeting_participants', 'invitation_status')) {
                $table->string('invitation_status')->default('invite');
            }
            if (! Schema::hasColumn('meeting_participants', 'confirmation_status')) {
                $table->string('confirmation_status')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'attendance_status')) {
                $table->string('attendance_status')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'external_name')) {
                $table->string('external_name')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'external_function')) {
                $table->string('external_function')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'external_structure')) {
                $table->string('external_structure')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'representative_id')) {
                $table->foreignId('representative_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('meeting_participants', 'representative_name')) {
                $table->string('representative_name')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'notified_at')) {
                $table->timestamp('notified_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'left_at')) {
                $table->timestamp('left_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'last_reminded_at')) {
                $table->timestamp('last_reminded_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_participants', 'observations')) {
                $table->text('observations')->nullable();
            }
        });

        // Rendre user_id nullable sans doctrine/dbal (invités externes).
        // L’unique (meeting_id, user_id) est conservé : MySQL autorise plusieurs NULL.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE meeting_participants MODIFY user_id BIGINT UNSIGNED NULL');
        }

        $this->addIndexIfMissing('meeting_participants', 'meeting_participants_invitation_status_index', ['invitation_status']);

        Schema::table('meeting_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_documents', 'agenda_item_id')) {
                $table->foreignId('agenda_item_id')->nullable()->after('document_id')->constrained('meeting_agenda_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('meeting_documents', 'kind')) {
                $table->string('kind')->default('document_de_travail')->after('agenda_item_id');
            }
        });
        $this->addIndexIfMissing('meeting_documents', 'meeting_documents_agenda_item_id_index', ['agenda_item_id']);

        Schema::table('meeting_decisions', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_decisions', 'reference')) {
                $table->string('reference')->nullable()->after('meeting_id');
            }
            if (! Schema::hasColumn('meeting_decisions', 'agenda_item_id')) {
                $table->foreignId('agenda_item_id')->nullable()->after('reference')->constrained('meeting_agenda_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('meeting_decisions', 'priority')) {
                $table->string('priority')->default('normale')->after('due_date');
            }
            if (! Schema::hasColumn('meeting_decisions', 'observations')) {
                $table->text('observations')->nullable();
            }
            if (! Schema::hasColumn('meeting_decisions', 'execution_comment')) {
                $table->text('execution_comment')->nullable();
            }
            if (! Schema::hasColumn('meeting_decisions', 'executed_at')) {
                $table->timestamp('executed_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_decisions', 'execution_declared_by')) {
                $table->foreignId('execution_declared_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('meeting_decisions', 'execution_validated_by')) {
                $table->foreignId('execution_validated_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('meeting_decisions', 'execution_validated_at')) {
                $table->timestamp('execution_validated_at')->nullable();
            }
            if (! Schema::hasColumn('meeting_decisions', 'justification_document_id')) {
                $table->foreignId('justification_document_id')->nullable()->constrained('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('meeting_decisions', 'last_reminded_at')) {
                $table->timestamp('last_reminded_at')->nullable();
            }
        });
        $this->addIndexIfMissing('meeting_decisions', 'meeting_decisions_status_index', ['status']);
        $this->addIndexIfMissing('meeting_decisions', 'meeting_decisions_due_date_index', ['due_date']);
        $this->addIndexIfMissing('meeting_decisions', 'meeting_decisions_assignee_id_index', ['assignee_id']);

        if (! Schema::hasTable('meeting_notes')) {
            Schema::create('meeting_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
                $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
                $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
                $table->string('visibility')->default('officielle');
                $table->string('section')->nullable();
                $table->text('body');
                $table->timestamps();

                $table->index(['meeting_id', 'visibility']);
                $table->index('author_id');
            });
        }

        if (! Schema::hasTable('meeting_recommendations')) {
            Schema::create('meeting_recommendations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
                $table->foreignId('agenda_item_id')->nullable()->constrained('meeting_agenda_items')->nullOnDelete();
                $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users');
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('status')->default('enregistree');
                $table->foreignId('converted_decision_id')->nullable()->constrained('meeting_decisions')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meeting_minutes')) {
            Schema::create('meeting_minutes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
                $table->string('kind')->default('cr_detaille');
                $table->unsignedSmallInteger('version_number')->default(1);
                $table->longText('body')->nullable();
                $table->json('payload')->nullable();
                $table->string('status')->default('brouillon');
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('diffused_at')->nullable();
                $table->timestamps();

                $table->index(['meeting_id', 'kind']);
            });
        }

        if (! Schema::hasTable('meeting_templates')) {
            Schema::create('meeting_templates', function (Blueprint $table) {
                $table->id();
                $table->string('kind');
                $table->string('name');
                $table->longText('body');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meeting_reminder_logs')) {
            Schema::create('meeting_reminder_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_id')->nullable()->constrained('meetings')->cascadeOnDelete();
                $table->foreignId('meeting_decision_id')->nullable()->constrained('meeting_decisions')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('kind');
                $table->string('slot');
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->unique(['meeting_id', 'user_id', 'kind', 'slot'], 'meeting_reminder_unique');
            });
        }

        DB::table('meeting_decisions')->where('status', 'ouverte')->update(['status' => 'a_faire']);

        if (! $this->indexExists('meetings', 'meetings_reference_unique')) {
            try {
                Schema::table('meetings', function (Blueprint $table) {
                    $table->unique('reference');
                });
            } catch (\Throwable) {
                // Index déjà présent (SQLite)
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reminder_logs');
        Schema::dropIfExists('meeting_templates');
        Schema::dropIfExists('meeting_minutes');
        Schema::dropIfExists('meeting_recommendations');
        Schema::dropIfExists('meeting_notes');

        if (Schema::hasColumn('meeting_decisions', 'agenda_item_id')) {
            Schema::table('meeting_decisions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('agenda_item_id');
            });
        }
        if (Schema::hasColumn('meeting_decisions', 'execution_declared_by')) {
            Schema::table('meeting_decisions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('execution_declared_by');
            });
        }
        if (Schema::hasColumn('meeting_decisions', 'execution_validated_by')) {
            Schema::table('meeting_decisions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('execution_validated_by');
            });
        }
        if (Schema::hasColumn('meeting_decisions', 'justification_document_id')) {
            Schema::table('meeting_decisions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('justification_document_id');
            });
        }

        Schema::table('meeting_decisions', function (Blueprint $table) {
            $cols = array_filter([
                'reference', 'priority', 'observations', 'execution_comment',
                'executed_at', 'execution_validated_at', 'last_reminded_at',
            ], fn (string $col) => Schema::hasColumn('meeting_decisions', $col));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        if (Schema::hasColumn('meeting_documents', 'agenda_item_id')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->dropConstrainedForeignId('agenda_item_id');
            });
        }
        if (Schema::hasColumn('meeting_documents', 'kind')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }

        if ($this->foreignKeyExists('meetings', 'meetings_current_agenda_item_id_foreign')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropForeign(['current_agenda_item_id']);
            });
        }

        Schema::dropIfExists('meeting_agenda_items');

        if ($this->indexExists('meetings', 'meetings_reference_unique')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropUnique(['reference']);
            });
        }

        foreach ([
            'meeting_type_id', 'structure_id', 'secretary_id', 'organizer_id',
            'parent_meeting_id', 'recurrence_id', 'convocation_document_id', 'minutes_document_id',
        ] as $fk) {
            if (Schema::hasColumn('meetings', $fk)) {
                Schema::table('meetings', function (Blueprint $table) use ($fk) {
                    $table->dropConstrainedForeignId($fk);
                });
            }
        }

        if (Schema::hasColumn('meetings', 'deleted_at')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::table('meetings', function (Blueprint $table) {
            $cols = array_filter([
                'reference', 'object', 'description', 'end_time', 'visio_url',
                'confidentiality', 'priority', 'is_recurring', 'observations',
                'cancellation_reason', 'previous_meeting_date', 'previous_meeting_time',
                'previous_location', 'started_at', 'ended_at', 'locked_at',
                'lock_version', 'current_agenda_item_id',
            ], fn (string $col) => Schema::hasColumn('meetings', $col));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('meeting_recurrences');
        Schema::dropIfExists('meeting_types');
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? '') === $index) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return ((int) ($row->aggregate ?? 0)) > 0;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite ne nomme pas toujours les FK comme MySQL ; on considère absente
            // et on laisse Schema gérer via hasColumn + try/catch côté appelant.
            return false;
        }

        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.table_constraints WHERE constraint_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$database, $table, $constraint, 'FOREIGN KEY']
        );

        return ((int) ($row->aggregate ?? 0)) > 0;
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->index($columns);
            });
        } catch (\Throwable) {
            // Index déjà présent
        }
    }
};
