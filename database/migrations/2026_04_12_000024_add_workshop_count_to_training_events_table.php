<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_events')) {
            return;
        }

        if (! Schema::hasColumn('training_events', 'workshop_count')) {
            Schema::table('training_events', function (Blueprint $table) {
                $table->unsignedTinyInteger('workshop_count')->default(4)->after('course_venue');
            });
        }

        if (
            Schema::hasTable('training_event_participants')
            && Schema::hasTable('training_event_workshop_scores')
        ) {
            $maxByEvent = DB::table('training_event_workshop_scores as scores')
                ->join('training_event_participants as enrollments', 'scores.training_event_participant_id', '=', 'enrollments.id')
                ->selectRaw('enrollments.training_event_id as event_id, MAX(scores.workshop_number) as workshop_max')
                ->groupBy('enrollments.training_event_id')
                ->get();

            foreach ($maxByEvent as $row) {
                $max = (int) ($row->workshop_max ?? 0);
                if ($max > 0) {
                    DB::table('training_events')
                        ->where('id', $row->event_id)
                        ->update(['workshop_count' => $max]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_events') || ! Schema::hasColumn('training_events', 'workshop_count')) {
            return;
        }

        Schema::table('training_events', function (Blueprint $table) {
            $table->dropColumn('workshop_count');
        });
    }
};

