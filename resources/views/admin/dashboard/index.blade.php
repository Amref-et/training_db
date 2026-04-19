@extends('layouts.admin')

@section('eyebrow', 'Analytics')
@section('title', 'Dashboard')
@section('subtitle', '')
@section('uses_charts', '1')

@section('head')
<style>
    .dashboard-toolbar { display: flex; gap: .5rem; flex-wrap: wrap; }
    .dashboard-tabs { border-bottom: 1px solid rgba(15, 23, 42, .08); padding-bottom: .75rem; margin-bottom: 1rem; }
    .dashboard-tabs .nav-link { border-radius: var(--radius-pill); }
    .dashboard-grid { --widget-gap: 1rem; display: flex; flex-wrap: wrap; gap: var(--widget-gap); align-items: stretch; }
    .dashboard-widget-shell { flex: 0 0 auto; max-width: 100%; min-width: min(100%, 260px); cursor: default; }
    .dashboard-widget-shell.dragging { opacity: .45; }
    .dashboard-widget-shell .widget-chart-wrap { position: relative; width: 100%; }
    .dashboard-widget-shell .widget-table-wrap { overflow: auto; border: 1px solid rgba(15, 23, 42, .08); border-radius: var(--radius-sm); }
    .dashboard-widget-shell .metric-card { overflow: hidden; }
    .dashboard-widget-shell .metric-card .metric-value { font-size: clamp(1.6rem, 2.6vw, 2.4rem); line-height: 1.1; word-break: break-word; }
    .widget-settings-card { background: rgba(15, 23, 42, .02); border: 1px solid rgba(15, 23, 42, .08); border-radius: var(--radius-sm); }
    .widget-drag-handle { cursor: move; }
    .widget-meta { font-size: .78rem; color: #64748b; }
    .widget-empty { min-height: 140px; display: flex; align-items: center; justify-content: center; color: #64748b; border: 1px dashed rgba(15, 23, 42, .18); border-radius: var(--radius-sm); }
</style>
@endsection

@section('actions')
@php($actionFilterValues = collect($filters ?? [])->filter(fn ($value) => $value !== null && $value !== '')->all())
@php($actionBaseParams = $activeTab ? array_merge(['tab_id' => $activeTab->id], $actionFilterValues) : $actionFilterValues)
<div class="dashboard-toolbar">
    @if($isEditing)
        <a class="btn btn-outline-secondary" href="{{ route('admin.dashboard', $actionBaseParams) }}">Turn Editing Off</a>
        <button class="btn btn-dark" type="button" data-bs-toggle="modal" data-bs-target="#createTabModal">Add Tab</button>
        <button class="btn btn-outline-dark" type="button" data-bs-toggle="modal" data-bs-target="#importLayoutModal">Import Layout</button>
        <a class="btn btn-outline-dark" href="{{ route('admin.dashboard.layout.export') }}">Export Layout</a>
        @if($activeTab)
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createWidgetModal">Add Widget</button>
        @endif
    @else
        <a class="btn btn-dark" href="{{ route('admin.dashboard', array_merge($actionBaseParams, ['edit' => '1'])) }}">Turn Editing On</a>
    @endif
</div>
@endsection

@section('content')
@php($activeFilterValues = collect($filters ?? [])->filter(fn ($value) => $value !== null && $value !== '')->all())
@php($widgetDataQueryString = http_build_query($activeFilterValues))
@php($viewParams = $activeTab ? array_merge(['tab_id' => $activeTab->id], $activeFilterValues) : $activeFilterValues)
<div class="panel p-4 mb-4">
    <div class="dashboard-tabs">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <ul class="nav nav-pills flex-wrap gap-2">
                @forelse($tabs as $tab)
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab && $activeTab->id === $tab->id ? 'active' : '' }}" href="{{ route('admin.dashboard', $isEditing ? array_merge(['tab_id' => $tab->id], $activeFilterValues, ['edit' => '1']) : array_merge(['tab_id' => $tab->id], $activeFilterValues)) }}">
                            {{ $tab->name }}
                            @if($tab->is_default)
                                <span class="badge text-bg-light ms-1">Default</span>
                            @endif
                        </a>
                    </li>
                @empty
                    <li class="text-secondary">No tabs found.</li>
                @endforelse
            </ul>
            @if($activeTab && $isEditing)
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editTabModal">Edit Tab</button>
                    <form method="POST" action="{{ route('admin.dashboard.tabs.destroy', $activeTab) }}" onsubmit="return confirm('Delete this dashboard tab and all its widgets?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="edit" value="1">
                        @foreach($activeFilterValues as $filterKey => $filterValue)
                            <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                        @endforeach
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete Tab</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
    @if(! empty($filterDefinitions))
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardFilterCollapse" aria-expanded="false" aria-controls="dashboardFilterCollapse">
                Filters
            </button>
        </div>
        <div class="collapse" id="dashboardFilterCollapse">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="row g-3 align-items-end mb-4">
                @if($activeTab)
                    <input type="hidden" name="tab_id" value="{{ $activeTab->id }}">
                @endif
                @if($isEditing)
                    <input type="hidden" name="edit" value="1">
                @endif
                @foreach($filterDefinitions as $definition)
                    <div class="col-md-4 col-xl-3">
                        <label class="form-label">{{ $definition['label'] }}</label>
                        <select name="{{ $definition['key'] }}" class="form-select">
                            <option value="">{{ $definition['all_label'] }}</option>
                            @foreach($definition['options'] as $option)
                                <option value="{{ $option['value'] }}" @selected(($filters[$definition['key']] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-4 col-xl-3 d-grid gap-2">
                    <button class="btn btn-dark" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.dashboard', $isEditing ? array_merge($activeTab ? ['tab_id' => $activeTab->id] : [], ['edit' => '1']) : ($activeTab ? ['tab_id' => $activeTab->id] : [])) }}">Reset Filters</a>
                </div>
            </form>
        </div>
    @endif

    @if(! $activeTab)
        <div class="widget-empty">No dashboard tab is available yet.</div>
    @else
        <div class="text-secondary small mb-3">{{ $isEditing ? 'Layout saves automatically when widgets are reordered or edited.' : '' }}</div>

        <div
            id="dashboardGrid"
            class="dashboard-grid"
            data-tab-id="{{ $activeTab->id }}"
            data-reorder-url="{{ route('admin.dashboard.widgets.reorder', $activeTab) }}"
            data-csrf="{{ csrf_token() }}"
        >
            @forelse($activeWidgets as $widget)
                @php($payload = $widgetPayloads[$widget->id] ?? ['type' => 'error', 'message' => 'Widget data unavailable.'])
                <article
                    class="panel p-3 dashboard-widget-shell"
                    style="{{ $widgetWidthStyles[$widget->id] ?? 'width:100%;' }}"
                    data-widget-id="{{ $widget->id }}"
                    data-widget-chart-type="{{ $widget->chart_type }}"
                    data-widget-color="{{ $widget->color_scheme }}"
                    data-widget-refresh="{{ (int) ($widget->refresh_interval_seconds ?? 0) }}"
                    data-widget-data-url="{{ route('admin.dashboard.widgets.data', $widget) }}{{ $widgetDataQueryString !== '' ? '?'.$widgetDataQueryString : '' }}"
                    draggable="{{ $isEditing ? 'true' : 'false' }}"
                >
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <h2 class="h6 mb-1">{{ $widget->title }}</h2>
                        </div>
                        @if($isEditing)
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#widget-settings-{{ $widget->id }}" aria-expanded="false" aria-controls="widget-settings-{{ $widget->id }}">
                                    <i class="bi bi-gear"></i>
                                </button>
                                <span class="btn btn-sm btn-outline-secondary widget-drag-handle" title="Drag to reorder">
                                    <i class="bi bi-arrows-move"></i>
                                </span>
                            </div>
                        @endif
                    </div>

                    @if($isEditing)
                        <div class="collapse mb-3" id="widget-settings-{{ $widget->id }}">
                            <div class="widget-settings-card p-3">
                                <form method="POST" action="{{ route('admin.dashboard.widgets.update', $widget) }}" class="row g-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="edit" value="1">
                                    @foreach($activeFilterValues as $filterKey => $filterValue)
                                        <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                    @endforeach
                                    <div class="col-md-6">
                                        <label class="form-label">Widget Title</label>
                                        <input class="form-control" type="text" name="title" value="{{ $widget->title }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Chart Type</label>
                                        <select class="form-select" name="chart_type">
                                            @foreach($chartTypes as $chartType)
                                                <option value="{{ $chartType }}" @selected($widget->chart_type === $chartType)>{{ \Illuminate\Support\Str::headline($chartType) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Color Scheme</label>
                                        <select class="form-select" name="color_scheme">
                                            @foreach($colorSchemes as $scheme)
                                                <option value="{{ $scheme }}" @selected($widget->color_scheme === $scheme)>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $scheme)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">SQL Query</label>
                                        <textarea class="form-control" name="sql_query" rows="5" required>{{ $widget->sql_query }}</textarea>
                                        <div class="form-text">Use read-only SQL (`SELECT`/`WITH`) only. Filter tokens: <code>@{{participants_filter:alias}}</code>, <code>@{{events_filter:alias}}</code>, and <code>@{{participant_events_filter:alias}}</code>.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Refresh (sec)</label>
                                        <input class="form-control" type="number" name="refresh_interval_seconds" min="0" max="86400" value="{{ $widget->refresh_interval_seconds }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Size Preset</label>
                                        <select class="form-select js-size-preset" name="size_preset">
                                            @foreach($sizePresets as $preset)
                                                <option value="{{ $preset }}" @selected($widget->size_preset === $preset)>{{ \Illuminate\Support\Str::headline($preset) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Width Mode</label>
                                        <select class="form-select js-width-mode" name="width_mode">
                                            @foreach($widthModes as $mode)
                                                <option value="{{ $mode }}" @selected($widget->width_mode === $mode)>{{ \Illuminate\Support\Str::headline($mode) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Height (px)</label>
                                        <input class="form-control" type="number" name="height_px" min="180" max="1000" value="{{ $widget->height_px }}">
                                    </div>
                                    <div class="col-md-3 js-width-columns-wrap">
                                        <label class="form-label">Width Columns (1-12)</label>
                                        <input class="form-control" type="number" name="width_columns" min="1" max="12" value="{{ $widget->width_columns }}">
                                    </div>
                                    <div class="col-md-3 js-width-px-wrap">
                                        <label class="form-label">Custom Width (px)</label>
                                        <input class="form-control" type="number" name="width_px" min="220" max="2200" value="{{ $widget->width_px }}">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-center">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="widget-active-{{ $widget->id }}" value="1" @checked($widget->is_active)>
                                            <label class="form-check-label" for="widget-active-{{ $widget->id }}">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between gap-2">
                                        <button type="button" class="btn btn-outline-danger" data-widget-delete-trigger="{{ $widget->id }}">Remove Widget</button>
                                        <button type="submit" class="btn btn-primary">Save Widget</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('admin.dashboard.widgets.destroy', $widget) }}" class="d-none" id="widget-delete-form-{{ $widget->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="edit" value="1">
                                    @foreach($activeFilterValues as $filterKey => $filterValue)
                                        <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                    @endforeach
                                </form>
                            </div>
                        </div>
                    @endif

                    @if(($payload['type'] ?? '') === 'error')
                        <div class="alert alert-danger mb-0">{{ $payload['message'] ?? 'Unable to render widget.' }}</div>
                    @elseif(($payload['type'] ?? '') === 'stat')
                        <div class="metric-card p-4 d-flex flex-column justify-content-center" style="height: {{ (int) $widget->height_px }}px;">
                            <div class="section-title">{{ $widget->title }}</div>
                            <div class="metric-value">
                                @if(is_numeric($payload['value'] ?? null))
                                    @php($numericValue = (float) $payload['value'])
                                    @if(fmod($numericValue, 1.0) == 0.0)
                                        {{ number_format($numericValue, 0) }}
                                    @else
                                        {{ rtrim(rtrim(number_format($numericValue, 2, '.', ','), '0'), '.') }}
                                    @endif
                                @else
                                    {{ $payload['value'] ?? '-' }}
                                @endif
                            </div>
                            <div class="text-secondary">{{ $payload['label'] ?? 'Total' }}</div>
                        </div>
                    @elseif(($payload['type'] ?? '') === 'table')
                        <div class="widget-table-wrap" style="height: {{ (int) $widget->height_px }}px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        @foreach(($payload['columns'] ?? []) as $column)
                                            <th>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $column)) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($payload['rows'] ?? []) as $row)
                                        <tr>
                                            @foreach(($payload['columns'] ?? []) as $column)
                                                <td>{{ $row[$column] ?? '-' }}</td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ max(count($payload['columns'] ?? []), 1) }}" class="text-center text-secondary">No data</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="widget-chart-wrap" style="height: {{ (int) $widget->height_px }}px;">
                            <canvas id="dashboard-widget-canvas-{{ $widget->id }}"></canvas>
                        </div>
                    @endif
                </article>
            @empty
                <div class="widget-empty w-100">No widgets yet. Add a widget to start building this dashboard.</div>
            @endforelse
        </div>
    @endif
</div>

@if($isEditing)
    <div class="modal fade" id="createTabModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.dashboard.tabs.store') }}">
                    @csrf
                    <input type="hidden" name="edit" value="1">
                    @foreach($activeFilterValues as $filterKey => $filterValue)
                        <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                    @endforeach
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Create Dashboard Tab</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Tab Name</label>
                        <input type="text" class="form-control" name="name" placeholder="Training Dashboard" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark">Create Tab</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($activeTab && $isEditing)
<div class="modal fade" id="editTabModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.dashboard.tabs.update', $activeTab) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="edit" value="1">
                @foreach($activeFilterValues as $filterKey => $filterValue)
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endforeach
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0">Edit Dashboard Tab</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Tab Name</label>
                    <input type="text" class="form-control mb-3" name="name" value="{{ $activeTab->name }}" required>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="tab-default-checkbox" name="is_default" @checked($activeTab->is_default)>
                        <label class="form-check-label" for="tab-default-checkbox">Set as default tab</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Tab</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($activeTab && $isEditing)
<div class="modal fade" id="createWidgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.dashboard.widgets.store', $activeTab) }}" class="js-widget-form">
                @csrf
                <input type="hidden" name="edit" value="1">
                @foreach($activeFilterValues as $filterKey => $filterValue)
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endforeach
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0">Add Widget</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Widget Title</label>
                            <input class="form-control" type="text" name="title" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Chart Type</label>
                            <select class="form-select" name="chart_type">
                                @foreach($chartTypes as $chartType)
                                    <option value="{{ $chartType }}">{{ \Illuminate\Support\Str::headline($chartType) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Color Scheme</label>
                            <select class="form-select" name="color_scheme">
                                @foreach($colorSchemes as $scheme)
                                    <option value="{{ $scheme }}">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $scheme)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">SQL Query</label>
                            <textarea class="form-control" name="sql_query" rows="6" required placeholder="SELECT label, value FROM ..."></textarea>
                            <div class="form-text">Supported chart data formats:
                                `label,value`; or `label,series,value` for grouped charts.
                                Filter tokens: <code>@{{participants_filter:alias}}</code>, <code>@{{events_filter:alias}}</code>, and <code>@{{participant_events_filter:alias}}</code>.
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Refresh (sec)</label>
                            <input class="form-control" type="number" name="refresh_interval_seconds" min="0" max="86400" placeholder="0 = manual">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Size Preset</label>
                            <select class="form-select js-size-preset" name="size_preset">
                                @foreach($sizePresets as $preset)
                                    <option value="{{ $preset }}" @selected($preset === 'medium')>{{ \Illuminate\Support\Str::headline($preset) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Width Mode</label>
                            <select class="form-select js-width-mode" name="width_mode">
                                @foreach($widthModes as $mode)
                                    <option value="{{ $mode }}">{{ \Illuminate\Support\Str::headline($mode) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Height (px)</label>
                            <input class="form-control" type="number" name="height_px" min="180" max="1000" value="280">
                        </div>
                        <div class="col-md-3 js-width-columns-wrap">
                            <label class="form-label">Width Columns (1-12)</label>
                            <input class="form-control" type="number" name="width_columns" min="1" max="12" value="6">
                        </div>
                        <div class="col-md-3 js-width-px-wrap">
                            <label class="form-label">Custom Width (px)</label>
                            <input class="form-control" type="number" name="width_px" min="220" max="2200" placeholder="e.g. 520">
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" value="1" id="new-widget-active" name="is_active" checked>
                                <label class="form-check-label" for="new-widget-active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Widget</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($isEditing)
    <div class="modal fade" id="importLayoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.dashboard.layout.import') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="edit" value="1">
                    @foreach($activeFilterValues as $filterKey => $filterValue)
                        <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                    @endforeach
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Import Layout</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Layout JSON File</label>
                        <input class="form-control" type="file" name="layout_file" accept=".json,.txt" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@section('scripts')
<script>
const dashboardEditingEnabled = @json((bool) $isEditing);
const widgetPayloads = @json($widgetPayloads);
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

const dashboardCharts = {};

function buildChartDatasets(widgetType, datasets, scheme) {
    const palette = chartPalette[scheme] || chartPalette.teal_amber;

    if (widgetType === 'pie' || widgetType === 'doughnut') {
        const base = (datasets[0] || { label: 'Value', data: [] });
        return [{
            label: base.label || 'Value',
            data: base.data || [],
            backgroundColor: (base.data || []).map((_, idx) => palette[idx % palette.length] + 'cc'),
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
                backgroundColor: color + '33',
                pointBackgroundColor: color,
                tension: 0.25,
                fill: widgetType === 'radar',
            };
        }

        return {
            ...common,
            backgroundColor: color + 'cc',
            borderColor: color,
            borderWidth: 1,
        };
    });
}

function renderWidgetChart(widgetId, widgetElement, payload) {
    const chartType = widgetElement.dataset.widgetChartType;
    const scheme = widgetElement.dataset.widgetColor;
    const canvas = document.getElementById(`dashboard-widget-canvas-${widgetId}`);
    if (!canvas || !payload || payload.type !== 'chart') {
        return;
    }

    if (dashboardCharts[widgetId]) {
        dashboardCharts[widgetId].destroy();
    }

    dashboardCharts[widgetId] = new Chart(canvas, {
        type: chartType,
        data: {
            labels: payload.labels || [],
            datasets: buildChartDatasets(chartType, payload.datasets || [], scheme),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
            },
            scales: (chartType === 'bar' || chartType === 'line' || chartType === 'radar')
                ? { y: { beginAtZero: true } }
                : {},
        },
    });
}

function initializeCharts() {
    document.querySelectorAll('.dashboard-widget-shell').forEach((widget) => {
        const widgetId = widget.dataset.widgetId;
        const payload = widgetPayloads[widgetId];
        renderWidgetChart(widgetId, widget, payload);
    });
}

function initializeRefreshIntervals() {
    document.querySelectorAll('.dashboard-widget-shell').forEach((widget) => {
        const refresh = Number(widget.dataset.widgetRefresh || 0);
        if (!refresh || refresh < 5) {
            return;
        }

        const widgetId = widget.dataset.widgetId;
        const dataUrl = widget.dataset.widgetDataUrl;

        window.setInterval(async () => {
            try {
                const response = await fetch(dataUrl, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                if (data.status !== 'ok') {
                    return;
                }
                widgetPayloads[widgetId] = data.data;
                renderWidgetChart(widgetId, widget, data.data);
            } catch (error) {
                console.error('Widget refresh failed', error);
            }
        }, refresh * 1000);
    });
}

function initializeDragDrop() {
    if (!dashboardEditingEnabled) {
        return;
    }

    const grid = document.getElementById('dashboardGrid');
    if (!grid) {
        return;
    }

    let dragged = null;

    grid.querySelectorAll('.dashboard-widget-shell').forEach((card) => {
        card.addEventListener('dragstart', () => {
            dragged = card;
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            dragged = null;
        });

        card.addEventListener('dragover', (event) => {
            event.preventDefault();
        });

        card.addEventListener('drop', async (event) => {
            event.preventDefault();
            if (!dragged || dragged === card) {
                return;
            }

            const rect = card.getBoundingClientRect();
            const insertAfter = (event.clientY - rect.top) > (rect.height / 2);
            if (insertAfter) {
                card.insertAdjacentElement('afterend', dragged);
            } else {
                card.insertAdjacentElement('beforebegin', dragged);
            }

            const orderedIds = Array.from(grid.querySelectorAll('.dashboard-widget-shell'))
                .map((element) => Number(element.dataset.widgetId));

            try {
                await fetch(grid.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': grid.dataset.csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ordered_ids: orderedIds }),
                });
            } catch (error) {
                console.error('Failed to save widget order', error);
            }
        });
    });
}

function updateWidthInputs(container) {
    const sizePreset = container.querySelector('.js-size-preset');
    const widthMode = container.querySelector('.js-width-mode');
    const widthColumnsWrap = container.querySelector('.js-width-columns-wrap');
    const widthPxWrap = container.querySelector('.js-width-px-wrap');
    if (!sizePreset || !widthMode || !widthColumnsWrap || !widthPxWrap) {
        return;
    }

    const preset = sizePreset.value;
    const mode = widthMode.value;
    const custom = preset === 'custom';
    widthMode.disabled = !custom;
    widthColumnsWrap.style.display = (custom && mode === 'columns') ? '' : 'none';
    widthPxWrap.style.display = (custom && mode === 'pixels') ? '' : 'none';
}

function initializeWidgetForms() {
    document.querySelectorAll('.js-widget-form, .widget-settings-card form').forEach((form) => {
        const sizePreset = form.querySelector('.js-size-preset');
        const widthMode = form.querySelector('.js-width-mode');
        if (!sizePreset || !widthMode) {
            return;
        }

        const update = () => updateWidthInputs(form);
        sizePreset.addEventListener('change', update);
        widthMode.addEventListener('change', update);
        update();
    });

    document.querySelectorAll('[data-widget-delete-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            const widgetId = button.dataset.widgetDeleteTrigger;
            if (!confirm('Remove this widget?')) {
                return;
            }

            const form = document.getElementById(`widget-delete-form-${widgetId}`);
            if (form) {
                form.submit();
            }
        });
    });
}

initializeCharts();
initializeRefreshIntervals();
initializeDragDrop();
initializeWidgetForms();
</script>
@endsection
