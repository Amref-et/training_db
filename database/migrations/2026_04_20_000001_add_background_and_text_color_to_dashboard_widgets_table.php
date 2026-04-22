<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            if (! Schema::hasColumn('dashboard_widgets', 'background_color')) {
                $table->string('background_color', 7)->default('#ffffff')->after('color_scheme');
            }

            if (! Schema::hasColumn('dashboard_widgets', 'text_color')) {
                $table->string('text_color', 7)->default('#1f2937')->after('background_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            foreach (['text_color', 'background_color'] as $column) {
                if (Schema::hasColumn('dashboard_widgets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
