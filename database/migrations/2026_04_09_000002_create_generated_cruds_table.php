<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_cruds')) {
            return;
        }

        Schema::create('generated_cruds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('table_name')->unique();
            $table->string('singular_label');
            $table->string('plural_label');
            $table->string('model_class')->unique();
            $table->json('schema');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_cruds');
    }
};
