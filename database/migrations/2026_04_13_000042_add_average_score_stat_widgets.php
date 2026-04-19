<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tabs = DB::table('dashboard_tabs')
            ->select('id', 'user_id', 'name', 'slug')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        foreach ($tabs as $userTabs) {
            $reportTab = $userTabs->first(function ($tab) {
                $slug = strtolower((string) ($tab->slug ?? ''));
                $name = strtolower((string) ($tab->name ?? ''));

                return $slug === 'reports-dashboard'
                    || str_contains($slug, 'report')
                    || str_contains($name, 'report');
            });

            $targetTab = $reportTab ?: $userTabs->first();
            if (! $targetTab) {
                continue;
            }

            $this->ensureWidget(
                (int) $targetTab->id,
                'Average Pre-result Score',
                "SELECT ROUND(AVG(w.pre_test_score), 1) AS average_pre_result_score
FROM training_event_workshop_scores w
INNER JOIN training_event_participants ep ON ep.id = w.training_event_participant_id
INNER JOIN participants p ON p.id = ep.participant_id
INNER JOIN training_events e ON e.id = ep.training_event_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}",
                'teal_amber'
            );

            $this->ensureWidget(
                (int) $targetTab->id,
                'Average Post-result Score',
                "SELECT ROUND(AVG(ep.final_score), 1) AS average_post_result_score
FROM training_event_participants ep
INNER JOIN participants p ON p.id = ep.participant_id
INNER JOIN training_events e ON e.id = ep.training_event_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}",
                'emerald_slate'
            );
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')
            ->whereIn('title', ['Average Pre-result Score', 'Average Post-result Score'])
            ->delete();
    }

    private function ensureWidget(int $tabId, string $title, string $sql, string $color): void
    {
        $exists = DB::table('dashboard_widgets')
            ->where('dashboard_tab_id', $tabId)
            ->where('title', $title)
            ->exists();

        if ($exists) {
            return;
        }

        $sortOrder = ((int) DB::table('dashboard_widgets')
            ->where('dashboard_tab_id', $tabId)
            ->max('sort_order')) + 1;

        DB::table('dashboard_widgets')->insert([
            'dashboard_tab_id' => $tabId,
            'title' => $title,
            'chart_type' => 'stat',
            'sql_query' => $sql,
            'refresh_interval_seconds' => null,
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'width_px' => null,
            'height_px' => 240,
            'color_scheme' => $color,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};

