<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_event_workshop_scores')) {
            return;
        }

        if (! Schema::hasColumn('training_event_workshop_scores', 'mid_test_score')) {
            Schema::table('training_event_workshop_scores', function (Blueprint $table) {
                $table->decimal('mid_test_score', 5, 2)->nullable()->after('pre_test_score');
            });
        }

        if (
            Schema::hasTable('training_event_participants')
            && Schema::hasColumn('training_event_participants', 'mid_test_score')
        ) {
            DB::table('training_event_participants')
                ->select(['id', 'mid_test_score', 'created_at', 'updated_at'])
                ->whereNotNull('mid_test_score')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $existing = DB::table('training_event_workshop_scores')
                            ->where('training_event_participant_id', $row->id)
                            ->where('workshop_number', 2)
                            ->first();

                        if ($existing) {
                            if ($existing->mid_test_score === null) {
                                DB::table('training_event_workshop_scores')
                                    ->where('id', $existing->id)
                                    ->update([
                                        'mid_test_score' => $row->mid_test_score,
                                        'updated_at' => $row->updated_at,
                                    ]);
                            }

                            continue;
                        }

                        DB::table('training_event_workshop_scores')->insert([
                            'training_event_participant_id' => $row->id,
                            'workshop_number' => 2,
                            'pre_test_score' => null,
                            'mid_test_score' => $row->mid_test_score,
                            'post_test_score' => null,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ]);
                    }
                }, 'id');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_event_workshop_scores') || ! Schema::hasColumn('training_event_workshop_scores', 'mid_test_score')) {
            return;
        }

        Schema::table('training_event_workshop_scores', function (Blueprint $table) {
            $table->dropColumn('mid_test_score');
        });
    }
};

