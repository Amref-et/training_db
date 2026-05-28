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
            ->where('route_name', 'admin.fab-faqs.index')
            ->exists();

        if ($exists) {
            return;
        }

        $appearanceParent = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(title) = ?', ['appearance'])
            ->first();

        if (! $appearanceParent) {
            return;
        }

        $sortOrder = ((int) DB::table('admin_sidebar_menu_items')
            ->where('parent_id', $appearanceParent->id)
            ->max('sort_order')) + 10;

        $payload = [
            'title' => 'FAB FAQs',
            'icon' => null,
            'route_name' => 'admin.fab-faqs.index',
            'url' => null,
            'target' => '_self',
            'required_permission' => 'appearance.view',
            'parent_id' => $appearanceParent->id,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('admin_sidebar_menu_items', 'section_id')) {
            $payload['section_id'] = $appearanceParent->section_id ?? null;
        }

        if (Schema::hasColumn('admin_sidebar_menu_items', 'section_title')) {
            $payload['section_title'] = $appearanceParent->section_title ?? 'Core';
        }

        if (Schema::hasColumn('admin_sidebar_menu_items', 'section_sort_order')) {
            $payload['section_sort_order'] = $appearanceParent->section_sort_order ?? 10;
        }

        DB::table('admin_sidebar_menu_items')->insert($payload);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        DB::table('admin_sidebar_menu_items')
            ->where('route_name', 'admin.fab-faqs.index')
            ->whereRaw('LOWER(title) = ?', ['fab faqs'])
            ->delete();
    }
};
