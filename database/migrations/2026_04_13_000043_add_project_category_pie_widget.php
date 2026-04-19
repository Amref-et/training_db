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

            $exists = DB::table('dashboard_widgets')
                ->where('dashboard_tab_id', $targetTab->id)
                ->where('title', 'Project Category Distribution')
                ->exists();

            if ($exists) {
                continue;
            }

            $sortOrder = ((int) DB::table('dashboard_widgets')
                ->where('dashboard_tab_id', $targetTab->id)
                ->max('sort_order')) + 1;

            DB::table('dashboard_widgets')->insert([
                'dashboard_tab_id' => $targetTab->id,
                'title' => 'Project Category Distribution',
                'chart_type' => 'pie',
                'sql_query' => "SELECT category_name AS label, project_total AS value
FROM (
    SELECT
        COALESCE(NULLIF(pc.name, ''), 'Uncategorized') AS category_name,
        COUNT(DISTINCT pr.id) AS project_total
    FROM projects pr
    LEFT JOIN project_categories pc ON pc.id = pr.project_category_id
    LEFT JOIN (
        SELECT project_id, participant_id
        FROM project_participants
        UNION ALL
        SELECT id AS project_id, participant_id
        FROM projects
        WHERE participant_id IS NOT NULL
    ) prp ON prp.project_id = pr.id
    LEFT JOIN participants p ON p.id = prp.participant_id
    WHERE 1 = 1{{participants_filter:p}}{{participant_events_filter:p}}
    GROUP BY COALESCE(NULLIF(pc.name, ''), 'Uncategorized')
) category_counts
WHERE project_total > 0
ORDER BY category_name",
                'refresh_interval_seconds' => null,
                'size_preset' => 'medium',
                'width_mode' => 'columns',
                'width_columns' => 6,
                'width_px' => null,
                'height_px' => 280,
                'color_scheme' => 'blue_pink',
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dashboard_widgets')
            ->where('title', 'Project Category Distribution')
            ->delete();
    }
};

