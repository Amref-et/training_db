<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_event_participants')) {
            Schema::create('training_event_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_event_id')->constrained('training_events')->cascadeOnDelete();
                $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
                $table->decimal('final_score', 5, 2)->nullable();
                $table->timestamps();

                $table->unique(['training_event_id', 'participant_id'], 'training_event_participants_event_participant_unique');
            });
        }

        if (! Schema::hasTable('training_event_workshop_scores')) {
            Schema::create('training_event_workshop_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('training_event_participant_id');
                $table->unsignedTinyInteger('workshop_number');
                $table->decimal('pre_test_score', 5, 2)->nullable();
                $table->decimal('post_test_score', 5, 2)->nullable();
                $table->timestamps();

                $table->foreign('training_event_participant_id', 'tews_tepid_fk')
                    ->references('id')
                    ->on('training_event_participants')
                    ->cascadeOnDelete();
                $table->unique(['training_event_participant_id', 'workshop_number'], 'training_event_workshop_scores_unique');
            });
        }

        if (
            Schema::hasTable('training_events')
            && Schema::hasColumn('training_events', 'participant_id')
            && Schema::hasTable('training_event_participants')
        ) {
            DB::table('training_events')
                ->select(['id', 'participant_id', 'post_test_score', 'created_at', 'updated_at'])
                ->whereNotNull('participant_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    $records = [];

                    foreach ($rows as $row) {
                        $records[] = [
                            'training_event_id' => $row->id,
                            'participant_id' => $row->participant_id,
                            'final_score' => $row->post_test_score,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                    }

                    if ($records !== []) {
                        DB::table('training_event_participants')->upsert(
                            $records,
                            ['training_event_id', 'participant_id'],
                            ['final_score', 'updated_at']
                        );
                    }
                }, 'id');

            DB::statement('ALTER TABLE training_events MODIFY participant_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_event_workshop_scores');
        Schema::dropIfExists('training_event_participants');

        if (Schema::hasTable('training_events') && Schema::hasColumn('training_events', 'participant_id')) {
            $fallbackParticipantId = DB::table('participants')->min('id');

            if ($fallbackParticipantId !== null) {
                DB::table('training_events')
                    ->whereNull('participant_id')
                    ->update(['participant_id' => $fallbackParticipantId]);

                DB::statement('ALTER TABLE training_events MODIFY participant_id BIGINT UNSIGNED NOT NULL');
            }
        }
    }
};
