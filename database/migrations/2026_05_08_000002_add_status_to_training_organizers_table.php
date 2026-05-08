<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_organizers')) {
            return;
        }

        Schema::table('training_organizers', function (Blueprint $table) {
            if (! Schema::hasColumn('training_organizers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('program');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_organizers')) {
            return;
        }

        Schema::table('training_organizers', function (Blueprint $table) {
            if (Schema::hasColumn('training_organizers', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
