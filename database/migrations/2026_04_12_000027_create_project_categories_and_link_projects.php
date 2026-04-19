<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_categories')) {
            Schema::create('project_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('projects') && ! Schema::hasColumn('projects', 'project_category_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreignId('project_category_id')->nullable()->after('participant_id')->constrained('project_categories')->nullOnDelete();
            });
        }

        $defaultCategoryId = DB::table('project_categories')->where('name', 'General')->value('id');
        if (! $defaultCategoryId) {
            $defaultCategoryId = DB::table('project_categories')->insertGetId([
                'name' => 'General',
                'description' => 'Default project category.',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'project_category_id')) {
            DB::table('projects')->whereNull('project_category_id')->update([
                'project_category_id' => $defaultCategoryId,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'project_category_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_category_id');
            });
        }

        Schema::dropIfExists('project_categories');
    }
};
