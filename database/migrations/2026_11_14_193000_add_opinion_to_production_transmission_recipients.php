<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_transmission_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('production_transmission_recipients', 'opinion')) {
                $table->string('opinion', 32)->nullable()->after('viewed_at');
            }
            if (! Schema::hasColumn('production_transmission_recipients', 'opinion_comment')) {
                $table->text('opinion_comment')->nullable()->after('opinion');
            }
            if (! Schema::hasColumn('production_transmission_recipients', 'opinion_at')) {
                $table->timestamp('opinion_at')->nullable()->after('opinion_comment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_transmission_recipients', function (Blueprint $table) {
            foreach (['opinion', 'opinion_comment', 'opinion_at'] as $column) {
                if (Schema::hasColumn('production_transmission_recipients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
