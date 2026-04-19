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

        if (! Schema::hasTable('admin_sidebar_menu_sections')) {
            return;
        }

        Schema::table('admin_sidebar_menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_sidebar_menu_items', 'section_id')) {
                $table->foreignId('section_id')
                    ->nullable()
                    ->after('required_permission')
                    ->constrained('admin_sidebar_menu_sections')
                    ->nullOnDelete();
            }
        });

        $groups = DB::table('admin_sidebar_menu_items')
            ->select('section_title', 'section_sort_order')
            ->whereNotNull('section_title')
            ->groupBy('section_title', 'section_sort_order')
            ->get();

        foreach ($groups as $group) {
            $sectionName = trim((string) $group->section_title);
            if ($sectionName === '') {
                continue;
            }

            $existing = DB::table('admin_sidebar_menu_sections')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($sectionName)])
                ->first();

            $sectionId = $existing?->id;
            if (! $sectionId) {
                $sectionId = DB::table('admin_sidebar_menu_sections')->insertGetId([
                    'name' => $sectionName,
                    'sort_order' => (int) ($group->section_sort_order ?? 0),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('admin_sidebar_menu_items')
                ->where('section_title', $group->section_title)
                ->where('section_sort_order', $group->section_sort_order)
                ->update([
                    'section_id' => $sectionId,
                    'updated_at' => now(),
                ]);
        }

        $general = DB::table('admin_sidebar_menu_sections')
            ->whereRaw('LOWER(name) = ?', ['general'])
            ->first();

        if (! $general) {
            $generalId = DB::table('admin_sidebar_menu_sections')->insertGetId([
                'name' => 'General',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $generalId = $general->id;
        }

        DB::table('admin_sidebar_menu_items')
            ->whereNull('section_id')
            ->update([
                'section_id' => $generalId,
                'section_title' => DB::raw("COALESCE(NULLIF(section_title, ''), 'General')"),
                'section_sort_order' => DB::raw('COALESCE(section_sort_order, 0)'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        Schema::table('admin_sidebar_menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('admin_sidebar_menu_items', 'section_id')) {
                $table->dropConstrainedForeignId('section_id');
            }
        });
    }
};

