<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'label')) {
                $table->string('label')->nullable()->after('name');
            }
            if (! Schema::hasColumn('permissions', 'module')) {
                $table->string('module', 64)->nullable()->after('label');
            }
            if (! Schema::hasColumn('permissions', 'description')) {
                $table->string('description')->nullable()->after('module');
            }
            if (! Schema::hasColumn('permissions', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'code')) {
                $table->string('code', 64)->nullable()->after('name');
            }
            if (! Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('code');
            }
            if (! Schema::hasColumn('roles', 'home_route')) {
                $table->string('home_route')->nullable()->after('is_system');
            }
        });

        $hasCodeUnique = collect(Schema::getIndexes('roles'))
            ->contains(fn (array $index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['code']);

        if (! $hasCodeUnique && Schema::hasColumn('roles', 'code')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'code')) {
            $hasCodeUnique = collect(Schema::getIndexes('roles'))
                ->contains(fn (array $index) => ($index['unique'] ?? false)
                    && ($index['columns'] ?? []) === ['code']);

            if ($hasCodeUnique) {
                Schema::table('roles', function (Blueprint $table) {
                    $table->dropUnique(['code']);
                });
            }
        }

        Schema::table('roles', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['code', 'is_system', 'home_route'],
                fn ($c) => Schema::hasColumn('roles', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['label', 'module', 'description', 'is_system'],
                fn ($c) => Schema::hasColumn('permissions', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
