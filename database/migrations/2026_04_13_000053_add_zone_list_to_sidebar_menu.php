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

        $organizationParent = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(title) = ?', ['organizations'])
            ->orderBy('id')
            ->first();

        if (! $organizationParent) {
            return;
        }

        $existing = DB::table('admin_sidebar_menu_items')
            ->where('parent_id', $organizationParent->id)
            ->whereRaw('LOWER(title) = ?', ['zone list'])
            ->first();

        if ($existing) {
            DB::table('admin_sidebar_menu_items')
                ->where('id', $existing->id)
                ->update([
                    'route_name' => 'admin.zones.index',
                    'required_permission' => 'zones.view',
                    'updated_at' => now(),
                ]);
            return;
        }

        $nextSortOrder = (int) DB::table('admin_sidebar_menu_items')
            ->where('parent_id', $organizationParent->id)
            ->max('sort_order');

        DB::table('admin_sidebar_menu_items')->insert([
            'title' => 'Zone List',
            'icon' => null,
            'route_name' => 'admin.zones.index',
            'url' => null,
            'target' => '_self',
            'required_permission' => 'zones.view',
            'section_id' => $organizationParent->section_id ?? null,
            'section_title' => $organizationParent->section_title ?? 'Reference Data',
            'section_sort_order' => $organizationParent->section_sort_order ?? 20,
            'parent_id' => $organizationParent->id,
            'sort_order' => max(10, $nextSortOrder + 10),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        $organizationParent = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(title) = ?', ['organizations'])
            ->orderBy('id')
            ->first();

        if (! $organizationParent) {
            return;
        }

        DB::table('admin_sidebar_menu_items')
            ->where('parent_id', $organizationParent->id)
            ->whereRaw('LOWER(title) = ?', ['zone list'])
            ->where('route_name', 'admin.zones.index')
            ->delete();
    }
};
