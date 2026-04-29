<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin console for training, participants, projects, and reporting.">
    <title>{{ trim($__env->yieldContent('title').' | '.config('app.name', 'HIL CMS'), ' |') }}</title>
    @php($appearance = \App\Models\WebsiteSetting::current())
    @php($radiusSm = max(0, (int) ($appearance->radius_sm ?? 10)))
    @php($radiusMd = max(0, (int) ($appearance->radius_md ?? 14)))
    @php($radiusLg = max(0, (int) ($appearance->radius_lg ?? 18)))
    @php($radiusXl = max(0, (int) ($appearance->radius_xl ?? 24)))
    @php($radiusPill = max(0, (int) ($appearance->radius_pill ?? 999)))
    @php($faviconUrl = !empty($appearance->favicon_url) ? ((str_starts_with($appearance->favicon_url, 'http://') || str_starts_with($appearance->favicon_url, 'https://')) ? $appearance->favicon_url : (str_starts_with($appearance->favicon_url, '/') ? url(ltrim($appearance->favicon_url, '/')) : \Illuminate\Support\Facades\Storage::disk('public')->url($appearance->favicon_url))) : null)
    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --hil-ink: #1f2937;
            --hil-slate: #64748b;
            --hil-panel: #ffffff;
            --radius-sm: {{ $radiusSm }}px;
            --radius-md: {{ $radiusMd }}px;
            --radius-lg: {{ $radiusLg }}px;
            --radius-xl: {{ $radiusXl }}px;
            --radius-pill: {{ $radiusPill }}px;
        }
        body { background: linear-gradient(180deg, #eef4f7 0%, #f8fafc 100%); color: var(--hil-ink); }
        .sidebar { background: linear-gradient(180deg, #092635 0%, #1b4965 100%); min-height: 100vh; }
        .sidebar a { color: rgba(255,255,255,.78); text-decoration: none; display: block; padding: .7rem .9rem; border-radius: var(--radius-sm); }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.12); color: #fff; }
        .sidebar .sidebar-section-toggle { color: rgba(255,255,255,.66); font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; background: transparent; border: none; padding: .65rem .2rem .4rem; margin-top: .45rem; border-radius: var(--radius-sm); }
        .sidebar .sidebar-section-toggle:hover, .sidebar .sidebar-section-toggle:focus { color: rgba(255,255,255,.92); background: rgba(255,255,255,.06); box-shadow: none; }
        .sidebar .sidebar-section-toggle .caret { transition: transform .2s ease; font-size: .75rem; color: rgba(255,255,255,.65); }
        .sidebar .sidebar-section-toggle[aria-expanded="true"] .caret { transform: rotate(180deg); color: #fff; }
        .sidebar .sidebar-section-content { padding-bottom: .2rem; }
        .sidebar .sidebar-group-toggle { color: rgba(255,255,255,.92); font-weight: 600; background: transparent; border: none; padding: .5rem .25rem; margin-top: .35rem; border-radius: var(--radius-sm); }
        .sidebar .sidebar-group-toggle:hover, .sidebar .sidebar-group-toggle:focus { color: #fff; background: rgba(255,255,255,.08); box-shadow: none; }
        .sidebar .sidebar-group-toggle .caret { transition: transform .2s ease; font-size: .85rem; color: rgba(255,255,255,.75); }
        .sidebar .sidebar-group-toggle[aria-expanded="true"] .caret { transform: rotate(180deg); color: #fff; }
        .sidebar .menu-icon { width: 1rem; text-align: center; margin-right: .35rem; }
        .sidebar .submenu { padding-left: .25rem; }
        .sidebar .submenu a { padding: .5rem .8rem; font-size: .95rem; }
        .panel { background: var(--hil-panel); border: 1px solid rgba(15, 23, 42, .06); border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(15, 23, 42, .06); }
        .metric-card { border-radius: var(--radius-lg); background: #fff; border: 1px solid rgba(15, 23, 42, .06); }
        .metric-card .metric-value { font-size: 2rem; font-weight: 700; }
        .section-title { font-size: .85rem; letter-spacing: .12em; text-transform: uppercase; color: var(--hil-slate); }
        .top-user-btn { border-radius: var(--radius-pill); }
        .admin-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .admin-header {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: var(--radius-lg);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .05);
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .admin-content { flex: 1 1 auto; }
        .admin-actions-bar { margin-bottom: 1rem; }
        .admin-footer {
            margin-top: 1.25rem;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: var(--radius-md);
            padding: .75rem 1rem;
            color: var(--hil-slate);
            font-size: .85rem;
        }
    </style>
    @yield('head')
</head>
<body>
    @php($user = auth()->user())
    @php($dynamicSidebarItems = collect())
    @if(\Illuminate\Support\Facades\Schema::hasTable('admin_sidebar_menu_items'))
        @php($dynamicSidebarItems = \App\Models\AdminSidebarMenuItem::navigationTreeFor($user))
    @endif
    @php($useDynamicSidebar = $dynamicSidebarItems->isNotEmpty())
    @php($dynamicSidebarSections = $dynamicSidebarItems->groupBy(fn ($item) => trim((string) ($item->section_title ?: 'General'))))
    <div class="container-fluid"><div class="row">
        <aside class="col-lg-2 px-3 py-4 sidebar">
            <div class="text-white mb-4"><div class="section-title text-white-50">Admin Console</div><h4 class="mb-1">{{ config('app.name', 'HIL CMS') }}</h4><div class="small text-white-50">{{ $user?->primaryRole()?->name ?? 'User' }}</div></div>
            @if($useDynamicSidebar)
                <nav class="mb-4">
                    @php($isSectionedSidebar = $dynamicSidebarSections->count() > 1)
                    @foreach($dynamicSidebarSections as $sectionTitle => $sectionItems)
                        @php($sectionId = 'sidebar-section-'.\Illuminate\Support\Str::slug((string) $sectionTitle, '-').'-'.$loop->index)
                        @php($sectionHasActiveItem = $sectionItems->contains(function ($sectionItem) {
                            if ($sectionItem->isActiveForRequest(request())) {
                                return true;
                            }

                            $sectionChildren = $sectionItem->children ?? collect();

                            return $sectionChildren->contains(fn ($child) => $child->isActiveForRequest(request()));
                        }))
                        @if($isSectionedSidebar)
                            <button class="sidebar-section-toggle d-flex justify-content-between align-items-center w-100" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $sectionId }}" aria-expanded="{{ $sectionHasActiveItem ? 'true' : 'false' }}" aria-controls="{{ $sectionId }}">
                                <span>{{ $sectionTitle }}</span>
                                <span class="caret">&#9662;</span>
                            </button>
                        @endif
                        <div id="{{ $sectionId }}" class="{{ $isSectionedSidebar ? 'collapse sidebar-section-content' : 'sidebar-section-content' }} {{ (! $isSectionedSidebar || $sectionHasActiveItem) ? 'show' : '' }}">
                            @foreach($sectionItems as $item)
                                @php($children = $item->children ?? collect())
                                @php($itemUrl = $item->resolvedUrl())
                                @php($itemActive = $item->isActiveForRequest(request()))
                                @php($itemIconClass = $item->iconClass())
                                @if($children->isNotEmpty())
                                    @php($groupId = 'sidebar-group-'.$item->id)
                                    @php($hasActiveChild = $children->contains(fn ($child) => $child->isActiveForRequest(request())))
                                    <button class="sidebar-group-toggle d-flex justify-content-between align-items-center w-100" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $groupId }}" aria-expanded="{{ $hasActiveChild ? 'true' : 'false' }}" aria-controls="{{ $groupId }}">
                                        <span class="d-inline-flex align-items-center">
                                            @if($itemIconClass)
                                                <i class="bi {{ $itemIconClass }} menu-icon" aria-hidden="true"></i>
                                            @elseif($item->icon)
                                                <span class="menu-icon">{{ $item->icon }}</span>
                                            @endif
                                            <span>{{ $item->title }}</span>
                                        </span>
                                        <span class="caret">&#9662;</span>
                                    </button>
                                    <div id="{{ $groupId }}" class="collapse submenu mb-2 {{ $hasActiveChild ? 'show' : '' }}">
                                        @foreach($children as $child)
                                            @php($childUrl = $child->resolvedUrl())
                                            @php($childActive = $child->isActiveForRequest(request()))
                                            @php($childIconClass = $child->iconClass())
                                            <a class="{{ $childActive ? 'active' : '' }}" href="{{ $childUrl }}" target="{{ $child->target ?: '_self' }}">
                                                @if($childIconClass)
                                                    <i class="bi {{ $childIconClass }} menu-icon" aria-hidden="true"></i>
                                                @elseif($child->icon)
                                                    <span class="menu-icon">{{ $child->icon }}</span>
                                                @endif
                                                {{ $child->title }}
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <a class="{{ $itemActive ? 'active' : '' }}" href="{{ $itemUrl }}" target="{{ $item->target ?: '_self' }}">
                                        @if($itemIconClass)
                                            <i class="bi {{ $itemIconClass }} menu-icon" aria-hidden="true"></i>
                                        @elseif($item->icon)
                                            <span class="menu-icon">{{ $item->icon }}</span>
                                        @endif
                                        {{ $item->title }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            @else
                <nav class="d-grid gap-1 mb-4">
                    @if($user?->hasPermission('dashboard.view'))<a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>@endif
                    @if($user?->hasPermission('pages.view'))<a class="{{ request()->routeIs('admin.pages.*') ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">CMS Pages</a>@endif
                    @if($user?->hasPermission('menus.view'))<a class="{{ request()->routeIs('admin.menus.*') ? 'active' : '' }}" href="{{ route('admin.menus.index') }}">Menu Management</a>@endif
                    @if($user?->hasPermission('menus.view'))<a class="{{ request()->routeIs('admin.sidebar-menus.*') ? 'active' : '' }}" href="{{ route('admin.sidebar-menus.index') }}">Sidebar Menus</a>@endif
                    @if($user?->hasPermission('appearance.view'))<a class="{{ request()->routeIs('admin.appearance.*') ? 'active' : '' }}" href="{{ route('admin.appearance.edit') }}">Appearance</a>@endif
                    @if($user?->hasPermission('appearance.view'))<a class="{{ request()->routeIs('admin.settings.env.*') ? 'active' : '' }}" href="{{ route('admin.settings.env.edit') }}">Env Settings</a>@endif
                    @if($user?->hasPermission('api_management.view'))<a class="{{ request()->routeIs('admin.api-management.*') ? 'active' : '' }}" href="{{ route('admin.api-management.index') }}">API Management</a>@endif
                    @if($user?->hasPermission('users.view'))<a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>@endif
                    @if($user?->hasPermission('users.view'))<a class="{{ request()->routeIs('admin.user-activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.user-activity-logs.index') }}">User Activity Log</a>@endif
                    @if($user?->hasPermission('roles.view'))<a class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}">Roles</a>@endif
                    @if($user?->hasPermission('crud_builder.view'))<a class="{{ request()->routeIs('admin.crud-builders.*') ? 'active' : '' }}" href="{{ route('admin.crud-builders.index') }}">CRUD Builder</a>@endif
                    @foreach(\App\Support\ResourceRegistry::all() as $resourceConfig)
                        @if($user?->hasPermission($resourceConfig['permission'].'.view'))<a class="{{ request()->routeIs('admin.'.$resourceConfig['path'].'.*') ? 'active' : '' }}" href="{{ route('admin.'.$resourceConfig['path'].'.index') }}">{{ $resourceConfig['label'] }}</a>@endif
                    @endforeach
                    @if($user?->hasPermission('training_events.view'))
                        <a class="{{ request()->routeIs('admin.training-events-calendar.*') ? 'active' : '' }}" href="{{ route('admin.training-events-calendar.index') }}">Event Calendar View</a>
                        <a class="{{ request()->routeIs('admin.training-events.grouped') ? 'active' : '' }}" href="{{ route('admin.training-events.grouped') }}">Grouped Training Events</a>
                        <a class="{{ request()->routeIs('admin.training-workflow.*') ? 'active' : '' }}" href="{{ route('admin.training-workflow.index') }}">Training Workflow</a>
                    @endif
                </nav>
            @endif
            <div class="mt-auto text-white-50 small"></div>
        </aside>
        <main class="col-lg-10 px-4 py-4 admin-shell">
            <header class="admin-header">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="section-title">{{ $__env->yieldContent('eyebrow', 'Management') }}</div>
                        <h1 class="h3 mb-1">@yield('title')</h1>
                        <div class="text-secondary">@yield('subtitle')</div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary top-user-btn dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span class="fw-semibold">{{ $user?->name ?? 'User' }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <div class="dropdown-item-text">
                                    <div class="fw-semibold">{{ $user?->name ?? 'User' }}</div>
                                    @if(!empty($user?->email))
                                        <div class="small text-secondary">{{ $user->email }}</div>
                                    @endif
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
            <section class="admin-content">
                @if(trim($__env->yieldContent('actions')) !== '')
                    <div class="admin-actions-bar d-flex justify-content-end">
                        <div>@yield('actions')</div>
                    </div>
                @endif
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </section>
            <footer class="admin-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>&copy; {{ now()->year }} {{ config('app.name', 'HIL CMS') }}</span>
                <span>Admin Console</span>
            </footer>
        </main>
    </div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @if(trim($__env->yieldContent('uses_charts')) === '1')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    @endif
    @yield('scripts')
</body>
</html>
