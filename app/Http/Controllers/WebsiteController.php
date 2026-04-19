<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\WebsiteMenuItem;
use App\Models\WebsiteSetting;
use App\Services\DashboardMetricsService;
use App\Support\PageSectionRegistry;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function __construct(private DashboardMetricsService $metrics)
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

    private function pageView(?ContentPage $page): View
    {
        $sections = PageSectionRegistry::forDisplay($page?->sections ?? [], $page?->blocks ?? []);
        $hasDashboardBlock = collect($sections)
            ->flatMap(fn (array $section) => $section['blocks'] ?? [])
            ->contains(fn (array $block) => ($block['type'] ?? null) === 'dashboard');

        $navigationPages = ContentPage::published()->orderBy('title')->get();
        $navigationMenu = WebsiteMenuItem::tree();

        return view('website.page', [
            'page' => $page,
            'sections' => $sections,
            'dashboardSnapshot' => $hasDashboardBlock ? $this->metrics->summary(
                $this->metrics->resolveFilters(request()->all())
            ) : null,
            'navigationPages' => $navigationPages,
            'navigationMenu' => $navigationMenu,
            'websiteSettings' => WebsiteSetting::current(),
        ]);
    }
}

