<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_settings') && ! Schema::hasColumn('website_settings', 'fab_chat_enabled')) {
            Schema::table('website_settings', function (Blueprint $table) {
                $table->boolean('fab_chat_enabled')->default(false)->after('show_login_link');
            });
        }

        if (! Schema::hasTable('fab_faq_items')) {
            Schema::create('fab_faq_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('fab_faq_items')->cascadeOnDelete();
                $table->string('type', 20)->default('category');
                $table->string('visibility', 20)->default('both');
                $table->string('title');
                $table->longText('answer')->nullable();
                $table->string('link_label')->nullable();
                $table->string('link_url')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['parent_id', 'sort_order'], 'fab_faq_items_parent_sort_idx');
                $table->index(['type', 'is_active'], 'fab_faq_items_type_active_idx');
                $table->index(['visibility', 'is_active'], 'fab_faq_items_visibility_active_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fab_faq_items');

        if (Schema::hasTable('website_settings') && Schema::hasColumn('website_settings', 'fab_chat_enabled')) {
            Schema::table('website_settings', function (Blueprint $table) {
                $table->dropColumn('fab_chat_enabled');
            });
        }
    }
};
