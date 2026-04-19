<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfPossible('training_events', ['event_name', 'training_id', 'training_organizer_id', 'start_date'], 'idx_te_group_lookup');
        $this->addIndexIfPossible('training_events', ['status'], 'idx_te_status');
        $this->addIndexIfPossible('training_events', ['training_id', 'training_organizer_id'], 'idx_te_training_org');

        $this->addIndexIfPossible('training_event_participants', ['training_event_id', 'final_score'], 'idx_tep_event_final_score');
        $this->addIndexIfPossible('training_event_participants', ['participant_id', 'training_event_id'], 'idx_tep_participant_event');

        $this->addIndexIfPossible('participants', ['participant_code'], 'idx_participants_code');
        $this->addIndexIfPossible('participants', ['mobile_phone'], 'idx_participants_mobile');
    }

    public function down(): void
    {
        $this->dropIndexIfPossible('participants', 'idx_participants_mobile');
        $this->dropIndexIfPossible('participants', 'idx_participants_code');

        $this->dropIndexIfPossible('training_event_participants', 'idx_tep_participant_event');
        $this->dropIndexIfPossible('training_event_participants', 'idx_tep_event_final_score');

        $this->dropIndexIfPossible('training_events', 'idx_te_training_org');
        $this->dropIndexIfPossible('training_events', 'idx_te_status');
        $this->dropIndexIfPossible('training_events', 'idx_te_group_lookup');
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
            // Ignore duplicate/unsupported index errors to keep migration idempotent across environments.
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
