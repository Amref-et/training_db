<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page?->meta_title ?: $page?->title ?: ($websiteSettings->site_name ?? config('app.name', 'HIL Website')) }}</title>
    @php
        $settings = $websiteSettings ?? \App\Models\WebsiteSetting::current();
        $siteName = $settings->site_name ?: config('app.name', 'HIL Website');
        $siteTagline = $settings->site_tagline;
        $resolveMediaUrl = function (?string $value): ?string {
            if (! $value) {
                return null;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            if (str_starts_with($value, '/')) {
                return url(ltrim($value, '/'));
            }

            return \Illuminate\Support\Facades\Storage::disk('public')->url($value);
        };
        $headerLogoUrl = $resolveMediaUrl($settings->header_logo_url);
        $headerLogoHeight = max(24, (int) ($settings->header_logo_height ?? 56));
        $footerLogoUrl = $resolveMediaUrl($settings->footer_logo_url);
        $headerBackgroundColor = $settings->header_background_color ?: '#ffffff';
        $headerTextColor = $settings->header_text_color ?: '#0f172a';
        $headerLinkColor = $settings->header_link_color ?: '#334155';
        $bodyBackgroundColor = $settings->body_background_color ?: '#f8fafc';
        $bodyTextColor = $settings->body_text_color ?: '#0f172a';
        $bodyPanelColor = $settings->body_panel_color ?: '#ffffff';
        $bodyAccentColor = $settings->body_accent_color ?: ($settings->primary_color ?: '#0f766e');
        $footerBackgroundColor = $settings->footer_background_color ?: ($settings->secondary_color ?: '#0f172a');
        $footerTextColor = $settings->footer_text_color ?: '#e2e8f0';
        $footerLinkColor = $settings->footer_link_color ?: '#cbd5e1';
        $radiusSm = max(0, (int) ($settings->radius_sm ?? 10));
        $radiusMd = max(0, (int) ($settings->radius_md ?? 14));
        $radiusLg = max(0, (int) ($settings->radius_lg ?? 18));
        $radiusXl = max(0, (int) ($settings->radius_xl ?? 24));
        $radiusPill = max(0, (int) ($settings->radius_pill ?? 999));
        $customCss = trim((string) ($settings->custom_css ?? ''));
        $customJs = trim((string) ($settings->custom_js ?? ''));
        $safeCustomCss = str_ireplace('</style', '<\\/style', $customCss);
        $safeCustomJs = str_ireplace('</script', '<\\/script', $customJs);
        $pageSections = $sections ?? [];
        $hasSections = count($pageSections) > 0;
        $hasHeroBlock = collect($pageSections)
            ->flatMap(fn ($section) => $section['blocks'] ?? [])
            ->contains(fn ($block) => ($block['type'] ?? null) === 'hero');
        $hasDashboardBlock = collect($pageSections)
            ->flatMap(fn ($section) => $section['blocks'] ?? [])
            ->contains(fn ($block) => ($block['type'] ?? null) === 'dashboard');
        $activePublicDashboard = $publicDashboard ?? null;
        $hasPublicDashboard = is_array($activePublicDashboard) && ! empty($activePublicDashboard['tab']);
        $widthClass = function ($width) {
            return match ($width) {
                'two-thirds' => 'col-12 col-lg-8',
                'half' => 'col-12 col-lg-6',
                'third' => 'col-12 col-md-6 col-lg-4',
                'quarter' => 'col-12 col-md-6 col-lg-3',
                default => 'col-12',
            };
        };
        $dashboardFilterDefinitions = collect($dashboardSnapshot['filterDefinitions'] ?? [])->keyBy('key');
        $currentDashboardUrl = url()->current();
        $managedNavigation = collect($navigationMenu ?? []);
        $useManagedNavigation = $managedNavigation->isNotEmpty();
        $renderCmsHtml = function (?string $html) {
            if (! is_string($html) || trim($html) === '') {
                return new \Illuminate\Support\HtmlString('');
            }

            $html = preg_replace('/\s+sandbox=(["\']).*?\1/i', '', $html) ?? $html;
            $html = preg_replace_callback(
                '/(<iframe\b[^>]*\bsrc=)(["\'])(?:\.\.\/)+(embed\/training-events-calendar[^"\']*)(\2)/i',
                fn (array $matches) => $matches[1].$matches[2].url('/'.ltrim($matches[3], '/')).$matches[4],
                $html
            ) ?? $html;

            return new \Illuminate\Support\HtmlString($html);
        };
        $faviconUrl = null;
        if (!empty($settings?->favicon_url)) {
            $faviconUrl = $resolveMediaUrl($settings->favicon_url);
        }
    @endphp
    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif
    @php
        $dashboardAssetsNeeded = collect($sections ?? [])
            ->flatMap(fn ($section) => $section['blocks'] ?? [])
            ->contains(fn ($block) => ($block['type'] ?? null) === 'dashboard');
    @endphp
    {!! \App\Support\PublicBuildManifest::tags(['resources/css/public-vendor.css', 'resources/js/public-vendor.js']) !!}
    <style>
        :root {
            --header-bg: #ffffff;
            --header-text: #0f172a;
            --header-link: #334155;
            --body-bg: #f8fafc;
            --body-text: #0f172a;
            --body-panel: #ffffff;
            --body-accent: #0f766e;
            --footer-bg: #0f172a;
            --footer-text: #e2e8f0;
            --footer-link: #cbd5e1;
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 18px;
            --radius-xl: 24px;
            --radius-pill: 999px;
            --header-logo-height: 56px;
        }
        body { background: radial-gradient(circle at top, color-mix(in srgb, var(--body-accent) 16%, #ffffff) 0%, var(--body-bg) 45%, #ffffff 100%); color: var(--body-text); }
        .hero { border-radius: var(--radius-xl); background: color-mix(in srgb, var(--body-panel) 88%, #ffffff); backdrop-filter: blur(12px); border: 1px solid rgba(15,23,42,.08); }
        .copy { line-height: 1.8; }
        .page-section { border-radius: var(--radius-xl); padding: 1.5rem; border: 1px solid rgba(15,23,42,.08); box-shadow: 0 24px 40px rgba(15,23,42,.06); }
        .section-default { background: rgba(255,255,255,.82); }
        .section-muted { background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
        .section-accent { background: linear-gradient(135deg, #ecfeff 0%, #dbeafe 100%); }
        .section-dark { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; }
        .section-dark .text-secondary, .section-dark .section-kicker, .section-dark .image-caption { color: rgba(226,232,240,.75) !important; }
        .section-grid { margin-top: 1rem; }
        .page-block-shell { display: flex; }
        .page-block-shell > * { width: 100%; }
        .block-panel { border-radius: var(--radius-xl); background: rgba(255,255,255,.86); border: 1px solid rgba(15,23,42,.08); box-shadow: 0 24px 40px rgba(15,23,42,.06); height: 100%; }
        .section-dark .block-panel, .section-dark .hero { background: rgba(15,23,42,.32); border-color: rgba(226,232,240,.12); color: #f8fafc; }
        .hero-grid-image { min-height: 280px; object-fit: cover; border-radius: var(--radius-lg); width: 100%; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; }
        .stat-card { background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%); border-radius: var(--radius-lg); padding: 1.25rem; border: 1px solid rgba(59,130,246,.12); }
        .section-dark .stat-card { background: rgba(255,255,255,.08); border-color: rgba(226,232,240,.12); }
        .stat-card .value { font-size: 2rem; line-height: 1; font-weight: 700; }
        .quote-block { background: linear-gradient(135deg, var(--body-accent) 0%, color-mix(in srgb, var(--body-accent) 65%, #000000) 100%); color: #ecfeff; }
        .quote-block blockquote { font-size: clamp(1.3rem, 2.4vw, 2rem); line-height: 1.5; margin: 0; }
        .cta-block { background: linear-gradient(135deg, var(--header-text) 0%, color-mix(in srgb, var(--header-text) 75%, #000000) 100%); color: #eff6ff; }
        .image-caption { color: #64748b; }
        .feature-list { display: grid; gap: .85rem; padding-left: 0; list-style: none; }
        .feature-list li { border: 1px solid rgba(15,23,42,.08); border-radius: var(--radius-md); padding: .9rem 1rem; background: #f8fafc; }
        .section-dark .feature-list li { background: rgba(255,255,255,.08); border-color: rgba(226,232,240,.12); }
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .gallery-grid img { width: 100%; height: 220px; object-fit: cover; border-radius: var(--radius-md); }
        .video-frame { width: 100%; aspect-ratio: 16 / 9; border: 0; border-radius: var(--radius-md); background: #0f172a; }
        .block-image { border-radius: var(--radius-lg); }
        .callout-info { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); }
        .callout-success { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); }
        .callout-warning { background: linear-gradient(135deg, #fffbeb 0%, #fde68a 100%); }
        .callout-danger { background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); }
        .section-kicker { font-size: .78rem; letter-spacing: .16em; text-transform: uppercase; color: #64748b; }
        .dashboard-filters { background: rgba(15,23,42,.03); border: 1px solid rgba(15,23,42,.08); border-radius: var(--radius-md); padding: 1rem; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
        .dashboard-card { background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border-radius: var(--radius-lg); border: 1px solid rgba(15,23,42,.08); padding: 1.1rem; }
        .dashboard-card .metric { font-size: 2rem; font-weight: 700; line-height: 1; margin-top: .35rem; }
        .dashboard-chart { position: relative; min-height: 320px; }
        .dashboard-chart canvas { width: 100% !important; height: 100% !important; }
        .dashboard-empty { min-height: 320px; display: flex; align-items: center; justify-content: center; }
        .public-dashboard-shell { display: grid; gap: 1rem; }
        .public-dashboard-grid { --widget-gap: 1rem; display: flex; flex-wrap: wrap; gap: var(--widget-gap); align-items: stretch; }
        .public-dashboard-widget-shell { flex: 0 0 auto; max-width: 100%; min-width: min(100%, 260px); background: var(--widget-bg, #ffffff); color: var(--widget-text, #1f2937); border: 1px solid var(--widget-border, rgba(15, 23, 42, .08)); border-radius: var(--radius-lg); box-shadow: 0 18px 34px rgba(15, 23, 42, .08); padding: 1rem; }
        .public-dashboard-widget-shell .widget-chart-wrap { position: relative; width: 100%; }
        .public-dashboard-widget-shell .widget-table-wrap { overflow: auto; border: 1px solid var(--widget-border, rgba(15, 23, 42, .08)); border-radius: var(--radius-sm); background: rgba(var(--widget-text-rgb, 31, 41, 55), .035); }
        .public-dashboard-widget-shell .metric-card { overflow: hidden; background: rgba(var(--widget-text-rgb, 31, 41, 55), .035); border: 1px solid var(--widget-border, rgba(15, 23, 42, .08)); color: inherit; border-radius: var(--radius-md); }
        .public-dashboard-widget-shell .metric-card .metric-value { font-size: clamp(1.6rem, 2.6vw, 2.4rem); line-height: 1.1; word-break: break-word; }
        .public-dashboard-widget-shell .widget-subtle,
        .public-dashboard-widget-shell .text-secondary { color: rgba(var(--widget-text-rgb, 31, 41, 55), .72) !important; }
        .public-dashboard-widget-shell .table { color: inherit; --bs-table-color: inherit; --bs-table-bg: transparent; --bs-table-striped-color: inherit; --bs-table-striped-bg: rgba(var(--widget-text-rgb, 31, 41, 55), .04); --bs-table-border-color: rgba(var(--widget-text-rgb, 31, 41, 55), .12); }
        .public-dashboard-filters { background: rgba(15,23,42,.03); border: 1px solid rgba(15,23,42,.08); border-radius: var(--radius-lg); padding: 1rem; }
        .dashboard-filters .ts-wrapper.single .ts-control,
        .public-dashboard-filters .ts-wrapper.single .ts-control { min-height: calc(2.25rem + 2px); border-radius: .375rem; }
        .dashboard-filters .ts-dropdown .option,
        .public-dashboard-filters .ts-dropdown .option { white-space: normal; }
        .section-dark .dashboard-filters,
        .section-dark .dashboard-card { background: rgba(255,255,255,.08); border-color: rgba(226,232,240,.12); }
        .section-dark .public-dashboard-filters,
        .section-dark .public-dashboard-widget-shell { background: rgba(255,255,255,.08); border-color: rgba(226,232,240,.12); box-shadow: none; }
        .section-dark .public-dashboard-widget-shell .widget-subtle,
        .section-dark .public-dashboard-widget-shell .text-secondary { color: rgba(226,232,240,.75) !important; }
        .section-dark .dashboard-legend, .section-dark .dashboard-card .text-secondary { color: rgba(226,232,240,.75) !important; }
        .site-header { position: sticky; top: 0; z-index: 1000; backdrop-filter: blur(10px); background: color-mix(in srgb, var(--header-bg) 88%, #ffffff); border-bottom: 1px solid rgba(15, 23, 42, .08); width: 100%; }
        .site-nav { width: 100%; border-top: 0; border-left: 0; border-right: 0; border-bottom: 1px solid rgba(15, 23, 42, .08); background: var(--header-bg); display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap; box-shadow: 0 12px 28px rgba(15, 23, 42, .08); padding: .95rem 1rem; }
        .site-brand { text-decoration: none; color: var(--header-text); display: inline-flex; align-items: center; gap: .7rem; max-width: min(100%, 440px); min-width: 0; }
        .site-brand-copy { display: flex; flex-direction: column; min-width: 0; }
        .site-brand-name { font-weight: 700; font-size: 1.1rem; line-height: 1.25; color: var(--header-text); }
        .site-brand-tagline { margin-top: .12rem; font-size: .82rem; line-height: 1.35; color: color-mix(in srgb, var(--header-text) 58%, #ffffff 42%); font-weight: 500; }
        .site-brand-logo-shell { display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, #ffffff 86%, var(--header-bg)); border: 1px solid rgba(15, 23, 42, .14); border-radius: var(--radius-sm); padding: .25rem .45rem; box-shadow: 0 8px 14px rgba(15, 23, 42, .12); flex: 0 0 auto; max-width: min(42vw, 240px); overflow: hidden; }
        .site-brand-logo { width: auto; height: var(--header-logo-height); max-width: min(38vw, 220px); object-fit: contain; display: block; }
        .site-menu { margin: 0; padding: 0; list-style: none; display: flex; gap: .35rem; align-items: center; flex-wrap: wrap; }
        .site-menu-item { position: relative; }
        .site-menu-item.site-menu-item-utility { margin-left: 1.5rem; padding-left: 1rem; border-left: 1px solid rgba(15, 23, 42, .12); }
        .site-menu-item > a { display: inline-flex; align-items: center; gap: .35rem; }
        .site-menu-item.has-submenu > a::after { content: '▾'; font-size: .72rem; line-height: 1; opacity: .7; }
        .site-menu a { text-decoration: none; color: var(--header-link); padding: .45rem .75rem; border-radius: var(--radius-sm); }
        .site-menu a:hover { background: rgba(15, 23, 42, .08); color: var(--header-text); }
        .site-submenu { margin: 0; padding: .4rem; list-style: none; display: none; position: absolute; left: 0; top: calc(100% + .3rem); min-width: 220px; background: var(--header-bg); border: 1px solid rgba(15, 23, 42, .12); border-radius: var(--radius-md); box-shadow: 0 18px 30px rgba(15, 23, 42, .12); z-index: 1005; }
        .site-submenu a { display: block; width: 100%; white-space: nowrap; }
        .site-menu-item.has-submenu:hover > .site-submenu,
        .site-menu-item.has-submenu:focus-within > .site-submenu { display: block; }
        .site-cta-btn { background: var(--header-link); color: #ffffff; text-decoration: none; padding: .55rem .95rem; border-radius: var(--radius-sm); font-weight: 600; }
        .site-cta-btn:hover { background: color-mix(in srgb, var(--header-link) 78%, #000000); color: #ffffff; }
        .site-footer { background: linear-gradient(135deg, color-mix(in srgb, var(--footer-bg) 92%, #000000) 0%, color-mix(in srgb, var(--body-accent) 44%, #020617) 100%); color: var(--footer-text); margin-top: 3rem; }
        .site-footer a { color: var(--footer-link); text-decoration: none; }
        .site-footer a:hover { color: #ffffff; }
        .site-footer-title { color: var(--footer-text); }
        .site-footer-logo { max-width: 64px; max-height: 64px; border-radius: var(--radius-sm); border: 1px solid rgba(226, 232, 240, .28); object-fit: cover; }
        .site-footer-bottom { border-top: 1px solid rgba(226, 232, 240, .2); }
        @media (max-width: 991.98px) {
            .site-brand { max-width: 100%; }
            .site-brand-logo-shell { max-width: min(46vw, 200px); }
            .site-brand-logo { max-width: min(42vw, 180px); }
            .site-menu { width: 100%; flex-direction: column; align-items: stretch; gap: .2rem; }
            .site-menu-item { width: 100%; }
            .site-menu-item.site-menu-item-utility { margin-left: 0; padding-left: 0; border-left: 0; border-top: 1px solid rgba(15, 23, 42, .08); padding-top: .45rem; margin-top: .25rem; }
            .site-menu-item > a { display: flex; width: 100%; justify-content: space-between; }
            .site-submenu { position: static; display: block; margin-top: .15rem; margin-left: .85rem; border: 0; box-shadow: none; padding: .1rem 0 .2rem; min-width: 0; }
            .site-submenu a { white-space: normal; }
        }
    </style>
</head>
<body>
    <style>
        :root {
            --header-bg: {{ $headerBackgroundColor }};
            --header-text: {{ $headerTextColor }};
            --header-link: {{ $headerLinkColor }};
            --body-bg: {{ $bodyBackgroundColor }};
            --body-text: {{ $bodyTextColor }};
            --body-panel: {{ $bodyPanelColor }};
            --body-accent: {{ $bodyAccentColor }};
            --footer-bg: {{ $footerBackgroundColor }};
            --footer-text: {{ $footerTextColor }};
            --footer-link: {{ $footerLinkColor }};
            --radius-sm: {{ $radiusSm }}px;
            --radius-md: {{ $radiusMd }}px;
            --radius-lg: {{ $radiusLg }}px;
            --radius-xl: {{ $radiusXl }}px;
            --radius-pill: {{ $radiusPill }}px;
            --header-logo-height: {{ $headerLogoHeight }}px;
        }
    </style>
    @if($customCss !== '')
        <style id="website-custom-css">
{!! $safeCustomCss !!}
        </style>
    @endif
    <header class="site-header">
        <nav class="site-nav">
            <a class="site-brand" href="{{ route('home') }}">
                @if($headerLogoUrl)
                    <span class="site-brand-logo-shell">
                        <img src="{{ $headerLogoUrl }}" alt="{{ $siteName }}" class="site-brand-logo">
                    </span>
                @endif
                <span class="site-brand-copy">
                    <span class="site-brand-name">{{ $siteName }}</span>
                    @if($siteTagline)
                        <span class="site-brand-tagline">{{ $siteTagline }}</span>
                    @endif
                </span>
            </a>
            <ul class="site-menu">
                @if($useManagedNavigation)
                    @foreach($managedNavigation as $menuItem)
                        @php
                            $children = $menuItem->children ?? collect();
                        @endphp
                        <li class="site-menu-item {{ $children->isNotEmpty() ? 'has-submenu' : '' }}">
                            <a href="{{ $menuItem->resolvedUrl() }}" target="{{ $menuItem->target }}" @if($menuItem->target === '_blank') rel="noopener noreferrer" @endif>{{ $menuItem->title }}</a>
                            @if($children->isNotEmpty())
                                <ul class="site-submenu">
                                    @foreach($children as $child)
                                        <li>
                                            <a href="{{ $child->resolvedUrl() }}" target="{{ $child->target }}" @if($child->target === '_blank') rel="noopener noreferrer" @endif>{{ $child->title }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                @else
                    @foreach($navigationPages as $navPage)
                        <li class="site-menu-item"><a href="{{ $navPage->is_homepage ? route('home') : route('pages.show', $navPage->slug) }}">{{ $navPage->title }}</a></li>
                    @endforeach
                @endif
                @auth
                    @if($settings->show_admin_link)
                        <li class="site-menu-item site-menu-item-utility"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    @endif
                @else
                    @if($settings->show_login_link)
                        <li class="site-menu-item site-menu-item-utility"><a href="{{ route('login') }}">Login</a></li>
                    @endif
                @endauth
            </ul>
            @if(!empty($settings->header_cta_label) && !empty($settings->header_cta_url))
                <a class="site-cta-btn" href="{{ $settings->header_cta_url }}">{{ $settings->header_cta_label }}</a>
            @endif
        </nav>
    </header>
    <main class="container py-4 py-lg-5">
        @if($page)
            @if(($page->show_page_heading ?? true) && ! $hasHeroBlock)
                <section class="hero p-4 p-lg-5 mb-4">
                    <div class="section-kicker mb-2">Content Management System</div>
                    <h1 class="display-5 fw-bold mb-3">{{ $page->title }}</h1>
                    @if($page->summary)<div class="lead text-secondary mb-0">{{ $renderCmsHtml($page->summary) }}</div>@endif
                </section>
            @endif

            @if($hasSections)
                <div class="d-grid gap-4">
                    @foreach($pageSections as $section)
                        <section id="{{ $section['anchor'] ?: null }}" class="page-section section-{{ $section['style'] ?? 'default' }}">
                            @if(!empty($section['title']) || !empty($section['intro']))
                                <div class="mb-4">
                                    @if(!empty($section['title']))<h2 class="h2 mb-2">{{ $section['title'] }}</h2>@endif
                                    @if(!empty($section['intro']))<div class="copy text-secondary">{{ $renderCmsHtml($section['intro']) }}</div>@endif
                                </div>
                            @endif
                            <div class="row g-4 section-grid">
                                @foreach($section['blocks'] ?? [] as $block)
                                    <div class="{{ $widthClass($block['width'] ?? 'full') }} page-block-shell">
                                        @switch($block['type'] ?? '')
                                            @case('hero')
                                                <section class="hero p-4 p-lg-5">
                                                    <div class="row g-4 align-items-center">
                                                        <div class="{{ !empty($block['image_url']) ? 'col-xl-7' : 'col-12' }}">
                                                            @if(!empty($block['eyebrow']))<div class="section-kicker mb-2">{{ $block['eyebrow'] }}</div>@endif
                                                            <h1 class="display-5 fw-bold mb-3">{{ $block['heading'] ?? $page->title }}</h1>
                                                            @if(!empty($block['content']))<div class="copy text-secondary">{{ $renderCmsHtml($block['content']) }}</div>@endif
                                                            @if(!empty($block['button_label']) && !empty($block['button_url']))<a class="btn btn-dark btn-lg mt-4" href="{{ $block['button_url'] }}">{{ $block['button_label'] }}</a>@endif
                                                        </div>
                                                        @if(!empty($block['image_url']))
                                                            <div class="col-xl-5">
                                                                <img src="{{ $block['image_url'] }}" alt="{{ $block['heading'] ?? $page->title }}" class="hero-grid-image">
                                                            </div>
                                                        @endif
                                                    </div>
                                                </section>
                                                @break

                                            @case('rich_text')
                                                <section class="block-panel p-4 p-lg-5">
                                                    @if(!empty($block['title']))<h2 class="h2 mb-3">{{ $block['title'] }}</h2>@endif
                                                    <div class="copy">{{ $renderCmsHtml($block['content'] ?? '') }}</div>
                                                </section>
                                                @break

                                            @case('image')
                                                <section class="block-panel p-3 p-lg-4">
                                                    @if(!empty($block['image_url']))
                                                        <figure class="mb-0">
                                                            <img src="{{ $block['image_url'] }}" alt="{{ $block['alt_text'] ?? $page->title }}" class="img-fluid block-image w-100">
                                                            @if(!empty($block['caption']))<figcaption class="image-caption mt-3">{{ $renderCmsHtml($block['caption']) }}</figcaption>@endif
                                                        </figure>
                                                    @endif
                                                </section>
                                                @break

                                            @case('stats')
                                                <section class="block-panel p-4 p-lg-5">
                                                    @if(!empty($block['title']))<h2 class="h2 mb-4">{{ $block['title'] }}</h2>@endif
                                                    <div class="stats-grid">
                                                        @foreach($block['items'] ?? [] as $item)
                                                            <div class="stat-card">
                                                                <div class="value">{{ $item['value'] ?? '' }}</div>
                                                                <div class="text-secondary mt-2">{{ $item['label'] ?? '' }}</div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </section>
                                                @break

                                            @case('quote')
                                                <section class="quote-block block-panel p-4 p-lg-5">
                                                    @if(!empty($block['quote']))<blockquote class="mb-4">{{ $renderCmsHtml($block['quote']) }}</blockquote>@endif
                                                    @if(!empty($block['author']) || !empty($block['role']))
                                                        <div class="small text-white-50">{{ $block['author'] ?? '' }}@if(!empty($block['author']) && !empty($block['role'])) | @endif{{ $block['role'] ?? '' }}</div>
                                                    @endif
                                                </section>
                                                @break

                                            @case('cta')
                                                <section class="cta-block block-panel p-4 p-lg-5">
                                                    @if(!empty($block['heading']))<h2 class="h2 mb-3">{{ $block['heading'] }}</h2>@endif
                                                    @if(!empty($block['content']))<div class="copy text-white-50 mb-4">{{ $renderCmsHtml($block['content']) }}</div>@endif
                                                    @if(!empty($block['button_label']) && !empty($block['button_url']))<a class="btn btn-light btn-lg" href="{{ $block['button_url'] }}">{{ $block['button_label'] }}</a>@endif
                                                </section>
                                                @break

                                            @case('feature_list')
                                                <section class="block-panel p-4 p-lg-5">
                                                    @if(!empty($block['title']))<h2 class="h2 mb-3">{{ $block['title'] }}</h2>@endif
                                                    @if(!empty($block['intro']))<div class="copy text-secondary mb-4">{{ $renderCmsHtml($block['intro']) }}</div>@endif
                                                    <ul class="feature-list mb-0">
                                                        @foreach($block['items'] ?? [] as $item)
                                                            <li>{{ $item }}</li>
                                                        @endforeach
                                                    </ul>
                                                </section>
                                                @break

                                            @case('gallery')
                                                <section class="block-panel p-4 p-lg-5">
                                                    @if(!empty($block['title']))<h2 class="h2 mb-4">{{ $block['title'] }}</h2>@endif
                                                    <div class="gallery-grid">
                                                        @foreach($block['items'] ?? [] as $item)
                                                            <figure class="mb-0">
                                                                <img src="{{ $item['url'] ?? '' }}" alt="{{ $item['caption'] ?? $page->title }}">
                                                                @if(!empty($item['caption']))<figcaption class="image-caption mt-2">{{ $item['caption'] }}</figcaption>@endif
                                                            </figure>
                                                        @endforeach
                                                    </div>
                                                </section>
                                                @break

                                            @case('video_embed')
                                                <section class="block-panel p-4 p-lg-5">
                                                    @if(!empty($block['title']))<h2 class="h2 mb-3">{{ $block['title'] }}</h2>@endif
                                                    @if(!empty($block['embed_url']))<iframe class="video-frame" src="{{ $block['embed_url'] }}" allowfullscreen loading="lazy"></iframe>@endif
                                                    @if(!empty($block['caption']))<div class="image-caption mt-3">{{ $renderCmsHtml($block['caption']) }}</div>@endif
                                                </section>
                                                @break

                                            @case('dashboard')
                                                <section class="block-panel p-4 p-lg-5">
                                                    @if(!empty($block['title']))<h2 class="h2 mb-3">{{ $block['title'] }}</h2>@endif
                                                    @if(!empty($block['intro']))<div class="copy text-secondary mb-4">{{ $renderCmsHtml($block['intro']) }}</div>@endif
                                                    @php
                                                        $dashboardBlockId = 'dashboard-'.$loop->parent->index.'-'.$loop->index;
                                                    @endphp

                                                    @if($hasPublicDashboard)
                                                        @include('website.partials.public-dashboard-widgets', [
                                                            'block' => $block,
                                                            'publicDashboard' => $activePublicDashboard,
                                                            'currentDashboardUrl' => $currentDashboardUrl,
                                                            'dashboardBlockId' => $dashboardBlockId,
                                                        ])
                                                    @elseif(! $dashboardSnapshot)
                                                        <div class="text-secondary">Dashboard metrics are not available yet.</div>
                                                    @else
                                                        @php
                                                            $visibleDashboardFilters = $dashboardFilterDefinitions->values();
                                                            $organizerRows = collect($dashboardSnapshot['resultsByOrganizer'] ?? []);
                                                            $regionRows = collect($dashboardSnapshot['resultsByRegion'] ?? []);
                                                            $showBreakdowns = ($block['show_breakdowns'] ?? 'yes') === 'yes';
                                                            $dashboardCharts = [
                                                                'summary' => [
                                                                    'id' => $dashboardBlockId.'-summary-chart',
                                                                    'type' => 'bar',
                                                                    'showLegend' => false,
                                                                    'data' => [
                                                                        'labels' => ['Pre-test', 'Post-test'],
                                                                        'datasets' => [[
                                                                            'label' => 'Average Score',
                                                                            'data' => [
                                                                                round((float) $dashboardSnapshot['avgPreScore'], 1),
                                                                                round((float) $dashboardSnapshot['avgPostScore'], 1),
                                                                            ],
                                                                            'backgroundColor' => ['rgba(15, 118, 110, 0.72)', 'rgba(245, 158, 11, 0.72)'],
                                                                            'borderColor' => ['rgba(15, 118, 110, 1)', 'rgba(245, 158, 11, 1)'],
                                                                            'borderWidth' => 1,
                                                                        ]],
                                                                    ],
                                                                ],
                                                                'organizer' => null,
                                                                'region' => null,
                                                            ];

                                                            if ($showBreakdowns && $organizerRows->isNotEmpty()) {
                                                                $dashboardCharts['organizer'] = [
                                                                    'id' => $dashboardBlockId.'-organizer-chart',
                                                                    'type' => 'bar',
                                                                    'indexAxis' => 'y',
                                                                    'data' => [
                                                                        'labels' => $organizerRows->pluck('label')->values()->all(),
                                                                        'datasets' => [
                                                                            [
                                                                                'label' => 'Pre-test',
                                                                                'data' => $organizerRows->pluck('avg_pre')->map(fn ($value) => round((float) $value, 1))->values()->all(),
                                                                                'backgroundColor' => 'rgba(15, 118, 110, 0.72)',
                                                                                'borderColor' => 'rgba(15, 118, 110, 1)',
                                                                                'borderWidth' => 1,
                                                                            ],
                                                                            [
                                                                                'label' => 'Post-test',
                                                                                'data' => $organizerRows->pluck('avg_post')->map(fn ($value) => round((float) $value, 1))->values()->all(),
                                                                                'backgroundColor' => 'rgba(245, 158, 11, 0.72)',
                                                                                'borderColor' => 'rgba(245, 158, 11, 1)',
                                                                                'borderWidth' => 1,
                                                                            ],
                                                                        ],
                                                                    ],
                                                                ];
                                                            }

                                                            if ($showBreakdowns && $regionRows->isNotEmpty()) {
                                                                $dashboardCharts['region'] = [
                                                                    'id' => $dashboardBlockId.'-region-chart',
                                                                    'type' => 'bar',
                                                                    'indexAxis' => 'y',
                                                                    'data' => [
                                                                        'labels' => $regionRows->pluck('label')->values()->all(),
                                                                        'datasets' => [
                                                                            [
                                                                                'label' => 'Pre-test',
                                                                                'data' => $regionRows->pluck('avg_pre')->map(fn ($value) => round((float) $value, 1))->values()->all(),
                                                                                'backgroundColor' => 'rgba(15, 118, 110, 0.72)',
                                                                                'borderColor' => 'rgba(15, 118, 110, 1)',
                                                                                'borderWidth' => 1,
                                                                            ],
                                                                            [
                                                                                'label' => 'Post-test',
                                                                                'data' => $regionRows->pluck('avg_post')->map(fn ($value) => round((float) $value, 1))->values()->all(),
                                                                                'backgroundColor' => 'rgba(245, 158, 11, 0.72)',
                                                                                'borderColor' => 'rgba(245, 158, 11, 1)',
                                                                                'borderWidth' => 1,
                                                                            ],
                                                                        ],
                                                                    ],
                                                                ];
                                                            }
                                                        @endphp

                                                        @if($visibleDashboardFilters->isNotEmpty())
                                                            <form method="GET" action="{{ $currentDashboardUrl }}" class="dashboard-filters mb-4">
                                                                <div class="row g-3 align-items-end">
                                                                    @foreach($visibleDashboardFilters as $filterDefinition)
                                                                        <div class="col-md-6 col-xl-3">
                                                                            <label class="form-label">{{ $filterDefinition['label'] }}</label>
                                                                            @if(!empty($filterDefinition['async']) && ($filterDefinition['key'] ?? '') === 'organization_id')
                                                                                <select
                                                                                    name="{{ $filterDefinition['key'] }}"
                                                                                    class="form-select js-public-dashboard-organization-filter"
                                                                                    data-remote-url="{{ route('dashboard.organization-options') }}"
                                                                                >
                                                                                    <option value="">{{ $filterDefinition['all_label'] }}</option>
                                                                                    @if(($dashboardSnapshot['filters'][$filterDefinition['key']] ?? '') !== '' && !empty($selectedDashboardOrganizationFilter))
                                                                                        <option value="{{ $selectedDashboardOrganizationFilter['value'] }}" selected>{{ $selectedDashboardOrganizationFilter['label'] }}</option>
                                                                                    @endif
                                                                                </select>
                                                                            @else
                                                                                <select name="{{ $filterDefinition['key'] }}" class="form-select">
                                                                                    <option value="">{{ $filterDefinition['all_label'] }}</option>
                                                                                    @foreach($filterDefinition['options'] as $option)
                                                                                        <option value="{{ $option['value'] }}" @selected(($dashboardSnapshot['filters'][$filterDefinition['key']] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                                                                    @endforeach
                                                                                </select>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                    <div class="col-md-6 col-xl-3 d-grid gap-2">
                                                                        <button class="btn btn-dark" type="submit">Apply Filters</button>
                                                                        <a class="btn btn-outline-secondary" href="{{ $currentDashboardUrl }}">Reset</a>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                        @endif

                                                        <div class="dashboard-grid mb-4">
                                                            <div class="dashboard-card">
                                                                <div class="section-kicker">Participants</div>
                                                                <div class="metric">{{ number_format($dashboardSnapshot['totalParticipants']) }}</div>
                                                                <div class="text-secondary mt-2">Total participants</div>
                                                            </div>
                                                            <div class="dashboard-card">
                                                                <div class="section-kicker">Projects</div>
                                                                <div class="metric">{{ number_format($dashboardSnapshot['totalProjects']) }}</div>
                                                                <div class="text-secondary mt-2">Total projects</div>
                                                            </div>
                                                            <div class="dashboard-card">
                                                                <div class="section-kicker">Average Pre-test</div>
                                                                <div class="metric">{{ $dashboardSnapshot['avgPreScore'] }}</div>
                                                                <div class="text-secondary mt-2">Average pre-result score</div>
                                                            </div>
                                                            <div class="dashboard-card">
                                                                <div class="section-kicker">Average Post-test</div>
                                                                <div class="metric">{{ $dashboardSnapshot['avgPostScore'] }}</div>
                                                                <div class="text-secondary mt-2">Average post-result score</div>
                                                            </div>
                                                        </div>

                                                        <div class="dashboard-card mb-4">
                                                            <div class="section-kicker mb-2">Overall Results</div>
                                                            <div class="fw-semibold mb-3">Pre vs Post</div>
                                                            <div class="dashboard-chart">
                                                                <canvas id="{{ $dashboardBlockId }}-summary-chart"></canvas>
                                                            </div>
                                                        </div>

                                                        @if($showBreakdowns)
                                                            <div class="row g-4">
                                                                <div class="col-xl-6">
                                                                    <div class="dashboard-card h-100">
                                                                        <div class="section-kicker mb-2">Project Comparison</div>
                                                                        @if($organizerRows->isNotEmpty())
                                                                            <div class="dashboard-chart">
                                                                                <canvas id="{{ $dashboardBlockId }}-organizer-chart"></canvas>
                                                                            </div>
                                                                        @else
                                                                            <div class="dashboard-empty text-secondary">No organizer comparison data available.</div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <div class="dashboard-card h-100">
                                                                        <div class="section-kicker mb-2">Regional Comparison</div>
                                                                        @if($regionRows->isNotEmpty())
                                                                            <div class="dashboard-chart">
                                                                                <canvas id="{{ $dashboardBlockId }}-region-chart"></canvas>
                                                                            </div>
                                                                        @else
                                                                            <div class="dashboard-empty text-secondary">No regional comparison data available.</div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <script type="application/json" class="dashboard-chart-config">@json($dashboardCharts)</script>
                                                    @endif
                                                </section>
                                                @break

                                            @case('callout')
                                                <section class="block-panel p-4 p-lg-5 callout-{{ $block['tone'] ?? 'info' }}">
                                                    @if(!empty($block['title']))<h2 class="h2 mb-3">{{ $block['title'] }}</h2>@endif
                                                    @if(!empty($block['content']))<div class="copy">{{ $renderCmsHtml($block['content']) }}</div>@endif
                                                </section>
                                                @break
                                        @endswitch
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @elseif($page->body)
                <section class="hero p-4 p-lg-5">
                    <div class="copy">{{ $renderCmsHtml($page->body) }}</div>
                </section>
            @endif
        @else
            <section class="hero p-4 p-lg-5">
                <div class="section-kicker mb-2">Content Management System</div>
                <h1 class="display-5 fw-bold mb-3">No published content yet</h1>
                <p class="lead text-secondary mb-0">Create and publish a CMS page from the admin console to populate the website.</p>
            </section>
        @endif
    </main>

    <footer class="site-footer py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    @if($footerLogoUrl)
                        <img src="{{ $footerLogoUrl }}" alt="{{ $siteName }}" class="site-footer-logo mb-3">
                    @endif
                    <h2 class="h4 site-footer-title mb-3">{{ $settings->footer_title ?: $siteName }}</h2>
                    @if(!empty($settings->footer_about))
                        <div class="small text-white-50">{{ $renderCmsHtml($settings->footer_about) }}</div>
                    @endif
                </div>
                <div class="col-lg-3">
                    <h3 class="h6 text-uppercase text-white-50 mb-3">Quick Links</h3>
                    <ul class="list-unstyled small mb-0 d-grid gap-2">
                        <li><a href="{{ route('home') }}">Home</a></li>
                        @foreach($navigationPages as $navPage)
                            @continue($navPage->is_homepage)
                            <li><a href="{{ route('pages.show', $navPage->slug) }}">{{ $navPage->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h3 class="h6 text-uppercase text-white-50 mb-3">Contact</h3>
                    <div class="small d-grid gap-2">
                        @if(!empty($settings->footer_address))<div>{{ $settings->footer_address }}</div>@endif
                        @if(!empty($settings->footer_email))<div><a href="mailto:{{ $settings->footer_email }}">{{ $settings->footer_email }}</a></div>@endif
                        @if(!empty($settings->footer_phone))<div><a href="tel:{{ preg_replace('/\\s+/', '', $settings->footer_phone) }}">{{ $settings->footer_phone }}</a></div>@endif
                    </div>
                </div>
            </div>
            <div class="site-footer-bottom mt-4 pt-3 d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-start">
                <div class="small text-white-50">&copy; {{ now()->year }} {{ $siteName }}. {{ $settings->footer_copyright ?: 'All rights reserved.' }}</div>
                @if(!empty($settings->footer_note))
                    <div class="small text-white-50">{{ $renderCmsHtml($settings->footer_note) }}</div>
                @endif
            </div>
        </div>
    </footer>
    @if($hasDashboardBlock)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.TomSelect === 'undefined') {
                return;
            }

            document.querySelectorAll('.js-public-dashboard-organization-filter').forEach((select) => {
                if (!(select instanceof HTMLSelectElement) || select.tomselect) {
                    return;
                }

                const form = select.form;
                const regionSelect = form?.querySelector('select[name="region_id"]');
                const buildUrl = (query = '') => {
                    const url = new URL(select.dataset.remoteUrl, window.location.origin);

                    if (query) {
                        url.searchParams.set('q', query);
                    }

                    if (select.value) {
                        url.searchParams.set('selected_id', select.value);
                    }

                    if (regionSelect?.value) {
                        url.searchParams.set('region_id', regionSelect.value);
                    }

                    return url.toString();
                };

                const instance = new TomSelect(select, {
                    create: false,
                    allowEmptyOption: false,
                    maxOptions: 50,
                    hidePlaceholder: true,
                    placeholder: select.options[0]?.textContent?.trim() || 'Search organizations',
                    valueField: 'value',
                    labelField: 'label',
                    searchField: ['label'],
                    options: Array.from(select.options)
                        .filter((option) => option.value !== '')
                        .map((option) => ({
                            value: option.value,
                            label: option.textContent,
                        })),
                    items: select.value ? [select.value] : [],
                    loadThrottle: 250,
                    shouldLoad(query) {
                        return query.length >= 2 || Boolean(this.getValue()) || Boolean(regionSelect?.value);
                    },
                    load(query, callback) {
                        fetch(buildUrl(query), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        })
                            .then((response) => response.ok ? response.json() : Promise.reject(new Error('Failed to load organizations')))
                            .then((payload) => callback(Array.isArray(payload.options) ? payload.options : []))
                            .catch(() => callback());
                    },
                    onDropdownOpen() {
                        if ((regionSelect?.value || this.getValue()) && Object.keys(this.options).length <= 1) {
                            this.load('');
                        }
                    },
                });

                instance.removeOption('');

                if (regionSelect && !regionSelect.dataset.publicDashboardOrganizationBound) {
                    regionSelect.dataset.publicDashboardOrganizationBound = '1';
                    regionSelect.addEventListener('change', () => {
                        const relatedForm = regionSelect.form;
                        relatedForm?.querySelectorAll('.js-public-dashboard-organization-filter').forEach((organizationSelect) => {
                            if (organizationSelect instanceof HTMLSelectElement && organizationSelect.tomselect) {
                                organizationSelect.tomselect.clear(true);
                                organizationSelect.tomselect.clearOptions();
                            }
                        });
                    });
                }
            });
        });
    </script>
    @endif
    @if($hasDashboardBlock)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.Chart) {
                return;
            }

            const buildOptions = (chartConfig, isDarkSection) => {
                const axisColor = isDarkSection ? 'rgba(226, 232, 240, 0.88)' : '#475569';
                const gridColor = isDarkSection ? 'rgba(226, 232, 240, 0.14)' : 'rgba(15, 23, 42, 0.08)';
                const indexAxis = chartConfig.indexAxis || 'x';
                const valueAxis = indexAxis === 'y' ? 'x' : 'y';
                const categoryAxis = indexAxis === 'y' ? 'y' : 'x';

                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis,
                    plugins: {
                        legend: {
                            display: chartConfig.showLegend !== false,
                            position: 'bottom',
                            labels: { color: axisColor },
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        },
                    },
                    scales: {
                        [valueAxis]: {
                            beginAtZero: true,
                            suggestedMax: 100,
                            ticks: { color: axisColor },
                            grid: { color: gridColor },
                        },
                        [categoryAxis]: {
                            ticks: { color: axisColor },
                            grid: {
                                color: gridColor,
                                display: indexAxis !== 'y',
                            },
                        },
                    },
                };
            };

            document.querySelectorAll('.dashboard-chart-config').forEach((configNode) => {
                let charts;

                try {
                    charts = JSON.parse(configNode.textContent);
                } catch (error) {
                    return;
                }

                Object.values(charts || {}).forEach((chartConfig) => {
                    if (!chartConfig || !chartConfig.id) {
                        return;
                    }

                    const canvas = document.getElementById(chartConfig.id);

                    if (!canvas) {
                        return;
                    }

                    const isDarkSection = Boolean(canvas.closest('.section-dark'));

                    new Chart(canvas, {
                        type: chartConfig.type || 'bar',
                        data: chartConfig.data,
                        options: buildOptions(chartConfig, isDarkSection),
                    });
                });
            });
        });
    </script>
    @if($hasPublicDashboard)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.Chart) {
                return;
            }

            const chartPalette = {
                teal_amber: ['#0f766e', '#f59e0b', '#2563eb', '#16a34a', '#dc2626', '#7c3aed'],
                blue_pink: ['#2563eb', '#ec4899', '#0ea5e9', '#f97316', '#8b5cf6', '#14b8a6'],
                emerald_slate: ['#059669', '#334155', '#10b981', '#64748b', '#0f172a', '#22c55e'],
                sunset: ['#f97316', '#ef4444', '#f59e0b', '#fb7185', '#eab308', '#f43f5e'],
                ocean_mint: ['#0ea5e9', '#10b981', '#06b6d4', '#22c55e', '#0284c7', '#14b8a6'],
                royal_coral: ['#4f46e5', '#fb7185', '#6366f1', '#f43f5e', '#7c3aed', '#ef4444'],
                forest_gold: ['#166534', '#ca8a04', '#15803d', '#eab308', '#14532d', '#f59e0b'],
                mono_gray: ['#111827', '#374151', '#6b7280', '#9ca3af', '#4b5563', '#1f2937'],
                berry_lime: ['#be185d', '#84cc16', '#db2777', '#65a30d', '#9d174d', '#4d7c0f'],
                earth_clay: ['#92400e', '#a16207', '#78350f', '#b45309', '#854d0e', '#b91c1c'],
            };

            const hexToRgba = (hex, alpha) => {
                const value = String(hex || '').trim();
                const match = value.match(/^#([0-9a-fA-F]{6})$/);

                if (!match) {
                    return `rgba(31, 41, 55, ${alpha})`;
                }

                const rgb = [
                    parseInt(match[1].slice(0, 2), 16),
                    parseInt(match[1].slice(2, 4), 16),
                    parseInt(match[1].slice(4, 6), 16),
                ];

                return `rgba(${rgb.join(', ')}, ${alpha})`;
            };

            const buildChartDatasets = (widgetType, datasets, scheme) => {
                const palette = chartPalette[scheme] || chartPalette.teal_amber;

                if (widgetType === 'pie' || widgetType === 'doughnut') {
                    const base = (datasets[0] || { label: 'Value', data: [] });

                    return [{
                        label: base.label || 'Value',
                        data: base.data || [],
                        backgroundColor: (base.data || []).map((_, idx) => `${palette[idx % palette.length]}CC`),
                        borderColor: (base.data || []).map((_, idx) => palette[idx % palette.length]),
                        borderWidth: 1,
                    }];
                }

                return (datasets || []).map((dataset, index) => {
                    const color = palette[index % palette.length];
                    const common = {
                        label: dataset.label || `Series ${index + 1}`,
                        data: dataset.data || [],
                    };

                    if (widgetType === 'line' || widgetType === 'radar') {
                        return {
                            ...common,
                            borderColor: color,
                            backgroundColor: `${color}33`,
                            pointBackgroundColor: color,
                            tension: 0.25,
                            fill: widgetType === 'radar',
                        };
                    }

                    return {
                        ...common,
                        backgroundColor: `${color}CC`,
                        borderColor: color,
                        borderWidth: 1,
                    };
                });
            };

            document.querySelectorAll('.public-dashboard-chart-config').forEach((configNode) => {
                let chartConfig;

                try {
                    chartConfig = JSON.parse(configNode.textContent);
                } catch (error) {
                    return;
                }

                const payloads = chartConfig?.payloads || {};
                const blockId = chartConfig?.block_id || 'dashboard';

                Object.entries(payloads).forEach(([widgetId, payload]) => {
                    if (!payload || payload.type !== 'chart') {
                        return;
                    }

                    const canvas = document.getElementById(`${blockId}-widget-canvas-${widgetId}`);
                    const widgetElement = canvas?.closest('.public-dashboard-widget-shell');

                    if (!canvas || !widgetElement) {
                        return;
                    }

                    const chartType = widgetElement.dataset.widgetChartType || 'bar';
                    const scheme = widgetElement.dataset.widgetColor || 'teal_amber';
                    const textColor = widgetElement.dataset.widgetTextColor || '#1f2937';

                    new Chart(canvas, {
                        type: chartType,
                        data: {
                            labels: payload.labels || [],
                            datasets: buildChartDatasets(chartType, payload.datasets || [], scheme),
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: textColor,
                                    },
                                },
                            },
                            scales: (chartType === 'bar' || chartType === 'line' || chartType === 'radar')
                                ? {
                                    x: {
                                        ticks: { color: textColor },
                                        grid: { color: hexToRgba(textColor, 0.08) },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: { color: textColor },
                                        grid: { color: hexToRgba(textColor, 0.12) },
                                    },
                                }
                                : {},
                        },
                    });
                });
            });
        });
    </script>
    @endif
    @endif
    @if($customJs !== '')
        <script id="website-custom-js">
{!! $safeCustomJs !!}
        </script>
    @endif
</body>
</html>














