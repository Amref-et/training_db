<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return;
        }

        Schema::table('user_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('user_activity_logs', 'log_type')) {
                $table->string('log_type', 30)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('user_activity_logs', 'event_key')) {
                $table->string('event_key', 120)->nullable()->after('log_type');
            }

            if (! Schema::hasColumn('user_activity_logs', 'auditable_type')) {
                $table->string('auditable_type', 255)->nullable()->after('status_code');
            }

            if (! Schema::hasColumn('user_activity_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
            }

            if (! Schema::hasColumn('user_activity_logs', 'auditable_label')) {
                $table->string('auditable_label', 255)->nullable()->after('auditable_id');
            }

            if (! Schema::hasColumn('user_activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('auditable_label');
            }

            if (! Schema::hasColumn('user_activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
        });

        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->index(['log_type', 'occurred_at'], 'user_activity_logs_type_occurred_idx');
            $table->index(['event_key', 'occurred_at'], 'user_activity_logs_event_occurred_idx');
            $table->index(['auditable_type', 'auditable_id'], 'user_activity_logs_auditable_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return;
        }

        Schema::table('user_activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_activity_logs', 'log_type')) {
                $table->dropIndex('user_activity_logs_type_occurred_idx');
            }

            if (Schema::hasColumn('user_activity_logs', 'event_key')) {
                $table->dropIndex('user_activity_logs_event_occurred_idx');
            }

            if (Schema::hasColumn('user_activity_logs', 'auditable_id')) {
                $table->dropIndex('user_activity_logs_auditable_idx');
            }
        });

        Schema::table('user_activity_logs', function (Blueprint $table) {
            foreach (['new_values', 'old_values', 'auditable_label', 'auditable_id', 'auditable_type', 'event_key', 'log_type'] as $column) {
                if (Schema::hasColumn('user_activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
