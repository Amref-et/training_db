<?php

namespace App\Http\Controllers;

use App\Models\DashboardTab;
use App\Models\DashboardWidget;
use App\Services\DashboardLayoutService;
use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardLayoutService $layoutService,
        private DashboardMetricsService $metrics
    )
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->layoutService->ensureDefaultTabs($user);
        $filterDefinitions = $this->metrics->filterDefinitions();
        $filters = $this->metrics->resolveFilters($request->all(), $filterDefinitions);
        $isEditing = $request->boolean('edit');

        $tabs = $user->dashboardTabs()->with('widgets')->get();
        $activeTab = $tabs->firstWhere('id', (int) $request->integer('tab_id'))
            ?? $tabs->firstWhere('is_default', true)
            ?? $tabs->first();

        $activeWidgets = $activeTab
            ? $activeTab->widgets->where('is_active', true)->sortBy('sort_order')->values()
            : collect();

        $widgetPayloads = $activeWidgets->mapWithKeys(function (DashboardWidget $widget) use ($filters) {
            try {
                return [
                    $widget->id => $this->layoutService->executeWidget($widget, $filters),
                ];
            } catch (Throwable $e) {
                return [
                    $widget->id => [
                        'type' => 'error',
                        'message' => $e->getMessage(),
                    ],
                ];
            }
        })->all();

        return view('admin.dashboard.index', [
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'activeWidgets' => $activeWidgets,
            'widgetPayloads' => $widgetPayloads,
            'filters' => $filters,
            'filterDefinitions' => $filterDefinitions,
            'isEditing' => $isEditing,
            'chartTypes' => DashboardWidget::CHART_TYPES,
            'sizePresets' => DashboardWidget::SIZE_PRESETS,
            'widthModes' => DashboardWidget::WIDTH_MODES,
            'colorSchemes' => DashboardWidget::COLOR_SCHEMES,
            'widgetWidthStyles' => $activeWidgets->mapWithKeys(fn (DashboardWidget $widget) => [
                $widget->id => $this->layoutService->widthStyle($widget),
            ])->all(),
        ]);
    }

    public function storeTab(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        try {
            $tab = $this->layoutService->createTab($request->user(), (string) $request->string('name'));
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        $this->audit()->logModelCreated($tab, 'Dashboard tab created');

        return redirect()
            ->route('admin.dashboard', $this->dashboardQueryParams($request, $tab->id))
            ->with('success', 'Dashboard tab created.');
    }

    public function updateTab(Request $request, DashboardTab $tab): RedirectResponse
    {
        $this->authorizeTab($request, $tab);
        $beforeState = $this->audit()->snapshotModel($tab);

        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        try {
            $payload = [
                'name' => (string) $request->string('name'),
            ];
            if ($request->boolean('is_default')) {
                $payload['is_default'] = true;
            }

            $this->layoutService->updateTab($tab, $payload);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        $tab->refresh();
        $this->audit()->logModelUpdated($tab, $beforeState, 'Dashboard tab updated');

        return redirect()
            ->route('admin.dashboard', $this->dashboardQueryParams($request, $tab->id))
            ->with('success', 'Dashboard tab updated.');
    }

    public function destroyTab(Request $request, DashboardTab $tab): RedirectResponse
    {
        $this->authorizeTab($request, $tab);
        $beforeState = $this->audit()->snapshotModel($tab);
        $tabId = $tab->id;
        $tabName = $tab->name;
        $this->layoutService->deleteTab($tab);
        $this->audit()->logModelDeleted(DashboardTab::class, $tabId, $tabName, $beforeState, 'Dashboard tab deleted');

        return redirect()
            ->route('admin.dashboard', $this->dashboardQueryParams($request))
            ->with('success', 'Dashboard tab deleted.');
    }

    public function storeWidget(Request $request, DashboardTab $tab): RedirectResponse
    {
        $this->authorizeTab($request, $tab);
        $this->validateWidgetRequest($request);

        try {
            $widget = $this->layoutService->createWidget($tab, $request->all());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['sql_query' => $e->getMessage()]);
        }

        $this->audit()->logModelCreated($widget, 'Dashboard widget created');

        return redirect()
            ->route('admin.dashboard', $this->dashboardQueryParams($request, $tab->id))
            ->with('success', 'Widget added.');
    }

    public function updateWidget(Request $request, DashboardWidget $widget): RedirectResponse
    {
        $this->authorizeWidget($request, $widget);
        $this->validateWidgetRequest($request);
        $beforeState = $this->audit()->snapshotModel($widget);

        try {
            $this->layoutService->updateWidget($widget, $request->all());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['sql_query' => $e->getMessage()]);
        }

        $widget->refresh();
        $this->audit()->logModelUpdated($widget, $beforeState, 'Dashboard widget updated');

        return redirect()
            ->route('admin.dashboard', $this->dashboardQueryParams($request, $widget->dashboard_tab_id))
            ->with('success', 'Widget updated.');
    }

    public function destroyWidget(Request $request, DashboardWidget $widget): RedirectResponse
    {
        $this->authorizeWidget($request, $widget);
        $tabId = $widget->dashboard_tab_id;
        $beforeState = $this->audit()->snapshotModel($widget);
        $widgetId = $widget->id;
        $widgetTitle = $widget->title;
        $widget->delete();
        $this->audit()->logModelDeleted(DashboardWidget::class, $widgetId, $widgetTitle, $beforeState, 'Dashboard widget deleted');

        return redirect()
            ->route('admin.dashboard', $this->dashboardQueryParams($request, $tabId))
            ->with('success', 'Widget removed.');
    }

    public function reorderWidgets(Request $request, DashboardTab $tab): JsonResponse
    {
        $this->authorizeTab($request, $tab);

        $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $this->layoutService->reorderWidgets($tab, $request->input('ordered_ids', []));

        return response()->json(['status' => 'ok']);
    }

    public function widgetData(Request $request, DashboardWidget $widget): JsonResponse
    {
        $this->authorizeWidget($request, $widget);
        $filters = $this->metrics->resolveFilters($request->all());

        try {
            return response()->json([
                'status' => 'ok',
                'data' => $this->layoutService->executeWidget($widget, $filters),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function exportLayout(Request $request)
    {
        $payload = $this->layoutService->exportLayout($request->user());
        $filename = 'dashboard-layout-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function importLayout(Request $request): RedirectResponse
    {
        $request->validate([
            'layout_file' => ['required', 'file', 'mimes:json,txt'],
        ]);

        $content = (string) file_get_contents($request->file('layout_file')->getRealPath());
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['layout_file' => 'Invalid JSON format.']);
        }

        try {
            $count = $this->layoutService->importLayout($request->user(), $decoded);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['layout_file' => $e->getMessage()]);
        }

        $this->audit()->logCustom('Dashboard layout imported', 'dashboard.layout.imported', [
            'metadata' => ['tab_count' => $count],
        ]);

        return redirect()
            ->route('admin.dashboard', $this->dashboardQueryParams($request))
            ->with('success', "Imported {$count} dashboard tab(s).");
    }

    private function validateWidgetRequest(Request $request): void
    {
        $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'chart_type' => ['required', 'string', 'in:'.implode(',', DashboardWidget::CHART_TYPES)],
            'sql_query' => ['required', 'string'],
            'refresh_interval_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'size_preset' => ['nullable', 'string', 'in:'.implode(',', DashboardWidget::SIZE_PRESETS)],
            'width_mode' => ['nullable', 'string', 'in:'.implode(',', DashboardWidget::WIDTH_MODES)],
            'width_columns' => ['nullable', 'integer', 'min:1', 'max:12'],
            'width_px' => ['nullable', 'integer', 'min:220', 'max:2200'],
            'height_px' => ['nullable', 'integer', 'min:180', 'max:1000'],
            'color_scheme' => ['nullable', 'string', 'in:'.implode(',', DashboardWidget::COLOR_SCHEMES)],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeTab(Request $request, DashboardTab $tab): void
    {
        abort_unless($tab->user_id === $request->user()->id, 403);
    }

    private function authorizeWidget(Request $request, DashboardWidget $widget): void
    {
        $widget->loadMissing('tab');
        abort_unless($widget->tab && $widget->tab->user_id === $request->user()->id, 403);
    }

    private function dashboardQueryParams(Request $request, ?int $tabId = null): array
    {
        $params = [];
        if ($tabId) {
            $params['tab_id'] = $tabId;
        }
        if ($request->boolean('edit')) {
            $params['edit'] = '1';
        }

        foreach ($this->metrics->resolveFilters($request->all()) as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $params[$key] = $value;
        }

        return $params;
    }
}
