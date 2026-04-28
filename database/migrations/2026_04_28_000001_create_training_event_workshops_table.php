<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_event_workshops')) {
            Schema::create('training_event_workshops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_event_id')->constrained('training_events')->cascadeOnDelete();
                $table->unsignedTinyInteger('workshop_number');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->unique(['training_event_id', 'workshop_number'], 'training_event_workshops_event_workshop_unique');
            });
        }

        if (! Schema::hasTable('training_events') || ! Schema::hasColumn('training_events', 'workshop_count')) {
            return;
        }

        DB::table('training_events')
            ->select(['id', 'workshop_count', 'start_date', 'end_date', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($events): void {
                $records = [];

                foreach ($events as $event) {
                    $workshopCount = max(1, (int) ($event->workshop_count ?? 4));

                    foreach (range(1, $workshopCount) as $workshopNumber) {
                        $records[] = [
                            'training_event_id' => $event->id,
                            'workshop_number' => $workshopNumber,
                            'start_date' => $event->start_date,
                            'end_date' => $event->end_date,
                            'created_at' => $event->created_at,
                            'updated_at' => $event->updated_at,
                        ];
                    }
                }

                if ($records !== []) {
                    DB::table('training_event_workshops')->upsert(
                        $records,
                        ['training_event_id', 'workshop_number'],
                        ['start_date', 'end_date', 'updated_at']
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_event_workshops');
    }
};
