<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\DashboardTab;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('email', ['admin@example.com', 'editor@example.com', 'viewer@example.com'])
            ->get();

        foreach ($users as $user) {
            $this->seedTrainingDashboardFor($user);
        }

        $publicTab = DashboardTab::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'admin@example.com'))
            ->where('slug', 'training-dashboard')
            ->first();

        if ($publicTab) {
            WebsiteSetting::query()->updateOrCreate(
                ['id' => 1],
                ['public_home_dashboard_tab_id' => $publicTab->id]
            );
        }

        $this->ensureHomepageDashboardFilters();
    }

    private function seedTrainingDashboardFor(User $user): void
    {
        $tab = DashboardTab::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'slug' => 'training-dashboard',
            ],
            [
                'name' => 'Training Dashboard',
                'sort_order' => 1,
                'is_default' => true,
            ]
        );

        DashboardTab::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $tab->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        foreach ($this->trainingDashboardWidgets() as $widgetDefinition) {
            DashboardWidget::query()->updateOrCreate(
                [
                    'dashboard_tab_id' => $tab->id,
                    'title' => $widgetDefinition['title'],
                ],
                array_merge($widgetDefinition, [
                    'dashboard_tab_id' => $tab->id,
                    'is_active' => true,
                ])
            );
        }
    }

    private function ensureHomepageDashboardFilters(): void
    {
        $expectedFilters = [
            'training_organizer_id',
            'organized_by',
            'gender',
            'region_id',
            'organization_id',
            'profession',
            'training_id',
            'status',
        ];

        $homePage = ContentPage::query()->where('slug', 'home')->first();
        if (! $homePage) {
            return;
        }

        $blocks = collect($homePage->blocks ?? [])
            ->map(function ($block) use ($expectedFilters) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'dashboard') {
                    return $block;
                }

                $existing = collect($block['selected_filters'] ?? [])
                    ->map(fn ($value) => (string) $value)
                    ->filter()
                    ->values()
                    ->all();

                $block['selected_filters'] = collect($expectedFilters)
                    ->merge($existing)
                    ->unique()
                    ->values()
                    ->all();

                return $block;
            })
            ->all();

        $homePage->update(['blocks' => $blocks]);
    }

    private function trainingDashboardWidgets(): array
    {
        return [
            [
                'title' => 'Total Participant',
                'chart_type' => 'stat',
                'sql_query' => 'SELECT COUNT(*) AS total_participants FROM participants p WHERE 1 = 1{{participants_filter:p}}{{participant_events_filter:p}}',
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'blue_pink',
                'background_color' => '#2B84F7',
                'text_color' => '#FFFFFF',
                'sort_order' => 1,
            ],
            [
                'title' => 'Total Project',
                'chart_type' => 'stat',
                'sql_query' => "SELECT COUNT(*) AS total_projects
FROM projects pr
INNER JOIN participants p ON p.id = pr.participant_id
WHERE 1 = 1{{participants_filter:p}}{{participant_events_filter:p}}",
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'teal_amber',
                'background_color' => '#049D9F',
                'text_color' => '#FFFFFF',
                'sort_order' => 2,
            ],
            [
                'title' => 'Average pre-test score',
                'chart_type' => 'stat',
                'sql_query' => "SELECT ROUND(AVG(w.pre_test_score), 1) AS average_pre_test_score
FROM training_event_workshop_scores w
INNER JOIN training_event_participants ep ON ep.id = w.training_event_participant_id
INNER JOIN participants p ON p.id = ep.participant_id
INNER JOIN training_events e ON e.id = ep.training_event_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}",
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'teal_amber',
                'background_color' => '#B92291',
                'text_color' => '#FFFFFF',
                'sort_order' => 3,
            ],
            [
                'title' => 'Average post-test score',
                'chart_type' => 'stat',
                'sql_query' => "SELECT ROUND(AVG(ep.final_score), 1) AS average_post_test_score
FROM training_event_participants ep
INNER JOIN participants p ON p.id = ep.participant_id
INNER JOIN training_events e ON e.id = ep.training_event_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}",
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'blue_pink',
                'background_color' => '#A70606',
                'text_color' => '#FFFFFF',
                'sort_order' => 4,
            ],
            [
                'title' => 'Male vs Female Participants',
                'chart_type' => 'pie',
                'sql_query' => "SELECT gender_label AS label, total AS value
FROM (
    SELECT
        CASE
            WHEN LOWER(TRIM(p.gender)) IN ('male', 'm') THEN 'Male'
            WHEN LOWER(TRIM(p.gender)) IN ('female', 'f') THEN 'Female'
            ELSE 'Other'
        END AS gender_label,
        COUNT(*) AS total
    FROM participants p
    WHERE 1 = 1{{participants_filter:p}}{{participant_events_filter:p}}
    GROUP BY gender_label
) AS gender_totals
WHERE gender_label IN ('Male', 'Female')",
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'emerald_slate',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'sort_order' => 5,
            ],
            [
                'title' => 'Overall Results',
                'chart_type' => 'bar',
                'sql_query' => "SELECT metric AS label, average_score AS value
FROM (
    SELECT 'Pre-test' AS metric, ROUND(AVG(w.pre_test_score), 1) AS average_score
    FROM training_event_workshop_scores w
    INNER JOIN training_event_participants ep ON ep.id = w.training_event_participant_id
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    UNION ALL
    SELECT 'Post-test' AS metric, ROUND(AVG(ep.final_score), 1) AS average_score
    FROM training_event_participants ep
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
) AS score_totals",
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'teal_amber',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'sort_order' => 6,
            ],
            [
                'title' => 'Project Comparison',
                'chart_type' => 'bar',
                'sql_query' => "SELECT label, series, value
FROM (
    SELECT o.title AS label, 'Pre-test' AS series, ROUND(AVG(w.pre_test_score), 1) AS value
    FROM training_organizers o
    INNER JOIN training_events e ON e.training_organizer_id = o.id
    INNER JOIN training_event_participants ep ON ep.training_event_id = e.id
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_event_workshop_scores w ON w.training_event_participant_id = ep.id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY o.title

    UNION ALL

    SELECT o.title AS label, 'Post-test' AS series, ROUND(AVG(ep.final_score), 1) AS value
    FROM training_organizers o
    INNER JOIN training_events e ON e.training_organizer_id = o.id
    INNER JOIN training_event_participants ep ON ep.training_event_id = e.id
    INNER JOIN participants p ON p.id = ep.participant_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY o.title
) AS organizer_scores
ORDER BY label, series",
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'teal_amber',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'sort_order' => 7,
            ],
            [
                'title' => 'Regional Comparison',
                'chart_type' => 'bar',
                'sql_query' => "SELECT label, series, value
FROM (
    SELECT r.name AS label, 'Pre-test' AS series, ROUND(AVG(w.pre_test_score), 1) AS value
    FROM regions r
    INNER JOIN participants p ON p.region_id = r.id
    INNER JOIN training_event_participants ep ON ep.participant_id = p.id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    INNER JOIN training_event_workshop_scores w ON w.training_event_participant_id = ep.id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY r.name

    UNION ALL

    SELECT r.name AS label, 'Post-test' AS series, ROUND(AVG(ep.final_score), 1) AS value
    FROM regions r
    INNER JOIN participants p ON p.region_id = r.id
    INNER JOIN training_event_participants ep ON ep.participant_id = p.id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY r.name
) AS region_scores
ORDER BY label, series",
                'refresh_interval_seconds' => null,
                'size_preset' => 'small',
                'width_mode' => 'columns',
                'width_columns' => 3,
                'width_px' => null,
                'height_px' => 180,
                'color_scheme' => 'teal_amber',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'sort_order' => 8,
            ],
            [
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
                'color_scheme' => 'emerald_slate',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'sort_order' => 9,
            ],
        ];
    }
}
