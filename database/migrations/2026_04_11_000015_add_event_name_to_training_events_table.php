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
            DB::statement('
                UPDATE training_events te
                LEFT JOIN trainings t ON t.id = te.training_id
                SET te.event_name = COALESCE(NULLIF(te.event_name, \'\'), t.title, CONCAT(\'Training Event #\', te.id))
                WHERE te.event_name IS NULL OR te.event_name = \'\'
            ');
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

