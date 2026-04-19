<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        DB::table('admin_sidebar_menu_items')
            ->whereRaw('LOWER(title) = ?', ['event calendar view'])
            ->where('route_name', 'admin.training-events.index')
            ->update([
                'route_name' => 'admin.training-events-calendar.index',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        DB::table('admin_sidebar_menu_items')
            ->whereRaw('LOWER(title) = ?', ['event calendar view'])
            ->where('route_name', 'admin.training-events-calendar.index')
            ->update([
                'route_name' => 'admin.training-events.index',
                'updated_at' => now(),
            ]);
    }
};

