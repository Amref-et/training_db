<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('training_events')
            || ! Schema::hasTable('training_event_participants')
            || ! Schema::hasTable('training_event_workshop_scores')
        ) {
            return;
        }

        $enrollments = DB::table('training_event_participants as tep')
            ->join('training_events as te', 'te.id', '=', 'tep.training_event_id')
            ->select([
                'tep.id',
                'tep.training_event_id',
                'te.workshop_count',
            ])
            ->orderBy('tep.id')
            ->get();

        foreach ($enrollments as $enrollment) {
            $requiredWorkshopCount = max(1, (int) ($enrollment->workshop_count ?? 4));

            $aggregate = DB::table('training_event_workshop_scores')
                ->where('training_event_participant_id', $enrollment->id)
                ->where('workshop_number', '<=', $requiredWorkshopCount)
                ->selectRaw('AVG(post_test_score) as avg_post, SUM(CASE WHEN post_test_score IS NOT NULL THEN 1 ELSE 0 END) as post_count')
                ->first();

            $finalScore = ((int) ($aggregate?->post_count ?? 0) >= $requiredWorkshopCount && $aggregate?->avg_post !== null)
                ? round((float) $aggregate->avg_post, 2)
                : null;

            DB::table('training_event_participants')
                ->where('id', $enrollment->id)
                ->update(['final_score' => $finalScore]);
        }

        $events = DB::table('training_events')
            ->select(['id', 'workshop_count'])
            ->orderBy('id')
            ->get();

        foreach ($events as $event) {
            $requiredWorkshopCount = max(1, (int) ($event->workshop_count ?? 4));

            $avgPre = DB::table('training_event_workshop_scores as tews')
                ->join('training_event_participants as tep', 'tews.training_event_participant_id', '=', 'tep.id')
                ->where('tep.training_event_id', $event->id)
                ->where('tews.workshop_number', '<=', $requiredWorkshopCount)
                ->avg('tews.pre_test_score');

            $avgFinal = DB::table('training_event_participants')
                ->where('training_event_id', $event->id)
                ->avg('final_score');

            DB::table('training_events')
                ->where('id', $event->id)
                ->update([
                    'pre_test_score' => $avgPre !== null ? round((float) $avgPre, 2) : null,
                    'post_test_score' => $avgFinal !== null ? round((float) $avgFinal, 2) : null,
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally no-op. This migration recalculates derived values.
    }
};

