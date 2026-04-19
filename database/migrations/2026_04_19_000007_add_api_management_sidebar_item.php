<?php

use App\Models\AdminSidebarMenuItem;
use App\Models\AdminSidebarMenuSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        $existing = AdminSidebarMenuItem::query()
            ->where('route_name', 'admin.api-management.index')
            ->first();

        if ($existing) {
            return;
        }

        $section = Schema::hasTable('admin_sidebar_menu_sections')
            ? AdminSidebarMenuSection::query()->firstOrCreate(
                ['name' => 'Core'],
                ['sort_order' => 10, 'is_active' => true]
            )
            : null;

        $parent = AdminSidebarMenuItem::query()->create([
            'title' => 'API Management',
            'icon' => 'cloud-arrow-up',
            'route_name' => null,
            'url' => null,
            'target' => '_self',
            'required_permission' => 'api_management.view',
            'section_id' => $section?->id,
            'section_title' => $section?->name ?? 'Core',
            'section_sort_order' => (int) ($section?->sort_order ?? 10),
            'parent_id' => null,
            'sort_order' => 65,
            'is_active' => true,
        ]);

        foreach ([
            ['title' => 'API Dashboard', 'route_name' => 'admin.api-management.index', 'sort_order' => 10, 'permission' => 'api_management.view'],
            ['title' => 'DHIS2 Integration', 'route_name' => 'admin.api-management.index', 'sort_order' => 20, 'permission' => 'api_management.update'],
        ] as $child) {
            AdminSidebarMenuItem::query()->create([
                'title' => $child['title'],
                'icon' => null,
                'route_name' => $child['route_name'],
                'url' => null,
                'target' => '_self',
                'required_permission' => $child['permission'],
                'section_id' => $section?->id,
                'section_title' => $section?->name ?? 'Core',
                'section_sort_order' => (int) ($section?->sort_order ?? 10),
                'parent_id' => $parent->id,
                'sort_order' => $child['sort_order'],
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        $parent = AdminSidebarMenuItem::query()
            ->where('title', 'API Management')
            ->where('required_permission', 'api_management.view')
            ->first();

        if ($parent) {
            $parent->children()->delete();
            $parent->delete();
        }
    }
};
