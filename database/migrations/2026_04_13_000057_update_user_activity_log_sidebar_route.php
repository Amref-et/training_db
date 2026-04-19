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
            ->whereRaw('LOWER(title) = ?', ['user activity log'])
            ->update([
                'route_name' => 'admin.user-activity-logs.index',
                'url' => null,
                'required_permission' => 'users.view',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        DB::table('admin_sidebar_menu_items')
            ->whereRaw('LOWER(title) = ?', ['user activity log'])
            ->where('route_name', 'admin.user-activity-logs.index')
            ->update([
                'route_name' => null,
                'url' => '#',
                'updated_at' => now(),
            ]);
    }
};
