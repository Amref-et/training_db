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

        Schema::table('training_events', function (Blueprint $table) {
            if (! Schema::hasColumn('training_events', 'event_name')) {
                $table->string('event_name')->nullable()->after('participant_id');
            }
        });

        if (Schema::hasColumn('training_events', 'event_name')) {
            DB::table('training_events')
                ->leftJoin('trainings', 'trainings.id', '=', 'training_events.training_id')
                ->where(fn ($query) => $query
                    ->whereNull('training_events.event_name')
                    ->orWhere('training_events.event_name', '')
                )
                ->select(['training_events.id', 'trainings.title'])
                ->orderBy('training_events.id')
                ->chunkById(200, function ($events): void {
                    foreach ($events as $event) {
                        DB::table('training_events')
                            ->where('id', $event->id)
                            ->update([
                                'event_name' => $event->title ?: 'Training Event #'.$event->id,
                            ]);
                    }
                }, 'training_events.id', 'id');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_events')) {
            return;
        }

        Schema::table('training_events', function (Blueprint $table) {
            if (Schema::hasColumn('training_events', 'event_name')) {
                $table->dropColumn('event_name');
            }
        });
    }
};
