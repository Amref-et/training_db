<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participant Registration | {{ $websiteSettings->site_name ?? config('app.name', 'HIL Website') }}</title>
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
        $faviconUrl = $resolveMediaUrl($settings->favicon_url);
        $headerLogoUrl = $resolveMediaUrl($settings->header_logo_url);
        $footerLogoUrl = $resolveMediaUrl($settings->footer_logo_url);
        $headerLogoHeight = max(24, (int) ($settings->header_logo_height ?? 56));
        $managedNavigation = collect($navigationMenu ?? []);
        $useManagedNavigation = $managedNavigation->isNotEmpty();
        $renderCmsHtml = function (?string $html) {
            if (! is_string($html) || trim($html) === '') {
                return new \Illuminate\Support\HtmlString('');
            }

            return new \Illuminate\Support\HtmlString($html);
        };
        $selectedRegistration = session('participant_registration');
    @endphp
    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @endif
    {!! \App\Support\PublicBuildManifest::tags(['resources/css/public-vendor.css', 'resources/js/public-vendor.js']) !!}
    <style>
        :root {
            --header-bg: {{ $settings->header_background_color ?: '#ffffff' }};
            --header-text: {{ $settings->header_text_color ?: '#0f172a' }};
            --header-link: {{ $settings->header_link_color ?: '#334155' }};
            --body-bg: {{ $settings->body_background_color ?: '#f8fafc' }};
            --body-text: {{ $settings->body_text_color ?: '#0f172a' }};
            --body-panel: {{ $settings->body_panel_color ?: '#ffffff' }};
            --body-accent: {{ $settings->body_accent_color ?: ($settings->primary_color ?: '#0f766e') }};
            --footer-bg: {{ $settings->footer_background_color ?: '#0f172a' }};
            --footer-text: {{ $settings->footer_text_color ?: '#e2e8f0' }};
            --footer-link: {{ $settings->footer_link_color ?: '#cbd5e1' }};
            --radius-sm: {{ max(0, (int) ($settings->radius_sm ?? 10)) }}px;
            --radius-md: {{ max(0, (int) ($settings->radius_md ?? 14)) }}px;
            --radius-lg: {{ max(0, (int) ($settings->radius_lg ?? 18)) }}px;
            --radius-xl: {{ max(0, (int) ($settings->radius_xl ?? 24)) }}px;
            --header-logo-height: {{ $headerLogoHeight }}px;
        }

        body {
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--body-accent) 18%, #ffffff) 0%, transparent 36%),
                linear-gradient(180deg, var(--body-bg) 0%, #ffffff 100%);
            color: var(--body-text);
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: color-mix(in srgb, var(--header-bg) 88%, #ffffff);
            border-bottom: 1px solid rgba(15, 23, 42, .08);
        }

        .site-nav {
            width: 100%;
            background: var(--header-bg);
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
            padding: .95rem 1rem;
        }

        .site-brand {
            text-decoration: none;
            color: var(--header-text);
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            max-width: min(100%, 440px);
            min-width: 0;
        }

        .site-brand-copy {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .site-brand-name {
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.25;
            color: var(--header-text);
        }

        .site-brand-tagline {
            margin-top: .12rem;
            font-size: .82rem;
            line-height: 1.35;
            color: color-mix(in srgb, var(--header-text) 58%, #ffffff 42%);
            font-weight: 500;
        }

        .site-brand-logo-shell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, #ffffff 86%, var(--header-bg));
            border: 1px solid rgba(15, 23, 42, .14);
            border-radius: var(--radius-sm);
            padding: .25rem .45rem;
            box-shadow: 0 8px 14px rgba(15, 23, 42, .12);
            flex: 0 0 auto;
            max-width: min(42vw, 240px);
            overflow: hidden;
        }

        .site-brand-logo {
            width: auto;
            height: var(--header-logo-height);
            max-width: min(38vw, 220px);
            object-fit: contain;
            display: block;
        }

        .site-nav-panel {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .75rem;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .site-menu-toggle {
            display: none;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(15, 23, 42, .14);
            border-radius: var(--radius-sm);
            background: color-mix(in srgb, #ffffff 88%, var(--header-bg));
            color: var(--header-text);
            font: inherit;
            font-weight: 600;
            line-height: 1;
            padding: .55rem .75rem;
            box-shadow: 0 8px 14px rgba(15, 23, 42, .08);
        }

        .site-menu-toggle:hover,
        .site-menu-toggle:focus-visible {
            background: rgba(15, 23, 42, .08);
            color: var(--header-text);
        }

        .site-menu-toggle:focus-visible {
            outline: 3px solid color-mix(in srgb, var(--header-link) 34%, transparent);
            outline-offset: 2px;
        }

        .site-menu-toggle-bars,
        .site-menu-toggle-bars::before,
        .site-menu-toggle-bars::after {
            display: block;
            width: 1rem;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
            transition: transform .18s ease, opacity .18s ease, background-color .18s ease;
        }

        .site-menu-toggle-bars {
            position: relative;
        }

        .site-menu-toggle-bars::before,
        .site-menu-toggle-bars::after {
            content: '';
            position: absolute;
            left: 0;
        }

        .site-menu-toggle-bars::before {
            top: -5px;
        }

        .site-menu-toggle-bars::after {
            top: 5px;
        }

        .site-nav.is-menu-open .site-menu-toggle-bars {
            background: transparent;
        }

        .site-nav.is-menu-open .site-menu-toggle-bars::before {
            transform: translateY(5px) rotate(45deg);
        }

        .site-nav.is-menu-open .site-menu-toggle-bars::after {
            transform: translateY(-5px) rotate(-45deg);
        }

        .site-menu {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            gap: .35rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .site-menu-item {
            position: relative;
        }

        .site-menu-item.site-menu-item-utility {
            margin-left: 1.5rem;
            padding-left: 1rem;
            border-left: 1px solid rgba(15, 23, 42, .12);
        }

        .site-menu a {
            text-decoration: none;
            color: var(--header-link);
            padding: .45rem .75rem;
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .site-menu a:hover {
            background: rgba(15, 23, 42, .08);
            color: var(--header-text);
        }

        .site-menu-item.has-submenu > a::after {
            content: '▾';
            font-size: .72rem;
            line-height: 1;
            opacity: .7;
        }

        .site-submenu {
            margin: 0;
            padding: .4rem;
            list-style: none;
            display: none;
            position: absolute;
            left: 0;
            top: calc(100% + .3rem);
            min-width: 220px;
            background: var(--header-bg);
            border: 1px solid rgba(15, 23, 42, .12);
            border-radius: var(--radius-md);
            box-shadow: 0 18px 30px rgba(15, 23, 42, .12);
            z-index: 1005;
        }

        .site-submenu a {
            display: block;
            width: 100%;
            white-space: nowrap;
        }

        .site-menu-item.has-submenu:hover > .site-submenu,
        .site-menu-item.has-submenu:focus-within > .site-submenu {
            display: block;
        }

        .site-cta-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.4rem;
            background: color-mix(in srgb, var(--body-accent) 10%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--body-accent) 38%, rgba(15, 23, 42, .18));
            color: color-mix(in srgb, var(--body-accent) 78%, #0f172a);
            text-decoration: none;
            padding: .55rem .95rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            transition: background .18s ease, border-color .18s ease, color .18s ease, box-shadow .18s ease;
        }

        .site-cta-btn:hover {
            background: var(--body-accent);
            border-color: var(--body-accent);
            color: #ffffff;
            box-shadow: 0 8px 18px color-mix(in srgb, var(--body-accent) 20%, transparent);
        }

        .site-cta-btn:focus-visible {
            outline: 3px solid color-mix(in srgb, var(--body-accent) 28%, transparent);
            outline-offset: 2px;
        }

        .site-cta-btn:active {
            box-shadow: none;
        }

        .registration-hero,
        .registration-form-card,
        .registration-side-card {
            border-radius: var(--radius-xl);
            background: color-mix(in srgb, var(--body-panel) 92%, #ffffff);
            border: 1px solid rgba(15, 23, 42, .08);
            box-shadow: 0 24px 50px rgba(15, 23, 42, .08);
        }

        .registration-hero {
            overflow: hidden;
            position: relative;
        }

        .registration-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--body-accent) 16%, #ffffff) 0%, transparent 54%),
                radial-gradient(circle at 80% 20%, rgba(245, 158, 11, .16), transparent 28%);
            pointer-events: none;
        }

        .registration-hero > * {
            position: relative;
            z-index: 1;
        }

        .registration-kicker {
            letter-spacing: .16em;
            text-transform: uppercase;
            font-size: .78rem;
            color: #64748b;
        }

        .registration-id-preview {
            background: linear-gradient(135deg, #0f172a 0%, color-mix(in srgb, var(--body-accent) 55%, #020617) 100%);
            color: #eff6ff;
            border-radius: var(--radius-lg);
            padding: 1.1rem 1.2rem;
        }

        .registration-id-preview-sticky {
            position: sticky;
            top: calc(var(--header-logo-height) + 2.35rem);
            z-index: 990;
            margin-bottom: 1rem;
        }

        .registration-id-preview-sticky .registration-id-preview {
            box-shadow: 0 18px 34px rgba(15, 23, 42, .18);
        }

        .registration-side-card {
            padding: 1.4rem;
        }

        .registration-side-list {
            padding-left: 1.1rem;
            margin-bottom: 0;
            display: grid;
            gap: .85rem;
        }

        .registration-field-note {
            font-size: .84rem;
            color: #64748b;
        }

        .required-mark {
            color: #dc3545;
            font-weight: 700;
            margin-left: .2rem;
        }

        .registration-form-card .form-control,
        .registration-form-card .form-select,
        .registration-form-card .ts-control {
            border-radius: var(--radius-sm);
            min-height: calc(2.75rem + 2px);
        }

        .registration-form-card .form-control:focus,
        .registration-form-card .form-select:focus,
        .registration-form-card .ts-control.focus {
            border-color: color-mix(in srgb, var(--body-accent) 46%, #94a3b8);
            box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--body-accent) 14%, transparent);
        }

        .registration-submit {
            background: linear-gradient(135deg, var(--body-accent) 0%, color-mix(in srgb, var(--body-accent) 65%, #0f172a) 100%);
            color: #fff;
            border: 0;
            border-radius: var(--radius-sm);
            padding: .9rem 1.2rem;
            font-weight: 600;
        }

        .registration-submit:hover {
            color: #fff;
            opacity: .96;
        }

        .site-footer {
            background: linear-gradient(135deg, color-mix(in srgb, var(--footer-bg) 92%, #000000) 0%, color-mix(in srgb, var(--body-accent) 44%, #020617) 100%);
            color: var(--footer-text);
            margin-top: 3rem;
        }

        .site-footer a {
            color: var(--footer-link);
            text-decoration: none;
        }

        .site-footer a:hover {
            color: #ffffff;
        }

        .site-footer-title {
            color: var(--footer-text);
        }

        .site-footer-logo {
            max-width: 64px;
            max-height: 64px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(226, 232, 240, .28);
            object-fit: cover;
        }

        .site-footer-bottom {
            border-top: 1px solid rgba(226, 232, 240, .2);
        }

        @media (max-width: 991.98px) {
            .site-brand {
                max-width: calc(100% - 5.25rem);
            }

            .site-brand-logo-shell {
                max-width: min(46vw, 200px);
            }

            .site-brand-logo {
                max-width: min(42vw, 180px);
            }

            .site-menu-toggle {
                display: inline-flex;
            }

            .site-nav-panel {
                display: none;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: .75rem;
                margin-left: 0;
                padding-top: .75rem;
                border-top: 1px solid rgba(15, 23, 42, .08);
            }

            .site-nav.is-menu-open .site-nav-panel {
                display: flex;
            }

            .site-menu {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: .2rem;
            }

            .site-menu-item {
                width: 100%;
            }

            .site-menu-item.site-menu-item-utility {
                margin-left: 0;
                padding-left: 0;
                border-left: 0;
                border-top: 1px solid rgba(15, 23, 42, .08);
                padding-top: .45rem;
                margin-top: .25rem;
            }

            .site-menu-item > a {
                width: 100%;
                justify-content: space-between;
            }

            .site-submenu {
                position: static;
                display: block;
                margin-top: .15rem;
                margin-left: .85rem;
                border: 0;
                box-shadow: none;
                padding: .1rem 0 .2rem;
                min-width: 0;
            }

            .site-cta-btn {
                width: 100%;
            }

            .registration-id-preview-sticky {
                top: calc(var(--header-logo-height) + 2rem);
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <nav class="site-nav" data-site-nav>
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
            <button class="site-menu-toggle" type="button" aria-expanded="false" aria-controls="site-registration-navigation-menu">
                <span class="site-menu-toggle-bars" aria-hidden="true"></span>
                <span>Menu</span>
            </button>
            <div class="site-nav-panel" id="site-registration-navigation-menu">
                <ul class="site-menu">
                    @if($useManagedNavigation)
                        @foreach($managedNavigation as $menuItem)
                            @php($children = $menuItem->children ?? collect())
                            <li class="site-menu-item {{ $children->isNotEmpty() ? 'has-submenu' : '' }}">
                                <a href="{{ $menuItem->resolvedUrl() }}" target="{{ $menuItem->target }}" @if($menuItem->target === '_blank') rel="noopener noreferrer" @endif>{{ $menuItem->title }}</a>
                                @if($children->isNotEmpty())
                                    <ul class="site-submenu">
                                        @foreach($children as $child)
                                            <li><a href="{{ $child->resolvedUrl() }}" target="{{ $child->target }}" @if($child->target === '_blank') rel="noopener noreferrer" @endif>{{ $child->title }}</a></li>
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
                    @guest
                        @if($settings->show_login_link)
                            <li class="site-menu-item site-menu-item-utility"><a href="{{ route('login') }}">Login</a></li>
                        @endif
                    @endguest
                </ul>
                <a class="site-cta-btn" href="{{ route('home') }}">Back to Website</a>
            </div>
        </nav>
    </header>

    <main class="container py-4 py-lg-5">
        <section class="registration-hero p-4 p-lg-5 mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-12">
                    <h1 class="display-5 fw-bold mb-3">Participant Registration</h1>
                </div>
            </div>
        </section>

        <div class="registration-id-preview-sticky">
            <div class="registration-id-preview">
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div class="fw-semibold">Participant ID:</div>
                    <div class="fs-5 fw-bold text-nowrap" id="participant-id-preview">XXX00000000</div>
                </div>
            </div>
        </div>

        @if(session('success') && is_array($selectedRegistration))
            <div class="alert alert-success border-0 shadow-sm mb-4">
                <div class="fw-semibold">{{ session('success') }}</div>
                <div class="small mt-1">Participant: {{ $selectedRegistration['name'] ?? '' }} | ID: {{ $selectedRegistration['participant_code'] ?? '' }}</div>
            </div>
        @elseif(session('success'))
            <div class="alert alert-success border-0 shadow-sm mb-4">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm mb-4">
                <div class="fw-semibold mb-2">Please correct the highlighted fields.</div>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="registration-form-card p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <div class="registration-kicker mb-2">Registration Form</div>
                    <h2 class="h3 mb-1">Enter Participant Details</h2>
                    <p class="text-secondary mb-0">Fields marked by validation are required. The generated participant ID is shown after successful submission.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('participant-registration.store') }}" id="public-participant-registration-form" novalidate>
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="first_name">First Name<span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="father_name">Father's Name<span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control @error('father_name') is-invalid @enderror" id="father_name" name="father_name" value="{{ old('father_name') }}" required>
                        @error('father_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="grandfather_name">Grandfather's Name<span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control @error('grandfather_name') is-invalid @enderror" id="grandfather_name" name="grandfather_name" value="{{ old('grandfather_name') }}" required>
                        @error('grandfather_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                        <div class="registration-field-note mt-1">Age is calculated as of July 1 of the current year.</div>
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="age">Age</label>
                        <input type="number" min="0" max="120" step="1" class="form-control @error('age') is-invalid @enderror" id="age" name="age" value="{{ old('age') }}">
                        <div class="registration-field-note mt-1">If age is entered first, date of birth is approximated to July 1.</div>
                        @error('age')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="gender">Gender<span class="required-mark" aria-hidden="true">*</span></label>
                        <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="male" @selected(old('gender') === 'male')>Male</option>
                            <option value="female" @selected(old('gender') === 'female')>Female</option>
                        </select>
                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="profession">Profession<span class="required-mark" aria-hidden="true">*</span></label>
                        <select class="form-select @error('profession') is-invalid @enderror" id="profession" name="profession" required>
                            <option value="">Select profession</option>
                            @foreach($professions as $profession)
                                <option value="{{ $profession->name }}" @selected(old('profession') === $profession->name)>{{ $profession->name }}</option>
                            @endforeach
                        </select>
                        @error('profession')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="home_phone">Home Phone</label>
                        <input type="text" class="form-control @error('home_phone') is-invalid @enderror" id="home_phone" name="home_phone" value="{{ old('home_phone') }}">
                        @error('home_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mobile_phone">Mobile Phone<span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control @error('mobile_phone') is-invalid @enderror" id="mobile_phone" name="mobile_phone" value="{{ old('mobile_phone') }}" required>
                        @error('mobile_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="email">Email<span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="region_id">Region<span class="required-mark" aria-hidden="true">*</span></label>
                        <select class="form-select @error('region_id') is-invalid @enderror" id="region_id" name="region_id" required>
                            <option value="">Select region</option>
                            @foreach($regions as $region)
                                <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }}</option>
                            @endforeach
                        </select>
                        @error('region_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="zone_id">Zone<span class="required-mark" aria-hidden="true">*</span></label>
                        <select class="form-select @error('zone_id') is-invalid @enderror" id="zone_id" name="zone_id" required>
                            <option value="">Select zone</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}" data-region-id="{{ $zone->region_id }}" @selected((string) old('zone_id') === (string) $zone->id)>{{ $zone->name }}</option>
                            @endforeach
                        </select>
                        @error('zone_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="woreda_id">Woreda<span class="required-mark" aria-hidden="true">*</span></label>
                        <select class="form-select @error('woreda_id') is-invalid @enderror" id="woreda_id" name="woreda_id" required>
                            <option value="">Select woreda</option>
                            @foreach($woredas as $woreda)
                                <option value="{{ $woreda->id }}" data-region-id="{{ $woreda->region_id }}" data-zone-id="{{ $woreda->zone_id }}" @selected((string) old('woreda_id') === (string) $woreda->id)>{{ $woreda->name }}</option>
                            @endforeach
                        </select>
                        @error('woreda_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="organization_id">Organization<span class="required-mark" aria-hidden="true">*</span></label>
                        <select class="form-select @error('organization_id') is-invalid @enderror" id="organization_id" name="organization_id" data-remote-url="{{ route('participant-registration.organization-options') }}" required>
                            <option value="">Select organization</option>
                            @if($selectedOrganization)
                                <option value="{{ $selectedOrganization['value'] }}" selected data-region-id="{{ $selectedOrganization['region_id'] }}" data-zone-id="{{ $selectedOrganization['zone_id'] }}" data-woreda-id="{{ $selectedOrganization['woreda_id'] }}">{{ $selectedOrganization['label'] }}</option>
                            @endif
                        </select>
                        <div class="registration-field-note mt-1">Type at least 2 characters or choose region, zone, and woreda first to load matching organizations.</div>
                        @error('organization_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 pt-2">
                        <button type="submit" class="registration-submit">Submit Registration</button>
                    </div>
                </div>
            </form>
        </section>
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
                        <li><a href="{{ route('participant-registration.create') }}">Participant Registration</a></li>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileNavigationQuery = window.matchMedia('(max-width: 991.98px)');

            document.querySelectorAll('[data-site-nav]').forEach((nav) => {
                const toggle = nav.querySelector('.site-menu-toggle');
                const panelId = toggle?.getAttribute('aria-controls');
                const panel = panelId ? document.getElementById(panelId) : null;

                if (!(toggle instanceof HTMLButtonElement) || !panel) {
                    return;
                }

                const setOpen = (isOpen) => {
                    nav.classList.toggle('is-menu-open', isOpen);
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                };

                toggle.addEventListener('click', () => {
                    setOpen(!nav.classList.contains('is-menu-open'));
                });

                panel.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', () => {
                        if (mobileNavigationQuery.matches) {
                            setOpen(false);
                        }
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!mobileNavigationQuery.matches || !nav.classList.contains('is-menu-open')) {
                        return;
                    }

                    if (event.target instanceof Node && !nav.contains(event.target)) {
                        setOpen(false);
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && nav.classList.contains('is-menu-open')) {
                        setOpen(false);
                        toggle.focus();
                    }
                });

                const handleViewportChange = () => {
                    if (!mobileNavigationQuery.matches) {
                        setOpen(false);
                    }
                };

                if (typeof mobileNavigationQuery.addEventListener === 'function') {
                    mobileNavigationQuery.addEventListener('change', handleViewportChange);
                } else {
                    mobileNavigationQuery.addListener(handleViewportChange);
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('public-participant-registration-form');
            if (!form) {
                return;
            }

            const regionSelect = document.getElementById('region_id');
            const zoneSelect = document.getElementById('zone_id');
            const woredaSelect = document.getElementById('woreda_id');
            const organizationSelect = document.getElementById('organization_id');
            const professionSelect = document.getElementById('profession');
            const dobInput = document.getElementById('date_of_birth');
            const ageInput = document.getElementById('age');
            const mobilePhoneInput = document.getElementById('mobile_phone');
            const firstNameInput = document.getElementById('first_name');
            const fatherNameInput = document.getElementById('father_name');
            const grandfatherNameInput = document.getElementById('grandfather_name');
            const participantIdPreview = document.getElementById('participant-id-preview');

            const originalZoneOptions = Array.from(zoneSelect.options).map((option) => ({
                value: option.value,
                label: option.textContent,
                regionId: option.dataset.regionId || '',
                selected: option.selected,
            }));

            const originalWoredaOptions = Array.from(woredaSelect.options).map((option) => ({
                value: option.value,
                label: option.textContent,
                regionId: option.dataset.regionId || '',
                zoneId: option.dataset.zoneId || '',
                selected: option.selected,
            }));

            const rebuildSelect = (select, options, selectedValue) => {
                select.innerHTML = '';

                options.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    if (item.regionId) {
                        option.dataset.regionId = item.regionId;
                    }
                    if (item.zoneId) {
                        option.dataset.zoneId = item.zoneId;
                    }
                    if (String(item.value) === String(selectedValue)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            };

            const filterHierarchy = () => {
                const regionId = regionSelect.value || '';
                const zoneId = zoneSelect.value || '';
                const currentZone = zoneSelect.value || '';
                const currentWoreda = woredaSelect.value || '';

                const zoneOptions = originalZoneOptions.filter((option) => {
                    if (option.value === '') {
                        return true;
                    }

                    return !regionId || String(option.regionId) === String(regionId);
                });

                const nextZone = zoneOptions.some((option) => String(option.value) === String(currentZone))
                    ? currentZone
                    : '';

                rebuildSelect(zoneSelect, zoneOptions, nextZone);

                const activeZoneId = zoneSelect.value || zoneId;
                const woredaOptions = originalWoredaOptions.filter((option) => {
                    if (option.value === '') {
                        return true;
                    }

                    if (regionId && String(option.regionId) !== String(regionId)) {
                        return false;
                    }

                    if (activeZoneId && String(option.zoneId) !== String(activeZoneId)) {
                        return false;
                    }

                    return true;
                });

                const nextWoreda = woredaOptions.some((option) => String(option.value) === String(currentWoreda))
                    ? currentWoreda
                    : '';

                rebuildSelect(woredaSelect, woredaOptions, nextWoreda);
            };

            const clearOrganizationSelection = () => {
                if (!organizationSelect.tomselect) {
                    organizationSelect.value = '';
                    return;
                }

                organizationSelect.tomselect.clear(true);
                organizationSelect.tomselect.clearOptions();
            };

            const buildOrganizationUrl = (query = '') => {
                const url = new URL(organizationSelect.dataset.remoteUrl, window.location.origin);

                if (query) {
                    url.searchParams.set('q', query);
                }

                if (regionSelect.value) {
                    url.searchParams.set('region_id', regionSelect.value);
                }

                if (zoneSelect.value) {
                    url.searchParams.set('zone_id', zoneSelect.value);
                }

                if (woredaSelect.value) {
                    url.searchParams.set('woreda_id', woredaSelect.value);
                }

                if (organizationSelect.value) {
                    url.searchParams.set('selected_id', organizationSelect.value);
                }

                return url.toString();
            };

            const organizationTom = new TomSelect(organizationSelect, {
                create: false,
                allowEmptyOption: false,
                maxOptions: 50,
                hidePlaceholder: true,
                placeholder: 'Search organization',
                valueField: 'value',
                labelField: 'label',
                searchField: ['label'],
                options: Array.from(organizationSelect.options)
                    .filter((option) => option.value !== '')
                    .map((option) => ({
                        value: option.value,
                        label: option.textContent,
                        region_id: option.dataset.regionId || '',
                        zone_id: option.dataset.zoneId || '',
                        woreda_id: option.dataset.woredaId || '',
                    })),
                items: organizationSelect.value ? [organizationSelect.value] : [],
                loadThrottle: 250,
                shouldLoad(query) {
                    return query.length >= 2 || Boolean(regionSelect.value || zoneSelect.value || woredaSelect.value || this.getValue());
                },
                load(query, callback) {
                    fetch(buildOrganizationUrl(query), {
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
                    if ((regionSelect.value || zoneSelect.value || woredaSelect.value || this.getValue()) && Object.keys(this.options).length <= 1) {
                        this.load('');
                    }
                },
            });
            organizationTom.removeOption('');

            if (professionSelect) {
                const professionTom = new TomSelect(professionSelect, {
                    create: false,
                    allowEmptyOption: false,
                    hidePlaceholder: true,
                    placeholder: 'Search profession',
                });
                professionTom.removeOption('');
            }

            regionSelect.addEventListener('change', () => {
                filterHierarchy();
                clearOrganizationSelection();
            });

            zoneSelect.addEventListener('change', () => {
                filterHierarchy();
                clearOrganizationSelection();
            });

            woredaSelect.addEventListener('change', clearOrganizationSelection);

            filterHierarchy();

            const referenceYear = new Date().getFullYear();
            let syncingBirthData = false;

            const pad = (number) => String(number).padStart(2, '0');
            const parseDob = (value) => {
                if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                    return null;
                }

                const [year, month, day] = value.split('-').map(Number);
                if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
                    return null;
                }

                return { year, month, day };
            };

            const ageFromDob = (value) => {
                const dob = parseDob(value);
                if (!dob) {
                    return null;
                }

                let age = referenceYear - dob.year;
                if (dob.month > 7 || (dob.month === 7 && dob.day > 1)) {
                    age -= 1;
                }

                return Math.max(0, age);
            };

            const dobFromAge = (value) => {
                const age = Number.parseInt(value, 10);
                if (!Number.isFinite(age) || age < 0) {
                    return '';
                }

                const year = referenceYear - age;
                return `${year}-${pad(7)}-${pad(1)}`;
            };

            const syncAgeFromDob = () => {
                if (syncingBirthData) {
                    return;
                }

                syncingBirthData = true;
                const age = ageFromDob(dobInput.value);
                ageInput.value = age === null ? '' : String(age);
                syncingBirthData = false;
                updateParticipantPreview();
            };

            const syncDobFromAge = () => {
                if (syncingBirthData) {
                    return;
                }

                syncingBirthData = true;
                dobInput.value = dobFromAge(ageInput.value);
                syncingBirthData = false;
                updateParticipantPreview();
            };

            dobInput.addEventListener('input', syncAgeFromDob);
            dobInput.addEventListener('change', syncAgeFromDob);
            ageInput.addEventListener('input', syncDobFromAge);
            ageInput.addEventListener('change', syncDobFromAge);

            const initialForCode = (value) => {
                const text = (value || '').trim();
                return text ? text.charAt(0).toUpperCase() : 'X';
            };

            const last4ForCode = (value) => {
                const digits = (value || '').replace(/\D+/g, '');
                const tail = digits.slice(-4);
                return tail.padStart(4, '0');
            };

            const datePartsForCode = () => {
                const dob = parseDob(dobInput.value);
                if (!dob) {
                    return ['0000', '00'];
                }

                return [String(dob.year), pad(dob.month)];
            };

            const updateParticipantPreview = () => {
                const [year, month] = datePartsForCode();
                participantIdPreview.textContent = [
                    initialForCode(firstNameInput.value),
                    initialForCode(fatherNameInput.value),
                    initialForCode(grandfatherNameInput.value),
                    year,
                    month,
                    last4ForCode(mobilePhoneInput.value),
                ].join('');
            };

            [firstNameInput, fatherNameInput, grandfatherNameInput, mobilePhoneInput].forEach((input) => {
                input.addEventListener('input', updateParticipantPreview);
            });

            if (dobInput.value && !ageInput.value) {
                syncAgeFromDob();
            } else if (ageInput.value && !dobInput.value) {
                syncDobFromAge();
            } else {
                updateParticipantPreview();
            }
        });
    </script>
</body>
</html>
