<?php

namespace App\Services;

use App\Models\DashboardTab;
use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DashboardLayoutService
{
    public function ensureDefaultTabs(User $user): void
    {
        if ($user->dashboardTabs()->exists()) {
            return;
        }

        $trainingTab = $this->createTab($user, 'Training Dashboard', true);
        $reportsTab = $this->createTab($user, 'Reports Dashboard', false);

        $this->createWidget($trainingTab, [
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
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 280,
            'color_scheme' => 'blue_pink',
        ]);

        $this->createWidget($trainingTab, [
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
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 280,
            'color_scheme' => 'teal_amber',
        ]);

        $this->createWidget($trainingTab, [
            'title' => 'Organizer Comparison',
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
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 280,
            'color_scheme' => 'emerald_slate',
        ]);

        $this->createWidget($trainingTab, [
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
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 280,
            'color_scheme' => 'sunset',
        ]);

        $this->createWidget($reportsTab, [
            'title' => 'Total Participants',
            'chart_type' => 'stat',
            'sql_query' => 'SELECT COUNT(*) AS total_participants FROM participants p WHERE 1 = 1{{participants_filter:p}}{{participant_events_filter:p}}',
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 240,
            'color_scheme' => 'teal_amber',
        ]);

        $this->createWidget($reportsTab, [
            'title' => 'Total Projects',
            'chart_type' => 'stat',
            'sql_query' => "SELECT COUNT(*) AS total_projects
FROM projects pr
INNER JOIN participants p ON p.id = pr.participant_id
WHERE 1 = 1{{participants_filter:p}}{{participant_events_filter:p}}",
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 240,
            'color_scheme' => 'emerald_slate',
        ]);

        $this->createWidget($reportsTab, [
            'title' => 'Average Pre-result Score',
            'chart_type' => 'stat',
            'sql_query' => "SELECT ROUND(AVG(w.pre_test_score), 1) AS average_pre_result_score
FROM training_event_workshop_scores w
INNER JOIN training_event_participants ep ON ep.id = w.training_event_participant_id
INNER JOIN participants p ON p.id = ep.participant_id
INNER JOIN training_events e ON e.id = ep.training_event_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}",
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 240,
            'color_scheme' => 'teal_amber',
        ]);

        $this->createWidget($reportsTab, [
            'title' => 'Average Post-result Score',
            'chart_type' => 'stat',
            'sql_query' => "SELECT ROUND(AVG(ep.final_score), 1) AS average_post_result_score
FROM training_event_participants ep
INNER JOIN participants p ON p.id = ep.participant_id
INNER JOIN training_events e ON e.id = ep.training_event_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}",
            'size_preset' => 'small',
            'width_mode' => 'columns',
            'width_columns' => 3,
            'height_px' => 240,
            'color_scheme' => 'emerald_slate',
        ]);

        $this->createWidget($reportsTab, [
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
            'size_preset' => 'medium',
            'width_mode' => 'columns',
            'width_columns' => 6,
            'height_px' => 280,
            'color_scheme' => 'blue_pink',
        ]);

        $this->createWidget($reportsTab, [
            'title' => 'Average Scores',
            'chart_type' => 'table',
            'sql_query' => "SELECT metric, score
FROM (
    SELECT 'Average Pre-test' AS metric, ROUND(AVG(w.pre_test_score), 1) AS score
    FROM training_event_workshop_scores w
    INNER JOIN training_event_participants ep ON ep.id = w.training_event_participant_id
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    UNION ALL
    SELECT 'Average Post-test' AS metric, ROUND(AVG(ep.final_score), 1) AS score
    FROM training_event_participants ep
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
) AS score_summary",
            'size_preset' => 'medium',
            'width_mode' => 'columns',
            'width_columns' => 6,
            'height_px' => 280,
            'color_scheme' => 'teal_amber',
        ]);

        $this->createWidget($reportsTab, [
            'title' => 'Top Trainings by Average Final Score',
            'chart_type' => 'bar',
            'sql_query' => "SELECT t.title AS label, ROUND(AVG(ep.final_score), 1) AS value
FROM trainings t
INNER JOIN training_events e ON e.training_id = t.id
INNER JOIN training_event_participants ep ON ep.training_event_id = e.id
INNER JOIN participants p ON p.id = ep.participant_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
GROUP BY t.title
ORDER BY value DESC
LIMIT 10",
            'size_preset' => 'medium',
            'width_mode' => 'columns',
            'width_columns' => 6,
            'height_px' => 280,
            'color_scheme' => 'blue_pink',
        ]);
    }

    public function createTab(User $user, string $name, bool $isDefault = false): DashboardTab
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Tab name is required.');
        }

        if ($isDefault) {
            $user->dashboardTabs()->update(['is_default' => false]);
        }

        $maxOrder = (int) $user->dashboardTabs()->max('sort_order');

        return DashboardTab::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => $this->nextUniqueSlug($user, $name),
            'sort_order' => $maxOrder + 1,
            'is_default' => $isDefault || ! $user->dashboardTabs()->exists(),
        ]);
    }

    public function updateTab(DashboardTab $tab, array $payload): DashboardTab
    {
        $name = trim((string) ($payload['name'] ?? $tab->name));
        if ($name === '') {
            throw new InvalidArgumentException('Tab name is required.');
        }

        $isDefault = (bool) ($payload['is_default'] ?? false);
        if ($isDefault) {
            DashboardTab::query()
                ->where('user_id', $tab->user_id)
                ->where('id', '!=', $tab->id)
                ->update(['is_default' => false]);
        }

        $tab->fill([
            'name' => $name,
            'slug' => $this->nextUniqueSlug($tab->user, $name, $tab->id),
            'is_default' => $isDefault || ($tab->is_default && ! isset($payload['is_default'])),
        ]);
        $tab->save();

        return $tab->refresh();
    }

    public function deleteTab(DashboardTab $tab): void
    {
        $user = $tab->user;
        $wasDefault = (bool) $tab->is_default;
        $tab->delete();

        $remainingTabs = $user->dashboardTabs()->orderBy('sort_order')->get();
        if ($remainingTabs->isEmpty()) {
            $this->createTab($user, 'Dashboard', true);

            return;
        }

        if ($wasDefault) {
            $remainingTabs->first()->update(['is_default' => true]);
        }
    }

    public function createWidget(DashboardTab $tab, array $payload): DashboardWidget
    {
        $normalized = $this->normalizeWidgetPayload($payload);
        $normalized['dashboard_tab_id'] = $tab->id;
        $normalized['sort_order'] = ((int) $tab->widgets()->max('sort_order')) + 1;

        return DashboardWidget::query()->create($normalized);
    }

    public function updateWidget(DashboardWidget $widget, array $payload): DashboardWidget
    {
        $widget->fill($this->normalizeWidgetPayload($payload, $widget));
        $widget->save();

        return $widget->refresh();
    }

    public function reorderWidgets(DashboardTab $tab, array $orderedWidgetIds): void
    {
        $ids = collect($orderedWidgetIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $knownIds = $tab->widgets()->pluck('id')->all();
        $order = 1;
        foreach ($ids as $id) {
            if (! in_array($id, $knownIds, true)) {
                continue;
            }

            DashboardWidget::query()
                ->where('id', $id)
                ->where('dashboard_tab_id', $tab->id)
                ->update(['sort_order' => $order]);

            $order++;
        }

        foreach ($knownIds as $id) {
            if ($ids->contains($id)) {
                continue;
            }

            DashboardWidget::query()
                ->where('id', $id)
                ->where('dashboard_tab_id', $tab->id)
                ->update(['sort_order' => $order]);

            $order++;
        }
    }

    public function exportLayout(User $user): array
    {
        $tabs = $user->dashboardTabs()->with('widgets')->get();

        return [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'tabs' => $tabs->map(function (DashboardTab $tab) {
                return [
                    'name' => $tab->name,
                    'slug' => $tab->slug,
                    'sort_order' => $tab->sort_order,
                    'is_default' => $tab->is_default,
                    'widgets' => $tab->widgets
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn (DashboardWidget $widget) => [
                            'title' => $widget->title,
                            'chart_type' => $widget->chart_type,
                            'sql_query' => $widget->sql_query,
                            'refresh_interval_seconds' => $widget->refresh_interval_seconds,
                            'size_preset' => $widget->size_preset,
                            'width_mode' => $widget->width_mode,
                            'width_columns' => $widget->width_columns,
                            'width_px' => $widget->width_px,
                            'height_px' => $widget->height_px,
                            'color_scheme' => $widget->color_scheme,
                            'sort_order' => $widget->sort_order,
                            'is_active' => $widget->is_active,
                        ])
                        ->all(),
                ];
            })->all(),
        ];
    }

    public function importLayout(User $user, array $payload): int
    {
        $tabs = collect($payload['tabs'] ?? [])->filter(fn ($tab) => is_array($tab))->values();
        if ($tabs->isEmpty()) {
            throw new InvalidArgumentException('Invalid import file. No tabs were found.');
        }

        $createdTabs = 0;
        DB::transaction(function () use ($tabs, $user, &$createdTabs) {
            $user->dashboardTabs()->update(['is_default' => false]);

            foreach ($tabs as $index => $tabPayload) {
                $tabName = trim((string) ($tabPayload['name'] ?? 'Imported Dashboard'));
                $tab = $this->createTab($user, $tabName === '' ? 'Imported Dashboard' : $tabName, $index === 0);
                $tab->update(['sort_order' => $index + 1]);
                $createdTabs++;

                $widgets = collect($tabPayload['widgets'] ?? [])->filter(fn ($widget) => is_array($widget))->values();
                foreach ($widgets as $widgetIndex => $widgetPayload) {
                    $widget = $this->createWidget($tab, $widgetPayload);
                    $widget->update(['sort_order' => $widgetIndex + 1]);
                }
            }
        });

        return $createdTabs;
    }

    public function executeWidget(DashboardWidget $widget, array $filters = []): array
    {
        $sql = $this->interpolateFilterTokens((string) $widget->sql_query, $filters);
        $sql = trim($sql);
        $this->assertReadOnlyQuery($sql);

        $rawRows = collect(DB::select($sql))
            ->map(fn ($row) => (array) $row)
            ->take(500)
            ->values();

        $columns = $rawRows->isNotEmpty()
            ? array_keys($rawRows->first())
            : [];

        if ($widget->chart_type === 'table') {
            return [
                'type' => 'table',
                'columns' => $columns,
                'rows' => $rawRows->all(),
            ];
        }

        if ($widget->chart_type === 'stat') {
            $value = null;
            $label = $widget->title;

            if ($rawRows->isNotEmpty()) {
                $firstRow = $rawRows->first();
                if (! empty($columns)) {
                    $firstKey = $columns[0];
                    $value = $firstRow[$firstKey] ?? null;
                    $label = Str::headline(str_replace('_', ' ', (string) $firstKey));
                }
            }

            return [
                'type' => 'stat',
                'label' => $label,
                'value' => $value,
            ];
        }

        return $this->buildChartPayload($rawRows, $columns);
    }

    public function widthStyle(DashboardWidget $widget): string
    {
        if ($widget->width_mode === 'pixels' && $widget->width_px) {
            $px = max(220, min((int) $widget->width_px, 2200));

            return "width:min(100%, {$px}px);";
        }

        $columns = max(1, min((int) $widget->width_columns, 12));
        $value = "calc((100% - (11 * var(--widget-gap))) / 12 * {$columns} + ({$columns} - 1) * var(--widget-gap))";

        return "width:{$value};";
    }

    private function normalizeWidgetPayload(array $payload, ?DashboardWidget $existing = null): array
    {
        $title = trim((string) ($payload['title'] ?? $existing?->title ?? 'Widget'));
        $chartType = (string) ($payload['chart_type'] ?? $existing?->chart_type ?? 'bar');
        $sqlQuery = trim((string) ($payload['sql_query'] ?? $existing?->sql_query ?? ''));
        $refresh = isset($payload['refresh_interval_seconds']) ? (int) $payload['refresh_interval_seconds'] : ($existing?->refresh_interval_seconds ?? null);
        $sizePreset = (string) ($payload['size_preset'] ?? $existing?->size_preset ?? 'medium');
        $widthMode = (string) ($payload['width_mode'] ?? $existing?->width_mode ?? 'columns');
        $widthColumns = isset($payload['width_columns']) ? (int) $payload['width_columns'] : (int) ($existing?->width_columns ?? 6);
        $widthPx = isset($payload['width_px']) ? (int) $payload['width_px'] : ($existing?->width_px ? (int) $existing->width_px : null);
        $heightPx = isset($payload['height_px']) ? (int) $payload['height_px'] : (int) ($existing?->height_px ?? 280);
        $colorScheme = (string) ($payload['color_scheme'] ?? $existing?->color_scheme ?? 'teal_amber');
        $isActive = isset($payload['is_active']) ? (bool) $payload['is_active'] : (bool) ($existing?->is_active ?? true);

        if (! in_array($chartType, DashboardWidget::CHART_TYPES, true)) {
            throw new InvalidArgumentException('Invalid chart type.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('Widget title is required.');
        }

        if ($sqlQuery === '') {
            throw new InvalidArgumentException('SQL query is required.');
        }

        $this->assertReadOnlyQuery($sqlQuery);

        if (! in_array($sizePreset, DashboardWidget::SIZE_PRESETS, true)) {
            $sizePreset = 'medium';
        }

        if ($sizePreset !== 'custom') {
            $widthMode = 'columns';
            $widthColumns = match ($sizePreset) {
                'small' => 3,
                'medium' => 6,
                'large' => 9,
                'full' => 12,
                default => 6,
            };
            $widthPx = null;
        } else {
            if (! in_array($widthMode, DashboardWidget::WIDTH_MODES, true)) {
                $widthMode = 'columns';
            }

            if ($widthMode === 'pixels') {
                $widthPx = max(220, min((int) ($widthPx ?: 480), 2200));
                $widthColumns = 12;
            } else {
                $widthColumns = max(1, min($widthColumns, 12));
                $widthPx = null;
            }
        }

        if (! in_array($colorScheme, DashboardWidget::COLOR_SCHEMES, true)) {
            $colorScheme = 'teal_amber';
        }

        $heightPx = max(180, min($heightPx, 1000));
        $refresh = $refresh && $refresh > 0 ? max(5, min($refresh, 86400)) : null;

        return [
            'title' => $title,
            'chart_type' => $chartType,
            'sql_query' => $sqlQuery,
            'refresh_interval_seconds' => $refresh,
            'size_preset' => $sizePreset,
            'width_mode' => $widthMode,
            'width_columns' => $widthColumns,
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'color_scheme' => $colorScheme,
            'is_active' => $isActive,
        ];
    }

    private function assertReadOnlyQuery(string $sql): void
    {
        if ($sql === '') {
            throw new InvalidArgumentException('SQL query is required.');
        }

        if (Str::length($sql) > 20000) {
            throw new InvalidArgumentException('SQL query is too long.');
        }

        $lowerSql = strtolower($sql);
        if (! preg_match('/^\s*(select|with)\b/i', $sql)) {
            throw new InvalidArgumentException('Only SELECT queries are allowed.');
        }

        if (str_contains($sql, ';')) {
            throw new InvalidArgumentException('Semicolons are not allowed in dashboard SQL queries.');
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|truncate|create|grant|revoke|replace)\b/i', $lowerSql)) {
            throw new InvalidArgumentException('Only read-only SELECT queries are allowed.');
        }
    }

    private function buildChartPayload($rows, array $columns): array
    {
        if ($rows->isEmpty() || count($columns) < 2) {
            return [
                'type' => 'chart',
                'labels' => [],
                'datasets' => [],
            ];
        }

        $firstColumn = $columns[0];
        $secondColumn = $columns[1];
        $thirdColumn = $columns[2] ?? null;

        $firstRow = $rows->first();
        $secondIsNumeric = $this->isNumericValue($firstRow[$secondColumn] ?? null);
        $thirdIsNumeric = $thirdColumn ? $this->isNumericValue($firstRow[$thirdColumn] ?? null) : false;

        if ($thirdColumn && ! $secondIsNumeric && $thirdIsNumeric) {
            $labels = $rows->pluck($firstColumn)->map(fn ($value) => (string) $value)->unique()->values();
            $series = $rows->pluck($secondColumn)
                ->map(fn ($value) => (string) $value)
                ->unique()
                ->sort(fn ($left, $right) => $this->compareSeriesLabels((string) $left, (string) $right))
                ->values();

            $datasets = $series->map(function ($seriesName) use ($rows, $labels, $firstColumn, $secondColumn, $thirdColumn) {
                $indexed = $rows
                    ->where($secondColumn, $seriesName)
                    ->keyBy(fn ($row) => (string) $row[$firstColumn]);

                return [
                    'label' => $seriesName,
                    'data' => $labels->map(function ($label) use ($indexed, $thirdColumn) {
                        $row = $indexed->get((string) $label);

                        return (float) (($row[$thirdColumn] ?? 0));
                    })->all(),
                ];
            })->all();

            return [
                'type' => 'chart',
                'labels' => $labels->all(),
                'datasets' => $datasets,
            ];
        }

        if ($secondIsNumeric && count($columns) === 2) {
            return [
                'type' => 'chart',
                'labels' => $rows->pluck($firstColumn)->map(fn ($value) => (string) $value)->all(),
                'datasets' => [[
                    'label' => Str::headline(str_replace('_', ' ', (string) $secondColumn)),
                    'data' => $rows->pluck($secondColumn)->map(fn ($value) => (float) $value)->all(),
                ]],
            ];
        }

        $numericColumns = collect(array_slice($columns, 1))
            ->filter(fn ($column) => $this->columnLooksNumeric($rows, $column))
            ->values();

        if ($numericColumns->isEmpty()) {
            return [
                'type' => 'chart',
                'labels' => [],
                'datasets' => [],
            ];
        }

        return [
            'type' => 'chart',
            'labels' => $rows->pluck($firstColumn)->map(fn ($value) => (string) $value)->all(),
            'datasets' => $numericColumns->map(fn ($column) => [
                'label' => Str::headline(str_replace('_', ' ', (string) $column)),
                'data' => $rows->pluck($column)->map(fn ($value) => (float) $value)->all(),
            ])->all(),
        ];
    }

    private function columnLooksNumeric($rows, string $column): bool
    {
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if ($this->isNumericValue($value)) {
                return true;
            }
        }

        return false;
    }

    private function isNumericValue($value): bool
    {
        return is_numeric($value);
    }

    private function compareSeriesLabels(string $left, string $right): int
    {
        $leftRank = $this->seriesRank($left);
        $rightRank = $this->seriesRank($right);

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strcasecmp($left, $right);
    }

    private function seriesRank(string $label): int
    {
        $normalized = strtolower(trim($label));

        return match (true) {
            str_contains($normalized, 'pre') => 0,
            str_contains($normalized, 'post') => 1,
            str_contains($normalized, 'mid') => 2,
            default => 3,
        };
    }

    private function interpolateFilterTokens(string $sql, array $filters): string
    {
        $sql = preg_replace_callback('/\{\{participants_filter(?::([a-zA-Z_][a-zA-Z0-9_]*))?\}\}/', function ($matches) use ($filters) {
            $alias = $matches[1] ?? 'participants';

            return $this->participantsFilterClause((string) $alias, $filters);
        }, $sql) ?? $sql;

        $sql = preg_replace_callback('/\{\{participant_events_filter(?::([a-zA-Z_][a-zA-Z0-9_]*))?\}\}/', function ($matches) use ($filters) {
            $alias = $matches[1] ?? 'participants';

            return $this->participantEventsFilterClause((string) $alias, $filters);
        }, $sql) ?? $sql;

        $sql = preg_replace_callback('/\{\{events_filter(?::([a-zA-Z_][a-zA-Z0-9_]*))?\}\}/', function ($matches) use ($filters) {
            $alias = $matches[1] ?? 'training_events';

            return $this->eventsFilterClause((string) $alias, $filters);
        }, $sql) ?? $sql;

        return $sql;
    }

    private function participantsFilterClause(string $alias, array $filters): string
    {
        $parts = [];

        if (! empty($filters['gender'])) {
            $parts[] = "{$alias}.gender = ".$this->quoteSqlLiteral((string) $filters['gender']);
        }

        if (! empty($filters['region_id'])) {
            $parts[] = "{$alias}.region_id = ".(int) $filters['region_id'];
        }

        if (! empty($filters['organization_id'])) {
            $parts[] = "{$alias}.organization_id = ".(int) $filters['organization_id'];
        }

        if (! empty($filters['profession'])) {
            $parts[] = "{$alias}.profession = ".$this->quoteSqlLiteral((string) $filters['profession']);
        }

        return $parts === []
            ? ''
            : ' AND '.implode(' AND ', $parts);
    }

    private function participantEventsFilterClause(string $participantAlias, array $filters): string
    {
        if (! $this->hasEventsFilters($filters)) {
            return '';
        }

        return " AND EXISTS (
            SELECT 1
            FROM training_event_participants epf
            INNER JOIN training_events ef ON ef.id = epf.training_event_id
            WHERE epf.participant_id = {$participantAlias}.id".$this->eventsFilterClause('ef', $filters).'
        )';
    }

    private function eventsFilterClause(string $alias, array $filters): string
    {
        $parts = [];

        if (! empty($filters['training_organizer_id'])) {
            $parts[] = "{$alias}.training_organizer_id = ".(int) $filters['training_organizer_id'];
        }

        if (! empty($filters['training_id'])) {
            $parts[] = "{$alias}.training_id = ".(int) $filters['training_id'];
        }

        if (! empty($filters['status'])) {
            $parts[] = "{$alias}.status = ".$this->quoteSqlLiteral((string) $filters['status']);
        }

        return $parts === []
            ? ''
            : ' AND '.implode(' AND ', $parts);
    }

    private function hasEventsFilters(array $filters): bool
    {
        return ! empty($filters['training_organizer_id'])
            || ! empty($filters['training_id'])
            || ! empty($filters['status']);
    }

    private function quoteSqlLiteral(string $value): string
    {
        return DB::connection()->getPdo()->quote($value);
    }

    private function nextUniqueSlug(User $user, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'dashboard';
        }

        $slug = $base;
        $index = 2;

        while (
            DashboardTab::query()
                ->where('user_id', $user->id)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }
}
