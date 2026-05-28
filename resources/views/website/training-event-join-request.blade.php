<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Training Event Enrollment | {{ $websiteSettings->site_name ?? config('app.name', 'HIL Website') }}</title>
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
            color: #000000;
            font: inherit;
            font-weight: 600;
            line-height: 1;
            padding: .55rem .75rem;
            box-shadow: 0 8px 14px rgba(15, 23, 42, .08);
        }

        .site-menu-toggle:hover,
        .site-menu-toggle:focus-visible {
            background: rgba(15, 23, 42, .08);
            color: #000000;
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
            content: 'v';
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

        .registration-hero,
        .registration-form-card {
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
        .registration-form-card .form-select {
            border-radius: var(--radius-sm);
            min-height: calc(2.75rem + 2px);
        }

        .registration-form-card .form-control:focus,
        .registration-form-card .form-select:focus {
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

        .participant-search-wrap {
            position: relative;
        }

        .participant-search-results {
            display: none;
            position: absolute;
            z-index: 30;
            top: calc(100% - .25rem);
            right: 0;
            left: 0;
            max-height: 260px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, .16);
            border-radius: var(--radius-sm);
            box-shadow: 0 18px 36px rgba(15, 23, 42, .14);
        }

        .participant-search-results.is-open {
            display: block;
        }

        .participant-search-option,
        .participant-search-empty {
            width: 100%;
            padding: .72rem .85rem;
            text-align: left;
            border: 0;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
            background: #ffffff;
        }

        .participant-search-option:hover,
        .participant-search-option:focus {
            background: #f8fafc;
        }

        .participant-search-option:last-child,
        .participant-search-empty:last-child {
            border-bottom: 0;
        }

        .participant-register-action {
            margin-top: .55rem;
            border: 1px solid var(--body-accent);
            border-radius: var(--radius-sm);
            background: #ffffff;
            color: var(--body-accent);
            padding: .45rem .7rem;
            font-weight: 600;
        }

        .participant-register-action:hover,
        .participant-register-action:focus {
            background: color-mix(in srgb, var(--body-accent) 10%, #ffffff);
        }

        .participant-search-label,
        .participant-search-hint {
            display: block;
        }

        .participant-search-label {
            font-weight: 600;
            color: #0f172a;
        }

        .participant-search-hint {
            margin-top: .15rem;
            font-size: .82rem;
            color: #64748b;
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
            <button class="site-menu-toggle" type="button" aria-expanded="false" aria-controls="site-join-request-navigation-menu">
                <span class="site-menu-toggle-bars" aria-hidden="true"></span>
                <span>Menu</span>
            </button>
            <div class="site-nav-panel" id="site-join-request-navigation-menu">
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
                    <h1 class="display-5 fw-bold mb-3">Request to Join a Training Event</h1>
                    <p class="lead text-secondary mb-0">Search for your name and enter your registered mobile phone. Requests are reviewed by the event organizer or manager before enrollment.</p>
                </div>
            </div>
        </section>

        @if(session('success'))
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
                    <div class="registration-kicker mb-2">Training Event Request</div>
                    <h2 class="h3 mb-1">Enrollment Request</h2>
                    <p class="text-secondary mb-0">Select the event, confirm your participant record, and submit the request for organizer approval.</p>
                </div>
            </div>

            @if($requestableEvents->isEmpty())
                <div class="alert alert-warning mb-0">No training events are currently accepting join requests.</div>
            @else
                <form method="POST" action="{{ route('training-event-join-requests.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label" for="training_event_id">Training Event<span class="required-mark" aria-hidden="true">*</span></label>
                        <select id="training_event_id" name="training_event_id" class="form-select @error('training_event_id') is-invalid @enderror" required>
                            <option value="">Select training event</option>
                            @foreach($requestableEvents as $event)
                                <option value="{{ $event->id }}" @selected((string) $selectedEventId === (string) $event->id)>
                                    {{ $event->event_name ?: 'Event #'.$event->id }} | {{ $event->training?->title ?: 'No training' }} | {{ $event->start_date }} to {{ $event->end_date }}
                                </option>
                            @endforeach
                        </select>
                        @error('training_event_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6 participant-search-wrap">
                        <label class="form-label" for="participant_name">Participant Name<span class="required-mark" aria-hidden="true">*</span></label>
                        <input id="participant_id" name="participant_id" type="hidden" value="{{ old('participant_id') }}">
                        <input
                            id="participant_name"
                            name="participant_name"
                            type="text"
                            autocomplete="off"
                            class="form-control @error('participant_name') is-invalid @enderror"
                            value="{{ old('participant_name') }}"
                            data-search-url="{{ route('training-event-join-requests.participant-options') }}"
                            data-registration-request-url="{{ route('training-event-join-requests.register') }}"
                            required
                        >
                        <div id="participant-search-results" class="participant-search-results" role="listbox"></div>
                        <div class="registration-field-note mt-1">Start typing your name and select the matching record.</div>
                        @error('participant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="mobile_phone">Registered Mobile Phone<span class="required-mark" aria-hidden="true">*</span></label>
                        <input id="mobile_phone" name="mobile_phone" type="tel" inputmode="tel" maxlength="30" class="form-control @error('mobile_phone') is-invalid @enderror" value="{{ old('mobile_phone') }}" required>
                        @error('mobile_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="requested_message">Message to Organizer</label>
                        <textarea id="requested_message" name="requested_message" rows="4" maxlength="1000" class="form-control @error('requested_message') is-invalid @enderror">{{ old('requested_message') }}</textarea>
                        @error('requested_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 pt-2">
                        <button type="submit" class="registration-submit">Submit Request</button>
                    </div>
                </form>
            @endif
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
                        <li><a href="{{ route('training-event-join-requests.create') }}">Request Training Event</a></li>
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
            const nameInput = document.getElementById('participant_name');
            const participantIdInput = document.getElementById('participant_id');
            const results = document.getElementById('participant-search-results');
            const trainingEventSelect = document.getElementById('training_event_id');
            const mobilePhoneInput = document.getElementById('mobile_phone');
            const requestedMessageInput = document.getElementById('requested_message');
            const csrfTokenInput = document.querySelector('input[name="_token"]');

            if (!nameInput || !participantIdInput || !results || !trainingEventSelect || !csrfTokenInput || !nameInput.dataset.searchUrl || !nameInput.dataset.registrationRequestUrl) {
                return;
            }

            let searchTimer = null;
            let activeRequest = null;
            let phoneAutofilled = false;

            const closeResults = () => {
                results.classList.remove('is-open');
                results.replaceChildren();
            };

            const askToRegister = () => {
                if (!trainingEventSelect.value) {
                    window.alert('Select a training event first.');
                    trainingEventSelect.focus();
                    return;
                }

                if (window.confirm('No registration was found for this participant. Do you want to register now and submit this training event request?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = nameInput.dataset.registrationRequestUrl;

                    const fields = {
                        _token: csrfTokenInput.value,
                        training_event_id: trainingEventSelect.value,
                        participant_name: nameInput.value,
                        mobile_phone: mobilePhoneInput ? mobilePhoneInput.value : '',
                        requested_message: requestedMessageInput ? requestedMessageInput.value : '',
                    };

                    Object.entries(fields).forEach(([name, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.append(input);
                    });

                    document.body.append(form);
                    form.submit();
                }
            };

            const renderEmpty = (message, offerRegistration = false) => {
                const item = document.createElement('div');
                item.className = 'participant-search-empty';

                const text = document.createElement('div');
                text.textContent = message;
                item.append(text);

                if (offerRegistration) {
                    const registerButton = document.createElement('button');
                    registerButton.type = 'button';
                    registerButton.className = 'participant-register-action';
                    registerButton.textContent = 'Register and request event';
                    registerButton.addEventListener('click', askToRegister);
                    item.append(registerButton);
                }

                results.replaceChildren(item);
                results.classList.add('is-open');
            };

            const selectOption = (option) => {
                participantIdInput.value = option.value;
                nameInput.value = option.label;

                if (mobilePhoneInput && option.mobile_phone) {
                    mobilePhoneInput.value = option.mobile_phone;
                    phoneAutofilled = true;
                }

                closeResults();
            };

            const renderOptions = (options) => {
                if (!options.length) {
                    renderEmpty('No registered participant found with this name.', true);
                    return;
                }

                const items = options.map((option) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'participant-search-option';
                    button.setAttribute('role', 'option');

                    const label = document.createElement('span');
                    label.className = 'participant-search-label';
                    label.textContent = option.label;

                    const hint = document.createElement('span');
                    hint.className = 'participant-search-hint';
                    hint.textContent = option.hint || '';

                    button.append(label, hint);
                    button.addEventListener('click', () => selectOption(option));

                    return button;
                });

                results.replaceChildren(...items);
                results.classList.add('is-open');
            };

            const searchParticipants = () => {
                const query = nameInput.value.trim();
                participantIdInput.value = '';

                if (query.length < 2) {
                    closeResults();
                    return;
                }

                if (activeRequest) {
                    activeRequest.abort();
                }

                activeRequest = new AbortController();

                fetch(`${nameInput.dataset.searchUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: activeRequest.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(response))
                    .then((data) => renderOptions(data.options || []))
                    .catch((error) => {
                        if (error.name !== 'AbortError') {
                            renderEmpty('Participant search is currently unavailable.');
                        }
                    });
            };

            nameInput.addEventListener('input', () => {
                if (phoneAutofilled && mobilePhoneInput) {
                    mobilePhoneInput.value = '';
                    phoneAutofilled = false;
                }

                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(searchParticipants, 250);
            });

            if (mobilePhoneInput) {
                mobilePhoneInput.addEventListener('input', () => {
                    phoneAutofilled = false;
                });
            }

            nameInput.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeResults();
                }
            });

            document.addEventListener('click', (event) => {
                if (!results.contains(event.target) && event.target !== nameInput) {
                    closeResults();
                }
            });
        });
    </script>
    @include('website.partials.fab-chatbot', ['settings' => $settings])
</body>
</html>
