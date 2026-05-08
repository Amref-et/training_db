<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['regions', 'zones', 'woredas', 'organizations'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'external_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->string('external_id')->nullable()->after('id');
                $table->index('external_id', $tableName.'_external_id_index');
            });
        }
    }

    public function down(): void
    {
        foreach (['organizations', 'woredas', 'zones', 'regions'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'external_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex($tableName.'_external_id_index');
                $table->dropColumn('external_id');
            });
        }
    }
};
