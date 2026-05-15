<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            // Drop the unique constraint on name
            $table->dropUnique(['name']);
            // Add unique constraint on external_id
            $table->unique('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            // Drop the unique constraint on external_id
            $table->dropUnique(['external_id']);
            // Add back unique constraint on name
            $table->unique('name');
        });
    }
};