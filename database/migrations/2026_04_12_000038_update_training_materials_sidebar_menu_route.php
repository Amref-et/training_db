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
            ->whereRaw('LOWER(title) = ?', ['training materials'])
            ->where('route_name', 'admin.trainings.index')
            ->update([
                'route_name' => 'admin.trainingmaterials.index',
                'required_permission' => 'training_materials.view',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        DB::table('admin_sidebar_menu_items')
            ->whereRaw('LOWER(title) = ?', ['training materials'])
            ->where('route_name', 'admin.trainingmaterials.index')
            ->update([
                'route_name' => 'admin.trainings.index',
                'updated_at' => now(),
            ]);
    }
};

