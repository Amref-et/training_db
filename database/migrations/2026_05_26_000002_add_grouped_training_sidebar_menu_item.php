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
            ->where('route_name', 'admin.training-events.grouped')
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(title) = ?', ['event by training'])
                    ->orWhereRaw('LOWER(title) = ?', ['view grouped events (by training)']);
            })
            ->update([
                'title' => 'Grouped Training',
                'route_name' => 'admin.training-events.grouped-training',
                'updated_at' => now(),
            ]);

        $exists = DB::table('admin_sidebar_menu_items')
            ->where('route_name', 'admin.training-events.grouped-training')
            ->exists();

        if ($exists) {
            return;
        }

        $parent = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(title) = ?', ['training events'])
            ->first();

        if (! $parent) {
            return;
        }

        $sortOrder = ((int) DB::table('admin_sidebar_menu_items')
            ->where('parent_id', $parent->id)
            ->max('sort_order')) + 1;

        $payload = [
            'title' => 'Grouped Training',
            'icon' => null,
            'route_name' => 'admin.training-events.grouped-training',
            'url' => null,
            'target' => '_self',
            'required_permission' => 'training_events.view',
            'parent_id' => $parent->id,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('admin_sidebar_menu_items', 'section_title')) {
            $payload['section_title'] = $parent->section_title ?? 'Training Operations';
        }

        if (Schema::hasColumn('admin_sidebar_menu_items', 'section_sort_order')) {
            $payload['section_sort_order'] = $parent->section_sort_order ?? 30;
        }

        if (Schema::hasColumn('admin_sidebar_menu_items', 'section_id')) {
            $payload['section_id'] = $parent->section_id ?? null;
        }

        DB::table('admin_sidebar_menu_items')->insert($payload);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        DB::table('admin_sidebar_menu_items')
            ->where('route_name', 'admin.training-events.grouped-training')
            ->whereRaw('LOWER(title) = ?', ['grouped training'])
            ->update([
                'title' => 'Event by Training',
                'route_name' => 'admin.training-events.grouped',
                'updated_at' => now(),
            ]);
    }
};
