<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\DashboardTab;
use App\Models\DashboardWidget;
use App\Models\WebsiteMenuItem;
use App\Models\WebsiteSetting;
use App\Services\DashboardLayoutService;
use App\Services\DashboardMetricsService;
use App\Support\PageSectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class WebsiteController extends Controller
{
    public function __construct(
        private DashboardMetricsService $metrics,
        private DashboardLayoutService $layoutService
    )
    {
    }

    public function home(): View
    {
        $page = ContentPage::published()
            ->where('is_homepage', true)
            ->latest()
            ->first() ?? ContentPage::published()->latest()->first();

        return $this->pageView($page);
    }

    public function show(string $slug): View
    {
        $page = ContentPage::published()->where('slug', $slug)->firstOrFail();

        return $this->pageView($page);
    }

    public function organizationOptions(Request $request): JsonResponse
    {
        return response()->json([
            'options' => $this->metrics->organizationFilterOptions(
                $request->string('q')->toString(),
                $request->input('selected_id'),
                $request->input('region_id')
            ),
        ]);
    }

    private function pageView(?ContentPage $page): View
    {
        $sections = PageSectionRegistry::forDisplay($page?->sections ?? [], $page?->blocks ?? []);
        $hasDashboardBlock = collect($sections)
            ->flatMap(fn (array $section) => $section['blocks'] ?? [])
            ->contains(fn (array $block) => ($block['type'] ?? null) === 'dashboard');
        $websiteSettings = WebsiteSetting::current();
        $filterDefinitions = $hasDashboardBlock ? $this->metrics->filterDefinitions() : [];
        $filters = $hasDashboardBlock ? $this->metrics->resolveFilters(request()->all(), $filterDefinitions) : [];
        $publicDashboard = $this->publicHomepageDashboard($page, $hasDashboardBlock, $websiteSettings, $filters, $filterDefinitions);

        $navigationPages = ContentPage::published()->orderBy('title')->get();
        $navigationMenu = WebsiteMenuItem::tree();

        return view('website.page', [
            'page' => $page,
            'sections' => $sections,
            'dashboardSnapshot' => $hasDashboardBlock && ! $publicDashboard ? $this->metrics->summary(
                $filters
            ) : null,
            'publicDashboard' => $publicDashboard,
            'selectedDashboardOrganizationFilter' => $hasDashboardBlock
                ? $this->metrics->selectedOrganizationFilterOption($filters['organization_id'] ?? null)
                : null,
            'navigationPages' => $navigationPages,
            'navigationMenu' => $navigationMenu,
            'websiteSettings' => $websiteSettings,
        ]);
    }

    private function publicHomepageDashboard(
        ?ContentPage $page,
        bool $hasDashboardBlock,
        WebsiteSetting $websiteSettings,
        array $filters,
        array $filterDefinitions
    ): ?array {
        if (! $hasDashboardBlock || ! $this->isHomepage($page)) {
            return null;
        }

        $tabId = (int) ($websiteSettings->public_home_dashboard_tab_id ?? 0);
        if ($tabId < 1) {
            return null;
        }

        $tab = DashboardTab::query()->with('widgets', 'user')->find($tabId);
        if (! $tab) {
            return null;
        }

        $widgets = $tab->widgets
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();

        return [
            'tab' => $tab,
            'filters' => $filters,
            'filterDefinitions' => $filterDefinitions,
            'widgets' => $widgets,
            'widgetPayloads' => $this->executePublicWidgets($widgets, $filters),
            'widgetWidthStyles' => $widgets->mapWithKeys(fn (DashboardWidget $widget) => [
                $widget->id => $this->layoutService->widthStyle($widget),
            ])->all(),
        ];
    }

    private function executePublicWidgets(Collection $widgets, array $filters): array
    {
        return $widgets->mapWithKeys(function (DashboardWidget $widget) use ($filters) {
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
    }

    private function isHomepage(?ContentPage $page): bool
    {
        return (bool) ($page?->is_homepage)
            || ($page?->slug !== null && trim((string) $page->slug) === 'home')
            || request()->routeIs('home');
    }
}

