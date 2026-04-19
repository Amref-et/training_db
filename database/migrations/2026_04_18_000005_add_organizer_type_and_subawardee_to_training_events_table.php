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
            if (! Schema::hasColumn('training_events', 'organizer_type')) {
                $table->string('organizer_type')->nullable()->after('training_organizer_id');
            }

            if (! Schema::hasColumn('training_events', 'project_subawardee_id') && Schema::hasTable('project_subawardees')) {
                $table->foreignId('project_subawardee_id')->nullable()->after('organizer_type')->constrained('project_subawardees')->nullOnDelete();
            }
        });

        DB::table('training_events')
            ->whereNull('organizer_type')
            ->update(['organizer_type' => 'The project']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_events')) {
            return;
        }

        Schema::table('training_events', function (Blueprint $table) {
            if (Schema::hasColumn('training_events', 'project_subawardee_id')) {
                $table->dropConstrainedForeignId('project_subawardee_id');
            }

            if (Schema::hasColumn('training_events', 'organizer_type')) {
                $table->dropColumn('organizer_type');
            }
        });
    }
};
