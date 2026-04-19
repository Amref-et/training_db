<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        Schema::table('admin_sidebar_menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_sidebar_menu_items', 'section_title')) {
                $table->string('section_title', 100)->nullable()->after('required_permission');
            }

            if (! Schema::hasColumn('admin_sidebar_menu_items', 'section_sort_order')) {
                $table->unsignedInteger('section_sort_order')->default(0)->after('section_title');
            }
        });

        $roots = DB::table('admin_sidebar_menu_items')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'section_title', 'section_sort_order']);

        foreach ($roots as $root) {
            $sectionTitle = trim((string) ($root->section_title ?? ''));
            if ($sectionTitle === '') {
                $sectionTitle = 'General';
            }

            $sectionSortOrder = (int) ($root->section_sort_order ?? 0);

            DB::table('admin_sidebar_menu_items')
                ->where('id', $root->id)
                ->update([
                    'section_title' => $sectionTitle,
                    'section_sort_order' => $sectionSortOrder,
                    'updated_at' => now(),
                ]);

            DB::table('admin_sidebar_menu_items')
                ->where('parent_id', $root->id)
                ->update([
                    'section_title' => $sectionTitle,
                    'section_sort_order' => $sectionSortOrder,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        Schema::table('admin_sidebar_menu_items', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('admin_sidebar_menu_items', 'section_sort_order')) {
                $columns[] = 'section_sort_order';
            }

            if (Schema::hasColumn('admin_sidebar_menu_items', 'section_title')) {
                $columns[] = 'section_title';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

