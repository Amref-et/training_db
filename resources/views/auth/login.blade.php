<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php($appearance = \App\Models\WebsiteSetting::current())
    @php($loginRadius = max(0, (int) ($appearance->radius_xl ?? 24)))
    @php($headerLogoHeight = max(24, (int) ($appearance->header_logo_height ?? 56)))
    @php($siteName = $appearance->site_name ?: config('app.name', 'HIL CMS'))
    @php($loginEyebrow = $appearance->login_eyebrow ?: 'Admin Access')
    @php($loginTitle = $appearance->login_title ?: 'Sign in to '.$siteName)
    @php($loginSubtitle = $appearance->login_subtitle ?: 'Use your administrator account to manage training, participants, projects, and reporting.')
    @php($loginFormTitle = $appearance->login_form_title ?: 'Welcome back')
    @php($loginFormSubtitle = $appearance->login_form_subtitle ?: 'Enter your credentials to continue to the administrative workspace.')
    @php($loginEmailLabel = $appearance->login_email_label ?: 'Email')
    @php($loginPasswordLabel = $appearance->login_password_label ?: 'Password')
    @php($loginRememberLabel = $appearance->login_remember_label ?: 'Remember me')
    @php($loginSubmitLabel = $appearance->login_submit_label ?: 'Login')
    @php($loginBackLabel = $appearance->login_back_label ?: 'Back to website')
    @php($loginFeature1 = $appearance->login_feature_1 ?: 'Centralized access to training operations, participants, projects, and reporting.')
    @php($loginFeature2 = $appearance->login_feature_2 ?: 'Brand-consistent authentication experience managed from the appearance settings.')
    @php($loginFeature3 = $appearance->login_feature_3 ?: 'Secure administrator entry point with direct access back to the public website.')
    @php($loginStart = $appearance->login_background_start_color ?: '#082f49')
    @php($loginEnd = $appearance->login_background_end_color ?: '#0f766e')
    @php($loginAccent = $appearance->login_background_accent_color ?: '#d97706')
    @php($loginCard = $appearance->login_card_background_color ?: '#ffffff')
    @php($resolveLogo = function (?string $value): ?string {
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
    })
    @php($headerLogoUrl = $resolveLogo($appearance->header_logo_url))
    @php($faviconUrl = $resolveLogo($appearance->favicon_url))
    <title>Login | {{ $siteName }}</title>
    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif
    <link href="{{ route('vendor-assets.show', 'bootstrap-5.3.3.min.css') }}" rel="stylesheet">
    <style>
        :root {
            --login-start: {{ $loginStart }};
            --login-end: {{ $loginEnd }};
            --login-accent: {{ $loginAccent }};
            --login-card: {{ $loginCard }};
            --login-radius: {{ $loginRadius }}px;
            --login-ink: #0f172a;
            --login-muted: #64748b;
        }
        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--login-accent) 34%, transparent) 0%, transparent 34%),
                radial-gradient(circle at bottom right, color-mix(in srgb, var(--login-end) 24%, transparent) 0%, transparent 36%),
                linear-gradient(135deg, var(--login-start) 0%, var(--login-end) 52%, var(--login-accent) 100%);
            display: grid;
            place-items: center;
            padding: 1.25rem;
            color: var(--login-ink);
        }
        .login-shell {
            width: min(1100px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(340px, 460px);
            gap: 1.25rem;
            align-items: stretch;
        }
        .login-brand-panel {
            position: relative;
            overflow: hidden;
            border-radius: calc(var(--login-radius) + 8px);
            padding: clamp(1.75rem, 4vw, 3rem);
            background:
                linear-gradient(145deg, rgba(255,255,255,.12) 0%, rgba(255,255,255,.04) 100%);
            border: 1px solid rgba(255,255,255,.18);
            box-shadow: 0 24px 70px rgba(2, 6, 23, .22);
            color: #ecfeff;
            backdrop-filter: blur(12px);
        }
        .login-brand-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(120deg, rgba(255,255,255,.08), transparent 36%),
                radial-gradient(circle at top right, rgba(255,255,255,.08), transparent 32%);
            pointer-events: none;
        }
        .login-brand-panel > * {
            position: relative;
            z-index: 1;
        }
        .login-brand-kicker {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: rgba(236, 254, 255, .72);
        }
        .login-brand-kicker::before {
            content: "";
            width: .55rem;
            height: .55rem;
            border-radius: 999px;
            background: rgba(255,255,255,.92);
            box-shadow: 0 0 0 5px rgba(255,255,255,.08);
        }
        .login-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 1rem 0 1.35rem;
            padding: .6rem .9rem;
            border-radius: 18px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.16);
        }
        .login-logo img {
            max-width: min(280px, 100%);
            height: {{ $headerLogoHeight }}px;
            object-fit: contain;
            display: block;
        }
        .login-brand-title {
            font-size: clamp(2rem, 4vw, 3.15rem);
            line-height: 1.08;
            font-weight: 700;
            margin: 0;
            color: #ffffff;
            max-width: 12ch;
        }
        .login-brand-copy {
            margin-top: 1rem;
            max-width: 54ch;
            color: rgba(236, 254, 255, .78);
            font-size: 1rem;
            line-height: 1.7;
        }
        .login-brand-points {
            margin: 1.5rem 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: .75rem;
            max-width: 520px;
        }
        .login-brand-points li {
            display: flex;
            align-items: start;
            gap: .75rem;
            padding: .9rem 1rem;
            border-radius: 18px;
            background: rgba(255,255,255,.09);
            border: 1px solid rgba(255,255,255,.12);
            color: rgba(236, 254, 255, .82);
        }
        .login-brand-points li::before {
            content: "";
            width: .7rem;
            height: .7rem;
            margin-top: .35rem;
            border-radius: 999px;
            flex: 0 0 auto;
            background: rgba(255,255,255,.88);
        }
        .login-card {
            width: 100%;
            border-radius: calc(var(--login-radius) + 4px);
            border: 1px solid rgba(15, 23, 42, .08);
            background: color-mix(in srgb, var(--login-card) 94%, #ffffff);
            box-shadow: 0 28px 80px rgba(2, 6, 23, .24);
            overflow: hidden;
        }
        .login-card-body {
            padding: clamp(1.5rem, 3vw, 2.4rem);
        }
        .login-eyebrow {
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #64748b;
        }
        .login-heading {
            margin: .55rem 0 .55rem;
            font-size: 1.9rem;
            line-height: 1.15;
            color: #0f172a;
        }
        .login-subtitle {
            margin: 0 0 1.5rem;
            color: #64748b;
            line-height: 1.65;
        }
        .login-form .form-control {
            min-height: 3rem;
            border-radius: 16px;
            border-color: rgba(148, 163, 184, .35);
            box-shadow: none;
        }
        .login-form .form-control:focus {
            border-color: rgba(15, 118, 110, .55);
            box-shadow: 0 0 0 .2rem rgba(15, 118, 110, .12);
        }
        .login-form .btn {
            min-height: 3rem;
            border-radius: 16px;
            font-weight: 600;
        }
        .login-form .btn-dark {
            background: #0f172a;
            border-color: #0f172a;
        }
        .login-form .btn-dark:hover {
            background: #020617;
            border-color: #020617;
        }
        @media (max-width: 991.98px) {
            .login-shell {
                grid-template-columns: 1fr;
            }
            .login-brand-panel {
                order: 2;
            }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <section class="login-brand-panel">
            <div class="login-brand-kicker">{{ $siteName }}</div>
            @if($headerLogoUrl)
                <div class="login-logo">
                    <img src="{{ $headerLogoUrl }}" alt="{{ $siteName }}">
                </div>
            @endif
            <h1 class="login-brand-title">{{ $loginTitle }}</h1>
            <p class="login-brand-copy">{{ $loginSubtitle }}</p>
            <ul class="login-brand-points">
                <li>{{ $loginFeature1 }}</li>
                <li>{{ $loginFeature2 }}</li>
                <li>{{ $loginFeature3 }}</li>
            </ul>
        </section>

        <div class="card login-card">
            <div class="login-card-body">
                <div class="login-eyebrow">{{ $loginEyebrow }}</div>
                <h2 class="login-heading">{{ $loginFormTitle }}</h2>
                <p class="login-subtitle">{{ $loginFormSubtitle }}</p>

                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="login-form d-grid gap-3">
                    @csrf
                    <div>
                        <label class="form-label">{{ $loginEmailLabel }}</label>
                        <input class="form-control" type="email" name="email" value="{{ old('email') }}" required autofocus>
                    </div>
                    <div>
                        <label class="form-label">{{ $loginPasswordLabel }}</label>
                        <input class="form-control" type="password" name="password" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">{{ $loginRememberLabel }}</label>
                    </div>
                    <button class="btn btn-dark" type="submit">{{ $loginSubmitLabel }}</button>
                    <a class="btn btn-outline-secondary" href="{{ route('home') }}">{{ $loginBackLabel }}</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
