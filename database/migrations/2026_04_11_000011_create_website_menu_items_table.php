<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_menu_items')) {
            return;
        }

        Schema::create('website_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url')->nullable();
            $table->foreignId('page_id')->nullable()->constrained('content_pages')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('website_menu_items')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('target', 20)->default('_self');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_menu_items');
    }
};
