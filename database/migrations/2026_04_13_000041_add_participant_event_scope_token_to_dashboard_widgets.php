<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $widgets = DB::table('dashboard_widgets')
            ->select('id', 'sql_query')
            ->where('sql_query', 'like', '%{{participants_filter:p}}%')
            ->get();

        foreach ($widgets as $widget) {
            $sql = (string) $widget->sql_query;
            if (str_contains($sql, '{{participant_events_filter:p}}')) {
                continue;
            }

            $shouldScopeByEvent =
                (str_contains($sql, 'FROM participants p') && ! str_contains($sql, 'training_event_participants'))
                || str_contains($sql, 'FROM projects pr');

            if (! $shouldScopeByEvent) {
                continue;
            }

            $newSql = str_replace(
                '{{participants_filter:p}}',
                '{{participants_filter:p}}{{participant_events_filter:p}}',
                $sql
            );

            if ($newSql === $sql) {
                continue;
            }

            DB::table('dashboard_widgets')
                ->where('id', $widget->id)
                ->update(['sql_query' => $newSql]);
        }
    }

    public function down(): void
    {
        $widgets = DB::table('dashboard_widgets')
            ->select('id', 'sql_query')
            ->where('sql_query', 'like', '%{{participant_events_filter:p}}%')
            ->get();

        foreach ($widgets as $widget) {
            $sql = (string) $widget->sql_query;
            $newSql = str_replace(
                '{{participants_filter:p}}{{participant_events_filter:p}}',
                '{{participants_filter:p}}',
                $sql
            );

            if ($newSql === $sql) {
                continue;
            }

            DB::table('dashboard_widgets')
                ->where('id', $widget->id)
                ->update(['sql_query' => $newSql]);
        }
    }
};

