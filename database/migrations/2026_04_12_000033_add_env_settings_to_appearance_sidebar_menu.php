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

        $exists = DB::table('admin_sidebar_menu_items')
            ->where('route_name', 'admin.settings.env.edit')
            ->exists();

        if ($exists) {
            return;
        }

        $appearanceParent = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(title) = ?', ['appearance'])
            ->orderBy('sort_order')
            ->first();

        if (! $appearanceParent) {
            return;
        }

        $maxSortOrder = (int) (DB::table('admin_sidebar_menu_items')
            ->where('parent_id', $appearanceParent->id)
            ->max('sort_order') ?? 0);

        DB::table('admin_sidebar_menu_items')->insert([
            'title' => 'Env Settings',
            'icon' => null,
            'route_name' => 'admin.settings.env.edit',
            'url' => null,
            'target' => '_self',
            'required_permission' => $appearanceParent->required_permission,
            'section_id' => $appearanceParent->section_id ?? null,
            'section_title' => $appearanceParent->section_title ?? 'Core',
            'section_sort_order' => (int) ($appearanceParent->section_sort_order ?? 10),
            'parent_id' => $appearanceParent->id,
            'sort_order' => $maxSortOrder + 10,
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

        DB::table('admin_sidebar_menu_items')
            ->where('route_name', 'admin.settings.env.edit')
            ->delete();
    }
};

