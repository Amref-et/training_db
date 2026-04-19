<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trainings') || ! Schema::hasTable('training_categories')) {
            return;
        }

        Schema::table('trainings', function (Blueprint $table) {
            if (! Schema::hasColumn('trainings', 'training_category_id')) {
                $table->foreignId('training_category_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('training_categories')
                    ->nullOnDelete();
            }
        });

        $defaultCategoryId = DB::table('training_categories')
            ->whereRaw('LOWER(name) = ?', ['general'])
            ->value('id');

        if (! $defaultCategoryId) {
            $defaultCategoryId = DB::table('training_categories')->insertGetId([
                'name' => 'General',
                'description' => 'Default category for existing trainings.',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('trainings')
            ->whereNull('training_category_id')
            ->update([
                'training_category_id' => $defaultCategoryId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('trainings')) {
            return;
        }

        Schema::table('trainings', function (Blueprint $table) {
            if (Schema::hasColumn('trainings', 'training_category_id')) {
                $table->dropConstrainedForeignId('training_category_id');
            }
        });
    }
};

