@extends('layouts.admin')

@section('eyebrow', 'Website')
@section('title', 'Appearance Studio')
@section('subtitle', 'Configure brand, navigation, colors, footer content, and advanced front-end overrides.')

@section('head')
<style>
    .appearance-shell {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(300px, .9fr);
        gap: 1.25rem;
        align-items: start;
    }
    .appearance-main {
        display: grid;
        gap: 1rem;
    }
    .appearance-sidebar {
        position: sticky;
        top: 1rem;
        display: grid;
        gap: 1rem;
    }
    .appearance-card {
        background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(248,250,252,.98) 100%);
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: var(--radius-xl);
        box-shadow: 0 16px 40px rgba(15, 23, 42, .06);
        overflow: hidden;
    }
    .appearance-card-header {
        padding: 1rem 1.25rem .75rem;
        border-bottom: 1px solid rgba(15, 23, 42, .06);
        background: linear-gradient(135deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
    }
    .appearance-card-body {
        padding: 1.25rem;
    }
    .appearance-kicker {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #64748b;
    }
    .appearance-kicker::before {
        content: "";
        width: .55rem;
        height: .55rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #0f766e 0%, #0f172a 100%);
        box-shadow: 0 0 0 4px rgba(15, 118, 110, .12);
    }
    .appearance-title {
        margin: .55rem 0 .35rem;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .appearance-description {
        margin: 0;
        color: #64748b;
        font-size: .95rem;
        line-height: 1.55;
    }
    .appearance-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1rem;
    }
    .appearance-col-12 { grid-column: span 12; }
    .appearance-col-8 { grid-column: span 8; }
    .appearance-col-6 { grid-column: span 6; }
    .appearance-col-4 { grid-column: span 4; }
    .appearance-col-3 { grid-column: span 3; }
    .appearance-field label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        margin-bottom: .45rem;
        font-size: .88rem;
        font-weight: 600;
        color: #1e293b;
    }
    .appearance-field .field-hint {
        display: block;
        margin-top: .45rem;
        color: #64748b;
        font-size: .82rem;
        line-height: 1.45;
    }
    .appearance-field .form-control,
    .appearance-field .form-select {
        border-radius: var(--radius-md);
        border-color: rgba(148, 163, 184, .35);
        box-shadow: none;
        min-height: 2.9rem;
    }
    .appearance-field textarea.form-control {
        min-height: 8rem;
    }
    .appearance-field .form-control:focus,
    .appearance-field .form-select:focus {
        border-color: rgba(15, 118, 110, .55);
        box-shadow: 0 0 0 .2rem rgba(15, 118, 110, .12);
    }
    .appearance-field .form-control-color {
        width: 100%;
        min-height: 3rem;
        padding: .45rem;
        border-radius: var(--radius-md);
    }
    .appearance-color-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-top: .5rem;
        color: #64748b;
        font-size: .82rem;
    }
    .appearance-color-chip {
        width: 1rem;
        height: 1rem;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, .12);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.4);
    }
    .appearance-section-nav {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
        margin-top: 1rem;
    }
    .appearance-section-nav a {
        text-decoration: none;
        color: #334155;
        font-size: .84rem;
        font-weight: 600;
        background: #f8fafc;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 999px;
        padding: .55rem .85rem;
    }
    .appearance-section-nav a:hover {
        background: #eff6ff;
        border-color: rgba(37, 99, 235, .18);
        color: #0f172a;
    }
    .appearance-logo-preview {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 110px;
        padding: 1rem;
        border-radius: var(--radius-lg);
        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        border: 1px dashed rgba(100, 116, 139, .35);
    }
    .appearance-logo-preview img {
        max-width: 100%;
        object-fit: contain;
    }
    .appearance-toggle-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
    }
    .appearance-toggle-card {
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: var(--radius-lg);
        padding: 1rem;
        background: #fff;
    }
    .appearance-toggle-card .form-check-input {
        width: 2.5rem;
        height: 1.35rem;
        margin-top: 0;
    }
    .appearance-toggle-card .form-check {
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 1rem;
        margin: 0;
    }
    .appearance-toggle-card .form-check-label {
        display: block;
    }
    .appearance-toggle-title {
        display: block;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: .2rem;
    }
    .appearance-toggle-copy {
        color: #64748b;
        font-size: .85rem;
        line-height: 1.45;
    }
    .appearance-studio {
        position: relative;
        overflow: hidden;
        padding: 1.35rem;
        color: #e2e8f0;
        background:
            radial-gradient(circle at top right, rgba(56, 189, 248, .24), transparent 36%),
            radial-gradient(circle at bottom left, rgba(20, 184, 166, .24), transparent 34%),
            linear-gradient(145deg, #0f172a 0%, #111827 50%, #0b1220 100%);
    }
    .appearance-studio::after {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(120deg, rgba(255,255,255,.05), transparent 34%),
            linear-gradient(transparent, rgba(255,255,255,.02));
        pointer-events: none;
    }
    .appearance-studio > * {
        position: relative;
        z-index: 1;
    }
    .appearance-studio-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #fff;
        margin: .5rem 0;
    }
    .appearance-studio-copy {
        margin: 0;
        color: rgba(226, 232, 240, .78);
        line-height: 1.6;
    }
    .appearance-preview {
        background: #fff;
        border-radius: 28px;
        padding: .9rem;
        box-shadow: 0 28px 65px rgba(2, 6, 23, .45);
        border: 1px solid rgba(255,255,255,.08);
    }
    .appearance-preview-browser {
        display: flex;
        align-items: center;
        gap: .4rem;
        margin-bottom: .75rem;
    }
    .appearance-preview-browser span {
        width: .7rem;
        height: .7rem;
        border-radius: 999px;
        background: #cbd5e1;
    }
    .appearance-preview-browser span:nth-child(1) { background: #f87171; }
    .appearance-preview-browser span:nth-child(2) { background: #fbbf24; }
    .appearance-preview-browser span:nth-child(3) { background: #34d399; }
    .appearance-preview-shell {
        overflow: hidden;
        border-radius: 22px;
        border: 1px solid rgba(15, 23, 42, .08);
        background: var(--preview-body-bg, #f8fafc);
        color: var(--preview-body-text, #0f172a);
    }
    .appearance-preview-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.2rem;
        background: var(--preview-header-bg, #ffffff);
        color: var(--preview-header-text, #0f172a);
    }
    .appearance-preview-brand {
        min-width: 0;
    }
    .appearance-preview-brand strong {
        display: block;
        font-size: 1rem;
        line-height: 1.3;
    }
    .appearance-preview-brand span {
        display: block;
        margin-top: .2rem;
        font-size: .77rem;
        opacity: .72;
    }
    .appearance-preview-pill {
        border-radius: var(--preview-pill-radius, 999px);
        padding: .62rem 1rem;
        border: 0;
        font-size: .82rem;
        font-weight: 700;
        color: #fff;
        background: var(--preview-link, #334155);
        white-space: nowrap;
    }
    .appearance-preview-main {
        padding: 1.15rem;
        background:
            radial-gradient(circle at top left, rgba(15, 118, 110, .08), transparent 28%),
            linear-gradient(180deg, rgba(255,255,255,.3), rgba(255,255,255,0));
    }
    .appearance-preview-panel {
        border-radius: var(--preview-panel-radius, 18px);
        background: var(--preview-panel-bg, #ffffff);
        border: 1px solid rgba(15, 23, 42, .06);
        padding: 1rem;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
    }
    .appearance-preview-panel h3 {
        margin: 0 0 .45rem;
        font-size: 1rem;
        font-weight: 700;
    }
    .appearance-preview-panel p {
        margin: 0;
        color: rgba(15, 23, 42, .72);
        font-size: .84rem;
        line-height: 1.6;
    }
    .appearance-preview-accent {
        display: flex;
        align-items: center;
        gap: .55rem;
        margin-top: .85rem;
        font-size: .8rem;
        color: rgba(15, 23, 42, .7);
    }
    .appearance-preview-accent-dot {
        width: .85rem;
        height: .85rem;
        border-radius: 999px;
        background: var(--preview-accent, #0f766e);
        box-shadow: 0 0 0 6px rgba(15, 118, 110, .12);
    }
    .appearance-preview-footer {
        padding: .95rem 1.2rem;
        background: var(--preview-footer-bg, #0f172a);
        color: var(--preview-footer-text, #e2e8f0);
        font-size: .8rem;
        display: flex;
        justify-content: space-between;
        gap: .75rem;
    }
    .appearance-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
    }
    .appearance-metric {
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: var(--radius-lg);
        padding: .9rem 1rem;
        background: #fff;
    }
    .appearance-metric-label {
        font-size: .75rem;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
    }
    .appearance-metric-value {
        margin-top: .35rem;
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
    }
    .appearance-actions {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .appearance-sticky-actions {
        position: sticky;
        bottom: 1rem;
        padding: 1rem 1.1rem;
        background: rgba(255,255,255,.94);
        border: 1px solid rgba(15, 23, 42, .08);
        backdrop-filter: blur(10px);
        border-radius: var(--radius-xl);
        box-shadow: 0 18px 40px rgba(15, 23, 42, .08);
    }
    .appearance-code-label {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #475569;
    }
    .appearance-code-label::before {
        content: "</>";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.1rem;
        height: 1.6rem;
        padding: 0 .45rem;
        border-radius: 999px;
        background: #e2e8f0;
        color: #0f172a;
        font-size: .72rem;
        letter-spacing: .04em;
    }
    @media (max-width: 1199.98px) {
        .appearance-shell {
            grid-template-columns: 1fr;
        }
        .appearance-sidebar {
            position: static;
        }
    }
    @media (max-width: 767.98px) {
        .appearance-grid {
            grid-template-columns: 1fr;
        }
        .appearance-col-12,
        .appearance-col-8,
        .appearance-col-6,
        .appearance-col-4,
        .appearance-col-3 {
            grid-column: auto;
        }
        .appearance-toggle-row,
        .appearance-metrics {
            grid-template-columns: 1fr;
        }
        .appearance-preview-header,
        .appearance-preview-footer {
            flex-direction: column;
            align-items: start;
        }
    }
</style>
@endsection

@section('actions')
<div class="appearance-actions">
    <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-dark">Preview Website</a>
    <a href="{{ route('admin.settings.env.edit') }}" class="btn btn-outline-secondary">Env Settings</a>
</div>
@endsection

@section('content')
@php
    $activeSection = $activeSection ?? '';
    $resolveLogo = function (?string $value): ?string {
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

    $faviconPreview = $resolveLogo($settings->favicon_url);
    $headerLogoPreview = $resolveLogo($settings->header_logo_url);
    $footerLogoPreview = $resolveLogo($settings->footer_logo_url);
    $defaultSiteName = old('site_name', $settings->site_name ?: config('app.name', 'HIL Website'));
    $defaultTagline = old('site_tagline', $settings->site_tagline ?: 'High-impact learning and implementation platform.');
@endphp

<form method="POST" action="{{ route('admin.appearance.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="_section" value="{{ $activeSection }}">

    <div class="appearance-shell">
        <div class="appearance-main">
            <section class="appearance-card">
                <div class="appearance-card-body appearance-studio">
                    <div class="appearance-kicker">Appearance Control Center</div>
                    <div class="row g-4 align-items-center mt-1">
                        <div class="col-lg-6">
                            <h2 class="appearance-studio-title">Turn the settings page into a real brand workspace.</h2>
                            <p class="appearance-studio-copy">
                                This screen controls your public-facing header, body palette, footer identity, and advanced code overrides.
                                The preview updates live while you edit, so visual decisions are easier to make before saving.
                            </p>
                            <div class="appearance-section-nav">
                                <a href="#brand-system">Brand</a>
                                <a href="#header-system">Header</a>
                                <a href="#color-system">Color System</a>
                                <a href="#footer-system">Footer</a>
                                <a href="#login-system">Login Page</a>
                                <a href="#advanced-system">Advanced</a>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="appearance-preview">
                                <div class="appearance-preview-browser">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <div
                                    class="appearance-preview-shell"
                                    id="appearance-live-preview"
                                    style="
                                        --preview-header-bg: {{ old('header_background_color', $settings->header_background_color ?? '#ffffff') }};
                                        --preview-header-text: {{ old('header_text_color', $settings->header_text_color ?? '#0f172a') }};
                                        --preview-link: {{ old('header_link_color', $settings->header_link_color ?? '#334155') }};
                                        --preview-body-bg: {{ old('body_background_color', $settings->body_background_color ?? '#f8fafc') }};
                                        --preview-body-text: {{ old('body_text_color', $settings->body_text_color ?? '#0f172a') }};
                                        --preview-panel-bg: {{ old('body_panel_color', $settings->body_panel_color ?? '#ffffff') }};
                                        --preview-accent: {{ old('body_accent_color', $settings->body_accent_color ?? '#0f766e') }};
                                        --preview-footer-bg: {{ old('footer_background_color', $settings->footer_background_color ?? '#0f172a') }};
                                        --preview-footer-text: {{ old('footer_text_color', $settings->footer_text_color ?? '#e2e8f0') }};
                                        --preview-panel-radius: {{ (int) old('radius_lg', $settings->radius_lg ?? 18) }}px;
                                        --preview-pill-radius: {{ (int) old('radius_pill', $settings->radius_pill ?? 999) }}px;
                                    "
                                >
                                    <div class="appearance-preview-header">
                                        <div class="appearance-preview-brand">
                                            <strong id="preview-site-name">{{ $defaultSiteName }}</strong>
                                            <span id="preview-site-tagline">{{ $defaultTagline }}</span>
                                        </div>
                                        <button type="button" class="appearance-preview-pill" id="preview-cta-button">
                                            {{ old('header_cta_label', $settings->header_cta_label ?: 'Get Started') }}
                                        </button>
                                    </div>
                                    <div class="appearance-preview-main">
                                        <div class="appearance-preview-panel">
                                            <h3>Professional presentation starts with structure.</h3>
                                            <p>
                                                Use consistent brand colors, balanced radii, and sharper footer copy to make the website
                                                feel intentional, trustworthy, and ready for stakeholders.
                                            </p>
                                            <div class="appearance-preview-accent">
                                                <span class="appearance-preview-accent-dot"></span>
                                                <span>Accent-driven actions and highlight moments</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="appearance-preview-footer">
                                        <span id="preview-footer-title">{{ old('footer_title', $settings->footer_title ?: $defaultSiteName) }}</span>
                                        <span id="preview-footer-copy">{{ old('footer_copyright', $settings->footer_copyright ?: 'All rights reserved.') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="appearance-card" id="brand-system">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Brand</div>
                    <h2 class="appearance-title">Identity and logos</h2>
                    <p class="appearance-description">Keep naming, tagline, and logo assets aligned across header and footer placements.</p>
                </div>
                <div class="appearance-card-body">
                    <div class="appearance-grid">
                        <div class="appearance-col-6 appearance-field">
                            <label for="site_name">Site Name</label>
                            <input id="site_name" type="text" name="site_name" class="form-control" value="{{ old('site_name', $settings->site_name) }}" placeholder="Amref Learning Portal">
                            <span class="field-hint">Primary brand label shown in previews and fallback locations when no logo is present.</span>
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="site_tagline">Site Tagline</label>
                            <input id="site_tagline" type="text" name="site_tagline" class="form-control" value="{{ old('site_tagline', $settings->site_tagline) }}" placeholder="High-impact learning and implementation platform.">
                            <span class="field-hint">Short supporting message displayed beneath the site name in the live preview.</span>
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="favicon_file">Favicon Upload</label>
                            <input id="favicon_file" type="file" name="favicon_file" class="form-control" accept=".ico,.png,.jpg,.jpeg,.webp,.svg,image/x-icon,image/png,image/jpeg,image/webp,image/svg+xml">
                            <span class="field-hint">Upload ICO, PNG, JPG, WebP, or SVG up to 2MB. This icon is used in browser tabs and bookmarks.</span>
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="favicon_url">Favicon URL</label>
                            <input id="favicon_url" type="text" name="favicon_url" class="form-control" value="{{ old('favicon_url', $settings->favicon_url) }}" placeholder="https://example.com/favicon.ico">
                            <span class="field-hint">Use this when the favicon asset is hosted outside the app.</span>
                        </div>
                        <div class="appearance-col-3">
                            <div class="appearance-logo-preview" style="min-height: 96px;">
                                @if($faviconPreview)
                                    <img src="{{ $faviconPreview }}" alt="Favicon preview" style="width:48px; height:48px; object-fit:contain;">
                                @else
                                    <div class="text-secondary small text-center">Favicon preview will appear here after upload or URL save.</div>
                                @endif
                            </div>
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="header_logo_file">Header Logo Upload</label>
                            <input id="header_logo_file" type="file" name="header_logo_file" class="form-control" accept="image/*">
                            <span class="field-hint">Upload JPG, PNG, GIF, or WebP up to 4MB. Uploaded files override remote links after save.</span>
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="header_logo_url">Header Logo URL</label>
                            <input id="header_logo_url" type="text" name="header_logo_url" class="form-control" value="{{ old('header_logo_url', $settings->header_logo_url) }}" placeholder="https://example.com/logo.png">
                            <span class="field-hint">Use this when brand assets are hosted outside the app.</span>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="header_logo_height">Header Logo Height</label>
                            <input id="header_logo_height" type="number" name="header_logo_height" min="24" max="220" class="form-control" value="{{ old('header_logo_height', $settings->header_logo_height ?? 56) }}">
                            <span class="field-hint">A restrained height usually reads more professionally than oversized logos.</span>
                        </div>
                        <div class="appearance-col-6">
                            <div class="appearance-logo-preview">
                                @if($headerLogoPreview)
                                    <img src="{{ $headerLogoPreview }}" alt="Header logo preview" style="height:{{ (int) old('header_logo_height', $settings->header_logo_height ?? 56) }}px;">
                                @else
                                    <div class="text-secondary small">Header logo preview will appear here after upload or URL save.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="appearance-card" id="header-system">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Header</div>
                    <h2 class="appearance-title">Navigation and call to action</h2>
                    <p class="appearance-description">Shape the first impression with cleaner navigation contrast and a focused call to action.</p>
                </div>
                <div class="appearance-card-body">
                    <div class="appearance-grid">
                        <div class="appearance-col-4 appearance-field">
                            <label for="header_cta_label">Button Label</label>
                            <input id="header_cta_label" type="text" name="header_cta_label" class="form-control" value="{{ old('header_cta_label', $settings->header_cta_label) }}" placeholder="Get Started">
                        </div>
                        <div class="appearance-col-8 appearance-field">
                            <label for="header_cta_url">Button URL</label>
                            <input id="header_cta_url" type="url" name="header_cta_url" class="form-control" value="{{ old('header_cta_url', $settings->header_cta_url) }}" placeholder="https://example.com/contact">
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="header_background_color">Header Background</label>
                            <input id="header_background_color" type="color" name="header_background_color" class="form-control form-control-color js-appearance-color" value="{{ old('header_background_color', $settings->header_background_color ?? '#ffffff') }}">
                            <div class="appearance-color-meta">
                                <span>Canvas</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('header_background_color', $settings->header_background_color ?? '#ffffff') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="header_text_color">Header Text</label>
                            <input id="header_text_color" type="color" name="header_text_color" class="form-control form-control-color js-appearance-color" value="{{ old('header_text_color', $settings->header_text_color ?? '#0f172a') }}">
                            <div class="appearance-color-meta">
                                <span>Typography</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('header_text_color', $settings->header_text_color ?? '#0f172a') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="header_link_color">Header Link / CTA Accent</label>
                            <input id="header_link_color" type="color" name="header_link_color" class="form-control form-control-color js-appearance-color" value="{{ old('header_link_color', $settings->header_link_color ?? '#334155') }}">
                            <div class="appearance-color-meta">
                                <span>Action color</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('header_link_color', $settings->header_link_color ?? '#334155') }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="appearance-card" id="color-system">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Color System</div>
                    <h2 class="appearance-title">Body palette and shape language</h2>
                    <p class="appearance-description">Use a disciplined palette and consistent radii to make the site feel modern and dependable.</p>
                </div>
                <div class="appearance-card-body">
                    <div class="appearance-grid">
                        <div class="appearance-col-3 appearance-field">
                            <label for="body_background_color">Body Background</label>
                            <input id="body_background_color" type="color" name="body_background_color" class="form-control form-control-color js-appearance-color" value="{{ old('body_background_color', $settings->body_background_color ?? '#f8fafc') }}">
                            <div class="appearance-color-meta">
                                <span>Page background</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('body_background_color', $settings->body_background_color ?? '#f8fafc') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="body_text_color">Body Text</label>
                            <input id="body_text_color" type="color" name="body_text_color" class="form-control form-control-color js-appearance-color" value="{{ old('body_text_color', $settings->body_text_color ?? '#0f172a') }}">
                            <div class="appearance-color-meta">
                                <span>Readable contrast</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('body_text_color', $settings->body_text_color ?? '#0f172a') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="body_panel_color">Body Panel</label>
                            <input id="body_panel_color" type="color" name="body_panel_color" class="form-control form-control-color js-appearance-color" value="{{ old('body_panel_color', $settings->body_panel_color ?? '#ffffff') }}">
                            <div class="appearance-color-meta">
                                <span>Cards & panels</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('body_panel_color', $settings->body_panel_color ?? '#ffffff') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="body_accent_color">Body Accent</label>
                            <input id="body_accent_color" type="color" name="body_accent_color" class="form-control form-control-color js-appearance-color" value="{{ old('body_accent_color', $settings->body_accent_color ?? '#0f766e') }}">
                            <div class="appearance-color-meta">
                                <span>Highlights</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('body_accent_color', $settings->body_accent_color ?? '#0f766e') }}</span></span>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="appearance-grid">
                        <div class="appearance-col-12">
                            <div class="appearance-kicker">Radii</div>
                            <h3 class="appearance-title mb-2">Border radius settings</h3>
                            <p class="appearance-description">Sharper corners feel more formal; larger radii feel softer. Keep them proportional.</p>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="radius_sm">Small</label>
                            <input id="radius_sm" type="number" name="radius_sm" min="0" max="100" class="form-control js-radius-input" value="{{ old('radius_sm', $settings->radius_sm ?? 10) }}">
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="radius_md">Medium</label>
                            <input id="radius_md" type="number" name="radius_md" min="0" max="100" class="form-control js-radius-input" value="{{ old('radius_md', $settings->radius_md ?? 14) }}">
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="radius_lg">Large</label>
                            <input id="radius_lg" type="number" name="radius_lg" min="0" max="100" class="form-control js-radius-input" value="{{ old('radius_lg', $settings->radius_lg ?? 18) }}">
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="radius_xl">XL</label>
                            <input id="radius_xl" type="number" name="radius_xl" min="0" max="120" class="form-control" value="{{ old('radius_xl', $settings->radius_xl ?? 24) }}">
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="radius_pill">Pill</label>
                            <input id="radius_pill" type="number" name="radius_pill" min="0" max="999" class="form-control js-radius-input" value="{{ old('radius_pill', $settings->radius_pill ?? 999) }}">
                        </div>
                    </div>
                </div>
            </section>

            <section class="appearance-card" id="footer-system">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Footer</div>
                    <h2 class="appearance-title">Footer identity and contact details</h2>
                    <p class="appearance-description">The footer should reinforce legitimacy, contactability, and brand continuity.</p>
                </div>
                <div class="appearance-card-body">
                    <div class="appearance-grid">
                        <div class="appearance-col-6 appearance-field">
                            <label for="footer_title">Footer Title</label>
                            <input id="footer_title" type="text" name="footer_title" class="form-control" value="{{ old('footer_title', $settings->footer_title) }}">
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="footer_copyright">Footer Copyright</label>
                            <input id="footer_copyright" type="text" name="footer_copyright" class="form-control" value="{{ old('footer_copyright', $settings->footer_copyright) }}">
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="footer_logo_file">Footer Logo Upload</label>
                            <input id="footer_logo_file" type="file" name="footer_logo_file" class="form-control" accept="image/*">
                            <span class="field-hint">Use a footer-specific mark if the layout needs a more compact variant.</span>
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="footer_logo_url">Footer Logo URL</label>
                            <input id="footer_logo_url" type="text" name="footer_logo_url" class="form-control" value="{{ old('footer_logo_url', $settings->footer_logo_url) }}" placeholder="https://example.com/footer-logo.png">
                        </div>
                        <div class="appearance-col-12">
                            <div class="appearance-logo-preview">
                                @if($footerLogoPreview)
                                    <img src="{{ $footerLogoPreview }}" alt="Footer logo preview" style="max-height: 70px;">
                                @else
                                    <div class="text-secondary small">Footer logo preview will appear here after upload or URL save.</div>
                                @endif
                            </div>
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="footer_address">Footer Address</label>
                            <input id="footer_address" type="text" name="footer_address" class="form-control" value="{{ old('footer_address', $settings->footer_address) }}">
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="footer_email">Footer Email</label>
                            <input id="footer_email" type="email" name="footer_email" class="form-control" value="{{ old('footer_email', $settings->footer_email) }}">
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="footer_phone">Footer Phone</label>
                            <input id="footer_phone" type="text" name="footer_phone" class="form-control" value="{{ old('footer_phone', $settings->footer_phone) }}">
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="footer_background_color">Footer Background</label>
                            <input id="footer_background_color" type="color" name="footer_background_color" class="form-control form-control-color js-appearance-color" value="{{ old('footer_background_color', $settings->footer_background_color ?? '#0f172a') }}">
                            <div class="appearance-color-meta">
                                <span>Foundation</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('footer_background_color', $settings->footer_background_color ?? '#0f172a') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="footer_text_color">Footer Text</label>
                            <input id="footer_text_color" type="color" name="footer_text_color" class="form-control form-control-color js-appearance-color" value="{{ old('footer_text_color', $settings->footer_text_color ?? '#e2e8f0') }}">
                            <div class="appearance-color-meta">
                                <span>Legibility</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('footer_text_color', $settings->footer_text_color ?? '#e2e8f0') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="footer_link_color">Footer Link</label>
                            <input id="footer_link_color" type="color" name="footer_link_color" class="form-control form-control-color js-appearance-color" value="{{ old('footer_link_color', $settings->footer_link_color ?? '#cbd5e1') }}">
                            <div class="appearance-color-meta">
                                <span>Interactive tone</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('footer_link_color', $settings->footer_link_color ?? '#cbd5e1') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-12 appearance-field">
                            <label for="footer_about">Footer About</label>
                            <textarea id="footer_about" name="footer_about" class="form-control js-cms-tinymce" rows="4">{{ old('footer_about', $settings->footer_about) }}</textarea>
                        </div>
                        <div class="appearance-col-12 appearance-field">
                            <label for="footer_note">Footer Note</label>
                            <textarea id="footer_note" name="footer_note" class="form-control js-cms-tinymce" rows="4">{{ old('footer_note', $settings->footer_note) }}</textarea>
                        </div>
                    </div>
                </div>
            </section>

            <section class="appearance-card" id="advanced-system">
            <section class="appearance-card" id="login-system">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Login Page</div>
                    <h2 class="appearance-title">Authentication page styling</h2>
                    <p class="appearance-description">Control the admin login message and its dedicated background treatment without affecting the public site header.</p>
                </div>
                <div class="appearance-card-body">
                    <div class="appearance-grid">
                        <div class="appearance-col-4 appearance-field">
                            <label for="login_eyebrow">Login Eyebrow</label>
                            <input id="login_eyebrow" type="text" name="login_eyebrow" class="form-control" value="{{ old('login_eyebrow', $settings->login_eyebrow) }}" placeholder="Admin Access">
                        </div>
                        <div class="appearance-col-8 appearance-field">
                            <label for="login_title">Login Title</label>
                            <input id="login_title" type="text" name="login_title" class="form-control" value="{{ old('login_title', $settings->login_title) }}" placeholder="Sign in to the admin console">
                            <span class="field-hint">Leave blank to fall back to “Sign in to {site name}”.</span>
                        </div>
                        <div class="appearance-col-12 appearance-field">
                            <label for="login_subtitle">Login Subtitle</label>
                            <input id="login_subtitle" type="text" name="login_subtitle" class="form-control" value="{{ old('login_subtitle', $settings->login_subtitle) }}" placeholder="Use your administrator account to manage the platform.">
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="login_form_title">Form Title</label>
                            <input id="login_form_title" type="text" name="login_form_title" class="form-control" value="{{ old('login_form_title', $settings->login_form_title) }}" placeholder="Welcome back">
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="login_form_subtitle">Form Subtitle</label>
                            <input id="login_form_subtitle" type="text" name="login_form_subtitle" class="form-control" value="{{ old('login_form_subtitle', $settings->login_form_subtitle) }}" placeholder="Enter your credentials to continue.">
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="login_email_label">Email Label</label>
                            <input id="login_email_label" type="text" name="login_email_label" class="form-control" value="{{ old('login_email_label', $settings->login_email_label) }}" placeholder="Email">
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="login_password_label">Password Label</label>
                            <input id="login_password_label" type="text" name="login_password_label" class="form-control" value="{{ old('login_password_label', $settings->login_password_label) }}" placeholder="Password">
                        </div>
                        <div class="appearance-col-4 appearance-field">
                            <label for="login_remember_label">Remember Label</label>
                            <input id="login_remember_label" type="text" name="login_remember_label" class="form-control" value="{{ old('login_remember_label', $settings->login_remember_label) }}" placeholder="Remember me">
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="login_submit_label">Submit Button Label</label>
                            <input id="login_submit_label" type="text" name="login_submit_label" class="form-control" value="{{ old('login_submit_label', $settings->login_submit_label) }}" placeholder="Login">
                        </div>
                        <div class="appearance-col-6 appearance-field">
                            <label for="login_back_label">Back Button Label</label>
                            <input id="login_back_label" type="text" name="login_back_label" class="form-control" value="{{ old('login_back_label', $settings->login_back_label) }}" placeholder="Back to website">
                        </div>
                        <div class="appearance-col-12">
                            <div class="appearance-kicker">Support Copy</div>
                            <h3 class="appearance-title mb-2">Feature highlights</h3>
                            <p class="appearance-description">These are the three supporting lines shown in the left-hand login panel.</p>
                        </div>
                        <div class="appearance-col-12 appearance-field">
                            <label for="login_feature_1">Feature Line 1</label>
                            <input id="login_feature_1" type="text" name="login_feature_1" class="form-control" value="{{ old('login_feature_1', $settings->login_feature_1) }}" placeholder="Centralized access to training operations, participants, projects, and reporting.">
                        </div>
                        <div class="appearance-col-12 appearance-field">
                            <label for="login_feature_2">Feature Line 2</label>
                            <input id="login_feature_2" type="text" name="login_feature_2" class="form-control" value="{{ old('login_feature_2', $settings->login_feature_2) }}" placeholder="Brand-consistent authentication experience managed from the appearance settings.">
                        </div>
                        <div class="appearance-col-12 appearance-field">
                            <label for="login_feature_3">Feature Line 3</label>
                            <input id="login_feature_3" type="text" name="login_feature_3" class="form-control" value="{{ old('login_feature_3', $settings->login_feature_3) }}" placeholder="Secure administrator entry point with direct access back to the public website.">
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="login_background_start_color">Gradient Start</label>
                            <input id="login_background_start_color" type="color" name="login_background_start_color" class="form-control form-control-color js-appearance-color" value="{{ old('login_background_start_color', $settings->login_background_start_color ?? '#082f49') }}">
                            <div class="appearance-color-meta">
                                <span>Base tone</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('login_background_start_color', $settings->login_background_start_color ?? '#082f49') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="login_background_end_color">Gradient End</label>
                            <input id="login_background_end_color" type="color" name="login_background_end_color" class="form-control form-control-color js-appearance-color" value="{{ old('login_background_end_color', $settings->login_background_end_color ?? '#0f766e') }}">
                            <div class="appearance-color-meta">
                                <span>Secondary tone</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('login_background_end_color', $settings->login_background_end_color ?? '#0f766e') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="login_background_accent_color">Background Accent</label>
                            <input id="login_background_accent_color" type="color" name="login_background_accent_color" class="form-control form-control-color js-appearance-color" value="{{ old('login_background_accent_color', $settings->login_background_accent_color ?? '#d97706') }}">
                            <div class="appearance-color-meta">
                                <span>Third stop</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('login_background_accent_color', $settings->login_background_accent_color ?? '#d97706') }}</span></span>
                            </div>
                        </div>
                        <div class="appearance-col-3 appearance-field">
                            <label for="login_card_background_color">Card Background</label>
                            <input id="login_card_background_color" type="color" name="login_card_background_color" class="form-control form-control-color js-appearance-color" value="{{ old('login_card_background_color', $settings->login_card_background_color ?? '#ffffff') }}">
                            <div class="appearance-color-meta">
                                <span>Panel surface</span>
                                <span class="d-inline-flex align-items-center gap-2"><span class="appearance-color-chip js-color-chip"></span><span class="js-color-value">{{ old('login_card_background_color', $settings->login_card_background_color ?? '#ffffff') }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="appearance-card" id="advanced-system">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Advanced</div>
                    <h2 class="appearance-title">Visibility and front-end code</h2>
                    <p class="appearance-description">Reserve this section for controlled overrides and final visibility decisions.</p>
                </div>
                <div class="appearance-card-body">
                    <div class="appearance-toggle-row mb-4">
                        <div class="appearance-toggle-card">
                            <div class="form-check form-switch">
                                <label class="form-check-label" for="show_admin_link">
                                    <span class="appearance-toggle-title">Show Admin Link</span>
                                    <span class="appearance-toggle-copy">Display an administrative access link on the public website.</span>
                                </label>
                                <input class="form-check-input" type="checkbox" name="show_admin_link" value="1" id="show_admin_link" @checked(old('show_admin_link', $settings->show_admin_link))>
                            </div>
                        </div>
                        <div class="appearance-toggle-card">
                            <div class="form-check form-switch">
                                <label class="form-check-label" for="show_login_link">
                                    <span class="appearance-toggle-title">Show Login Link</span>
                                    <span class="appearance-toggle-copy">Expose a standard login link in the public navigation experience.</span>
                                </label>
                                <input class="form-check-input" type="checkbox" name="show_login_link" value="1" id="show_login_link" @checked(old('show_login_link', $settings->show_login_link))>
                            </div>
                        </div>
                    </div>

                    <div class="appearance-grid">
                        <div class="appearance-col-12 appearance-field" id="custom-css-section">
                            <label for="custom_css">
                                <span class="appearance-code-label">Custom CSS</span>
                                <span class="text-secondary small">Loaded on public pages</span>
                            </label>
                            <textarea id="custom_css" name="custom_css" class="form-control font-monospace" rows="10" placeholder="Paste CSS only, without a <style> tag.">{{ old('custom_css', $settings->custom_css) }}</textarea>
                            <span class="field-hint">Use this for targeted refinements, not broad theme replacement.</span>
                        </div>
                        <div class="appearance-col-12 appearance-field" id="custom-js-section">
                            <label for="custom_js">
                                <span class="appearance-code-label">Custom JavaScript</span>
                                <span class="text-secondary small">Loaded on public pages</span>
                            </label>
                            <textarea id="custom_js" name="custom_js" class="form-control font-monospace" rows="10" placeholder="Paste JavaScript only, without a <script> tag.">{{ old('custom_js', $settings->custom_js) }}</textarea>
                            <span class="field-hint">Keep this lean and purposeful. Track non-trivial snippets in source control.</span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="appearance-sticky-actions">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="appearance-kicker">Ready To Publish</div>
                        <div class="text-secondary small mt-1">Save updates when the live preview reflects the direction you want.</div>
                    </div>
                    <div class="appearance-actions">
                        <button class="btn btn-dark px-4" type="submit">Save Appearance</button>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </div>

        <aside class="appearance-sidebar">
            <section class="appearance-card">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Snapshot</div>
                    <h2 class="appearance-title">Theme health</h2>
                    <p class="appearance-description">A compact summary of the current visual system.</p>
                </div>
                <div class="appearance-card-body">
                    <div class="appearance-metrics">
                        <div class="appearance-metric">
                            <div class="appearance-metric-label">Accent</div>
                            <div class="appearance-metric-value" id="metric-accent">{{ old('body_accent_color', $settings->body_accent_color ?? '#0f766e') }}</div>
                        </div>
                        <div class="appearance-metric">
                            <div class="appearance-metric-label">Header</div>
                            <div class="appearance-metric-value" id="metric-header">{{ old('header_background_color', $settings->header_background_color ?? '#ffffff') }}</div>
                        </div>
                        <div class="appearance-metric">
                            <div class="appearance-metric-label">Footer</div>
                            <div class="appearance-metric-value" id="metric-footer">{{ old('footer_background_color', $settings->footer_background_color ?? '#0f172a') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="appearance-card">
                <div class="appearance-card-header">
                    <div class="appearance-kicker">Guidance</div>
                    <h2 class="appearance-title">What usually looks more professional</h2>
                </div>
                <div class="appearance-card-body">
                    <div class="text-secondary small d-grid gap-3">
                        <div>
                            <strong class="d-block text-dark mb-1">Keep the palette disciplined</strong>
                            Limit the experience to one primary accent, one neutral base, and one dark anchor.
                        </div>
                        <div>
                            <strong class="d-block text-dark mb-1">Use concise footer copy</strong>
                            Contact details and one sharp brand statement usually outperform long generic notes.
                        </div>
                        <div>
                            <strong class="d-block text-dark mb-1">Avoid oversized logos</strong>
                            Moderate logo height improves balance and keeps navigation feeling intentional.
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</form>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    (() => {
        const activeSection = @json($activeSection);
        if (!activeSection) {
            return;
        }

        const targetId = activeSection === 'custom-js' ? 'custom-js-section' : 'custom-css-section';
        const target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        target.classList.add('border', 'border-primary', 'rounded-4', 'p-3');
    })();

    const cmsEditorPlugins = [
        'advlist', 'anchor', 'autolink', 'autoresize', 'autosave', 'charmap', 'code', 'codesample',
        'directionality', 'emoticons', 'fullscreen', 'help', 'hr', 'image', 'insertdatetime', 'link',
        'lists', 'media', 'nonbreaking', 'pagebreak', 'preview', 'quickbars', 'save', 'searchreplace',
        'table', 'visualblocks', 'visualchars', 'wordcount'
    ];

    if (window.tinymce) {
        document.querySelectorAll('textarea.js-cms-tinymce').forEach((textarea) => {
            if (!textarea.id) {
                textarea.id = `appearance-editor-${Math.random().toString(36).slice(2)}`;
            }

            if (tinymce.get(textarea.id)) {
                return;
            }

            tinymce.init({
                target: textarea,
                height: 280,
                menubar: true,
                toolbar_mode: 'sliding',
                branding: false,
                promotion: false,
                convert_urls: false,
                relative_urls: false,
                remove_script_host: false,
                plugins: cmsEditorPlugins,
                toolbar: 'undo redo restoredraft | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | outdent indent | bullist numlist | link image media table charmap emoticons hr pagebreak nonbreaking insertdatetime | ltr rtl | removeformat | searchreplace visualblocks visualchars preview fullscreen code',
            });
        });
    }

    (() => {
        const preview = document.getElementById('appearance-live-preview');
        if (!preview) {
            return;
        }

        const bindText = (selector, targetId, fallback = '') => {
            const input = document.querySelector(selector);
            const target = document.getElementById(targetId);
            if (!input || !target) {
                return;
            }

            const sync = () => {
                const value = (input.value || '').trim();
                target.textContent = value || fallback;
            };

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            sync();
        };

        bindText('input[name="site_name"]', 'preview-site-name', 'HIL Website');
        bindText('input[name="site_tagline"]', 'preview-site-tagline', 'High-impact learning and implementation platform.');
        bindText('input[name="header_cta_label"]', 'preview-cta-button', 'Get Started');
        bindText('input[name="footer_title"]', 'preview-footer-title', 'HIL Website');
        bindText('input[name="footer_copyright"]', 'preview-footer-copy', 'All rights reserved.');

        const previewStyleMap = {
            'header_background_color': '--preview-header-bg',
            'header_text_color': '--preview-header-text',
            'header_link_color': '--preview-link',
            'body_background_color': '--preview-body-bg',
            'body_text_color': '--preview-body-text',
            'body_panel_color': '--preview-panel-bg',
            'body_accent_color': '--preview-accent',
            'footer_background_color': '--preview-footer-bg',
            'footer_text_color': '--preview-footer-text',
        };

        document.querySelectorAll('.js-appearance-color').forEach((input) => {
            const updateMeta = () => {
                const wrap = input.closest('.appearance-field');
                const chip = wrap?.querySelector('.js-color-chip');
                const text = wrap?.querySelector('.js-color-value');
                if (chip) {
                    chip.style.background = input.value;
                }
                if (text) {
                    text.textContent = input.value;
                }
            };

            const updatePreview = () => {
                const variable = previewStyleMap[input.name];
                if (variable) {
                    preview.style.setProperty(variable, input.value);
                }

                if (input.name === 'body_accent_color') {
                    const metric = document.getElementById('metric-accent');
                    if (metric) {
                        metric.textContent = input.value;
                    }
                }

                if (input.name === 'header_background_color') {
                    const metric = document.getElementById('metric-header');
                    if (metric) {
                        metric.textContent = input.value;
                    }
                }

                if (input.name === 'footer_background_color') {
                    const metric = document.getElementById('metric-footer');
                    if (metric) {
                        metric.textContent = input.value;
                    }
                }
            };

            const sync = () => {
                updateMeta();
                updatePreview();
            };

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            sync();
        });

        const radiusLargeInput = document.querySelector('input[name="radius_lg"]');
        const radiusPillInput = document.querySelector('input[name="radius_pill"]');

        const syncRadius = () => {
            if (radiusLargeInput) {
                preview.style.setProperty('--preview-panel-radius', `${parseInt(radiusLargeInput.value || '18', 10)}px`);
            }
            if (radiusPillInput) {
                preview.style.setProperty('--preview-pill-radius', `${parseInt(radiusPillInput.value || '999', 10)}px`);
            }
        };

        radiusLargeInput?.addEventListener('input', syncRadius);
        radiusLargeInput?.addEventListener('change', syncRadius);
        radiusPillInput?.addEventListener('input', syncRadius);
        radiusPillInput?.addEventListener('change', syncRadius);
        syncRadius();
    })();
</script>
@endsection
