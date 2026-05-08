<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_organizers')) {
            return;
        }

        Schema::table('training_organizers', function (Blueprint $table) {
            if (! Schema::hasColumn('training_organizers', 'project_long_name')) {
                $table->string('project_long_name')->nullable()->after('project_name');
            }

            if (! Schema::hasColumn('training_organizers', 'donor')) {
                $table->string('donor')->nullable()->after('project_long_name');
            }

            if (! Schema::hasColumn('training_organizers', 'program')) {
                $table->string('program')->nullable()->after('donor');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_organizers')) {
            return;
        }

        Schema::table('training_organizers', function (Blueprint $table) {
            foreach (['program', 'donor', 'project_long_name'] as $column) {
                if (Schema::hasColumn('training_organizers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
