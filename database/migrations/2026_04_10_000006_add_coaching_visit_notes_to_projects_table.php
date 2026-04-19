<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'coaching_visit_1_notes')) {
                $table->longText('coaching_visit_1_notes')->nullable()->after('coaching_visit_1');
            }

            if (! Schema::hasColumn('projects', 'coaching_visit_2_notes')) {
                $table->longText('coaching_visit_2_notes')->nullable()->after('coaching_visit_2');
            }

            if (! Schema::hasColumn('projects', 'coaching_visit_3_notes')) {
                $table->longText('coaching_visit_3_notes')->nullable()->after('coaching_visit_3');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $columns = [];

            foreach (['coaching_visit_1_notes', 'coaching_visit_2_notes', 'coaching_visit_3_notes'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
