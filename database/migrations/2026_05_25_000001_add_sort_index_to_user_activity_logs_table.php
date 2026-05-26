<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfPossible('user_activity_logs', ['occurred_at', 'id'], 'user_activity_logs_occurred_id_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfPossible('user_activity_logs', 'user_activity_logs_occurred_id_idx');
    }

    private function addIndexIfPossible(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Throwable) {
            // Ignore duplicate/unsupported index errors across restored or partially migrated databases.
        }
    }

    private function dropIndexIfPossible(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (\Throwable) {
            // Ignore missing-index errors.
        }
    }
};
