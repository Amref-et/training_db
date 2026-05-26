<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_events')) {
            return;
        }

        Schema::table('training_events', function (Blueprint $table) {
            if (! Schema::hasColumn('training_events', 'training_event_report_path')) {
                $table->string('training_event_report_path')->nullable()->after('status');
            }

            if (! Schema::hasColumn('training_events', 'training_event_picture_paths')) {
                $table->json('training_event_picture_paths')->nullable()->after('training_event_report_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_events')) {
            return;
        }

        Schema::table('training_events', function (Blueprint $table) {
            if (Schema::hasColumn('training_events', 'training_event_picture_paths')) {
                $table->dropColumn('training_event_picture_paths');
            }

            if (Schema::hasColumn('training_events', 'training_event_report_path')) {
                $table->dropColumn('training_event_report_path');
            }
        });
    }
};
