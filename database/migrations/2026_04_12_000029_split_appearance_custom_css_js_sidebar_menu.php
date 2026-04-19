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

        $now = now();
        $appearanceParents = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(title) = ?', ['appearance'])
            ->get(['id', 'required_permission']);

        foreach ($appearanceParents as $parent) {
            $children = DB::table('admin_sidebar_menu_items')
                ->where('parent_id', $parent->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'title', 'route_name', 'sort_order', 'target', 'required_permission', 'is_active']);

            $customCss = $children->first(fn ($child) => strtolower(trim((string) $child->title)) === 'custom css');
            $customJs = $children->first(fn ($child) => strtolower(trim((string) $child->title)) === 'custom js');
            $combined = $children->first(function ($child) {
                $title = strtolower(trim((string) $child->title));

                return in_array($title, ['custom css/js', 'custom css & js', 'custom css js'], true);
            });

            if ($combined) {
                if (! $customCss) {
                    DB::table('admin_sidebar_menu_items')
                        ->where('id', $combined->id)
                        ->update([
                            'title' => 'Custom CSS',
                            'route_name' => 'admin.appearance.custom-css',
                            'url' => null,
                            'updated_at' => $now,
                        ]);

                    $customCssSortOrder = (int) $combined->sort_order;
                } else {
                    DB::table('admin_sidebar_menu_items')->where('id', $combined->id)->delete();
                    $customCssSortOrder = (int) $customCss->sort_order;
                }
            } else {
                if (! $customCss) {
                    $maxSortOrder = (int) ($children->max('sort_order') ?? 0);
                    $customCssSortOrder = $maxSortOrder + 10;
                    DB::table('admin_sidebar_menu_items')->insert([
                        'title' => 'Custom CSS',
                        'icon' => null,
                        'route_name' => 'admin.appearance.custom-css',
                        'url' => null,
                        'target' => '_self',
                        'required_permission' => $parent->required_permission,
                        'parent_id' => $parent->id,
                        'sort_order' => $customCssSortOrder,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $customCssSortOrder = (int) $customCss->sort_order;

                    if ($customCss->route_name !== 'admin.appearance.custom-css') {
                        DB::table('admin_sidebar_menu_items')
                            ->where('id', $customCss->id)
                            ->update([
                                'route_name' => 'admin.appearance.custom-css',
                                'url' => null,
                                'updated_at' => $now,
                            ]);
                    }
                }
            }

            if (! $customJs) {
                DB::table('admin_sidebar_menu_items')->insert([
                    'title' => 'Custom JS',
                    'icon' => null,
                    'route_name' => 'admin.appearance.custom-js',
                    'url' => null,
                    'target' => '_self',
                    'required_permission' => $parent->required_permission,
                    'parent_id' => $parent->id,
                    'sort_order' => $customCssSortOrder + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif ($customJs->route_name !== 'admin.appearance.custom-js') {
                DB::table('admin_sidebar_menu_items')
                    ->where('id', $customJs->id)
                    ->update([
                        'route_name' => 'admin.appearance.custom-js',
                        'url' => null,
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        $appearanceParents = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(title) = ?', ['appearance'])
            ->pluck('id');

        foreach ($appearanceParents as $parentId) {
            $customCss = DB::table('admin_sidebar_menu_items')
                ->where('parent_id', $parentId)
                ->whereRaw('LOWER(title) = ?', ['custom css'])
                ->first(['id', 'sort_order']);
            $customJs = DB::table('admin_sidebar_menu_items')
                ->where('parent_id', $parentId)
                ->whereRaw('LOWER(title) = ?', ['custom js'])
                ->first(['id']);

            if ($customCss) {
                DB::table('admin_sidebar_menu_items')
                    ->where('id', $customCss->id)
                    ->update([
                        'title' => 'Custom CSS/JS',
                        'route_name' => 'admin.appearance.edit',
                        'updated_at' => now(),
                    ]);
            }

            if ($customJs) {
                DB::table('admin_sidebar_menu_items')->where('id', $customJs->id)->delete();
            }
        }
    }
};

