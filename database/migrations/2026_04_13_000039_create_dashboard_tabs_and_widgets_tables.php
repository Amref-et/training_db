<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_tabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_tab_id')->constrained('dashboard_tabs')->cascadeOnDelete();
            $table->string('title');
            $table->string('chart_type', 32)->default('bar');
            $table->longText('sql_query');
            $table->unsignedInteger('refresh_interval_seconds')->nullable();
            $table->string('size_preset', 16)->default('medium');
            $table->string('width_mode', 16)->default('columns');
            $table->unsignedTinyInteger('width_columns')->default(6);
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->default(280);
            $table->string('color_scheme', 32)->default('teal_amber');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('dashboard_tabs');
    }
};

