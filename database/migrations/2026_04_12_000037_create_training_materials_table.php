<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_materials')) {
            return;
        }

        Schema::create('training_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->nullable()->constrained('trainings')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('resource_file')->nullable();
            $table->string('external_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_materials');
    }
};

