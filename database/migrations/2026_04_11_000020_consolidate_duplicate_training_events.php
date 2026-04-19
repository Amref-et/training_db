<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_events')) {
            return;
        }

        $hasEventParticipants = Schema::hasTable('training_event_participants');
        $hasWorkshopScores = Schema::hasTable('training_event_workshop_scores');
        $hasTrainingRounds = Schema::hasTable('training_rounds');

        $groups = DB::table('training_events')
            ->selectRaw('MIN(id) as keep_id, event_name, training_id, training_organizer_id, start_date, end_date, status, COUNT(*) as records_count')
            ->groupBy('event_name', 'training_id', 'training_organizer_id', 'start_date', 'end_date', 'status')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $keepId = (int) $group->keep_id;

            $eventIds = DB::table('training_events')
                ->when($group->event_name === null, fn (Builder $query) => $query->whereNull('event_name'), fn (Builder $query) => $query->where('event_name', $group->event_name))
                ->when($group->training_id === null, fn (Builder $query) => $query->whereNull('training_id'), fn (Builder $query) => $query->where('training_id', $group->training_id))
                ->when($group->training_organizer_id === null, fn (Builder $query) => $query->whereNull('training_organizer_id'), fn (Builder $query) => $query->where('training_organizer_id', $group->training_organizer_id))
                ->when($group->start_date === null, fn (Builder $query) => $query->whereNull('start_date'), fn (Builder $query) => $query->where('start_date', $group->start_date))
                ->when($group->end_date === null, fn (Builder $query) => $query->whereNull('end_date'), fn (Builder $query) => $query->where('end_date', $group->end_date))
                ->when($group->status === null, fn (Builder $query) => $query->whereNull('status'), fn (Builder $query) => $query->where('status', $group->status))
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $dropIds = array_values(array_filter($eventIds, fn (int $id) => $id !== $keepId));

            if ($dropIds === []) {
                continue;
            }

            if ($hasEventParticipants) {
                $duplicateEnrollments = DB::table('training_event_participants')
                    ->whereIn('training_event_id', $dropIds)
                    ->orderBy('id')
                    ->get();

                foreach ($duplicateEnrollments as $enrollment) {
                    $existing = DB::table('training_event_participants')
                        ->where('training_event_id', $keepId)
                        ->where('participant_id', $enrollment->participant_id)
                        ->first();

                    if ($existing) {
                        if ($existing->final_score === null && $enrollment->final_score !== null) {
                            DB::table('training_event_participants')
                                ->where('id', $existing->id)
                                ->update(['final_score' => $enrollment->final_score, 'updated_at' => now()]);
                        }

                        if ($hasWorkshopScores) {
                            $scores = DB::table('training_event_workshop_scores')
                                ->where('training_event_participant_id', $enrollment->id)
                                ->get();

                            foreach ($scores as $score) {
                                $existingScore = DB::table('training_event_workshop_scores')
                                    ->where('training_event_participant_id', $existing->id)
                                    ->where('workshop_number', $score->workshop_number)
                                    ->first();

                                if (! $existingScore) {
                                    DB::table('training_event_workshop_scores')
                                        ->where('id', $score->id)
                                        ->update(['training_event_participant_id' => $existing->id, 'updated_at' => now()]);
                                    continue;
                                }

                                $updates = ['updated_at' => now()];

                                if ($existingScore->pre_test_score === null && $score->pre_test_score !== null) {
                                    $updates['pre_test_score'] = $score->pre_test_score;
                                }

                                if ($existingScore->post_test_score === null && $score->post_test_score !== null) {
                                    $updates['post_test_score'] = $score->post_test_score;
                                }

                                if (count($updates) > 1) {
                                    DB::table('training_event_workshop_scores')
                                        ->where('id', $existingScore->id)
                                        ->update($updates);
                                }

                                DB::table('training_event_workshop_scores')->where('id', $score->id)->delete();
                            }
                        }

                        DB::table('training_event_participants')->where('id', $enrollment->id)->delete();
                        continue;
                    }

                    DB::table('training_event_participants')
                        ->where('id', $enrollment->id)
                        ->update(['training_event_id' => $keepId, 'updated_at' => now()]);
                }
            }

            if ($hasTrainingRounds) {
                $duplicateRounds = DB::table('training_rounds')
                    ->whereIn('training_event_id', $dropIds)
                    ->orderBy('id')
                    ->get();

                foreach ($duplicateRounds as $round) {
                    $existingRound = DB::table('training_rounds')
                        ->where('training_event_id', $keepId)
                        ->where('round_number', $round->round_number)
                        ->first();

                    if (! $existingRound) {
                        DB::table('training_rounds')
                            ->where('id', $round->id)
                            ->update(['training_event_id' => $keepId, 'updated_at' => now()]);
                        continue;
                    }

                    $updates = ['updated_at' => now()];

                    foreach (['workshop_title', 'round_start_date', 'round_end_date'] as $field) {
                        if (empty($existingRound->{$field}) && ! empty($round->{$field})) {
                            $updates[$field] = $round->{$field};
                        }
                    }

                    if (Schema::hasColumn('training_rounds', 'pre_test_score') && $existingRound->pre_test_score === null && $round->pre_test_score !== null) {
                        $updates['pre_test_score'] = $round->pre_test_score;
                    }

                    if (Schema::hasColumn('training_rounds', 'post_test_score') && $existingRound->post_test_score === null && $round->post_test_score !== null) {
                        $updates['post_test_score'] = $round->post_test_score;
                    }

                    if (count($updates) > 1) {
                        DB::table('training_rounds')
                            ->where('id', $existingRound->id)
                            ->update($updates);
                    }

                    DB::table('training_rounds')->where('id', $round->id)->delete();
                }
            }

            if (Schema::hasColumn('training_events', 'participant_id')) {
                DB::table('training_events')->where('id', $keepId)->update(['participant_id' => null]);
            }

            DB::table('training_events')->whereIn('id', $dropIds)->delete();
        }

        if (Schema::hasColumn('training_events', 'participant_id')) {
            DB::table('training_events')->whereNotNull('participant_id')->update(['participant_id' => null]);
        }

        if ($hasEventParticipants) {
            $enrollments = DB::table('training_event_participants')->select('id', 'training_event_id')->get();

            foreach ($enrollments as $enrollment) {
                $aggregate = $hasWorkshopScores
                    ? DB::table('training_event_workshop_scores')
                        ->where('training_event_participant_id', $enrollment->id)
                        ->selectRaw('AVG(post_test_score) as avg_post, SUM(CASE WHEN post_test_score IS NOT NULL THEN 1 ELSE 0 END) as post_count')
                        ->first()
                    : null;

                $finalScore = ($aggregate && (int) ($aggregate->post_count ?? 0) >= 4 && $aggregate->avg_post !== null)
                    ? round((float) $aggregate->avg_post, 2)
                    : null;

                DB::table('training_event_participants')
                    ->where('id', $enrollment->id)
                    ->update(['final_score' => $finalScore, 'updated_at' => now()]);
            }

            $eventIds = DB::table('training_events')->pluck('id');

            foreach ($eventIds as $eventId) {
                $avgPre = $hasWorkshopScores
                    ? DB::table('training_event_workshop_scores')
                        ->join('training_event_participants', 'training_event_workshop_scores.training_event_participant_id', '=', 'training_event_participants.id')
                        ->where('training_event_participants.training_event_id', $eventId)
                        ->avg('training_event_workshop_scores.pre_test_score')
                    : null;

                $avgFinal = DB::table('training_event_participants')
                    ->where('training_event_id', $eventId)
                    ->avg('final_score');

                DB::table('training_events')
                    ->where('id', $eventId)
                    ->update([
                        'pre_test_score' => $avgPre !== null ? round((float) $avgPre, 2) : null,
                        'post_test_score' => $avgFinal !== null ? round((float) $avgFinal, 2) : null,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Data consolidation is intentionally irreversible.
    }
};

