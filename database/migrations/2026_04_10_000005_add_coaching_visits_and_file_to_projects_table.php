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
            if (! Schema::hasColumn('projects', 'coaching_visit_1')) {
                $table->date('coaching_visit_1')->nullable()->after('title');
            }

            if (! Schema::hasColumn('projects', 'coaching_visit_2')) {
                $table->date('coaching_visit_2')->nullable()->after('coaching_visit_1');
            }

            if (! Schema::hasColumn('projects', 'coaching_visit_3')) {
                $table->date('coaching_visit_3')->nullable()->after('coaching_visit_2');
            }

            if (! Schema::hasColumn('projects', 'project_file')) {
                $table->string('project_file')->nullable()->after('coaching_visit_3');
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

            foreach (['coaching_visit_1', 'coaching_visit_2', 'coaching_visit_3', 'project_file'] as $column) {
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
