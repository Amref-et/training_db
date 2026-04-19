<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_sidebar_menu_items')) {
            return;
        }

        Schema::create('admin_sidebar_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('icon', 50)->nullable();
            $table->string('route_name')->nullable();
            $table->string('url')->nullable();
            $table->string('target', 20)->default('_self');
            $table->string('required_permission')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('admin_sidebar_menu_items')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order'], 'admin_sidebar_parent_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sidebar_menu_items');
    }
};

