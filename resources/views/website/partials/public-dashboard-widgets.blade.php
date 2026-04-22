@php
    $publicDashboardTab = $publicDashboard['tab'] ?? null;
    $publicDashboardWidgets = $publicDashboard['widgets'] ?? collect();
    $publicDashboardPayloads = $publicDashboard['widgetPayloads'] ?? [];
    $publicDashboardFilters = $publicDashboard['filters'] ?? [];
    $publicDashboardFilterDefinitions = collect($publicDashboard['filterDefinitions'] ?? []);
    $selectedDashboardFilters = collect($block['selected_filters'] ?? [])->map(fn ($value) => (string) $value)->all();
    $visibleDashboardFilters = $selectedDashboardFilters === []
        ? $publicDashboardFilterDefinitions->values()
        : $publicDashboardFilterDefinitions
            ->filter(fn (array $definition) => in_array((string) ($definition['key'] ?? ''), $selectedDashboardFilters, true))
            ->values();
    $hasActiveDashboardFilters = collect($publicDashboardFilters)
        ->contains(fn ($value) => trim((string) $value) !== '');
    $widgetHexToRgb = function ($color, $fallback = '31, 41, 55') {
        $value = strtoupper(trim((string) $color));
        if (! preg_match('/^#([0-9A-F]{6})$/', $value, $matches)) {
            return $fallback;
        }

        return implode(', ', [
            hexdec(substr($matches[1], 0, 2)),
            hexdec(substr($matches[1], 2, 2)),
            hexdec(substr($matches[1], 4, 2)),
        ]);
    };
@endphp

@if(! $publicDashboardTab)
    <div class="text-secondary">A public dashboard tab has not been configured yet.</div>
@else
    <div class="public-dashboard-shell">
        @php($filterCollapseId = $dashboardBlockId.'-filters')
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
            <div>
                <div class="section-kicker mb-2">Live Dashboard</div>
                <div class="fw-semibold">{{ $publicDashboardTab->name }}</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($visibleDashboardFilters->isNotEmpty())
                    <button
                        class="btn btn-outline-secondary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $filterCollapseId }}"
                        aria-expanded="{{ $hasActiveDashboardFilters ? 'true' : 'false' }}"
                        aria-controls="{{ $filterCollapseId }}"
                    >
                        Filters
                    </button>
                @endif
                @auth
                    @can('dashboard.view')
                        <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.dashboard', ['tab_id' => $publicDashboardTab->id, 'edit' => '1']) }}">Edit Dashboard</a>
                    @endcan
                @endauth
            </div>
        </div>

        @if($visibleDashboardFilters->isNotEmpty())
            <div class="collapse {{ $hasActiveDashboardFilters ? 'show' : '' }}" id="{{ $filterCollapseId }}">
                <form method="GET" action="{{ $currentDashboardUrl }}" class="dashboard-filters public-dashboard-filters mb-4">
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
                                        @if(($publicDashboardFilters[$filterDefinition['key']] ?? '') !== '' && !empty($selectedDashboardOrganizationFilter))
                                            <option value="{{ $selectedDashboardOrganizationFilter['value'] }}" selected>{{ $selectedDashboardOrganizationFilter['label'] }}</option>
                                        @endif
                                    </select>
                                @else
                                    <select name="{{ $filterDefinition['key'] }}" class="form-select">
                                        <option value="">{{ $filterDefinition['all_label'] }}</option>
                                        @foreach($filterDefinition['options'] as $option)
                                            <option value="{{ $option['value'] }}" @selected(($publicDashboardFilters[$filterDefinition['key']] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        @endforeach
                        <div class="col-md-6 col-xl-3">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-dark flex-fill" type="submit">Apply Filters</button>
                                <a class="btn btn-outline-secondary flex-fill" href="{{ $currentDashboardUrl }}">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        <div class="public-dashboard-grid">
            @forelse($publicDashboardWidgets as $widget)
                @php($payload = $publicDashboardPayloads[$widget->id] ?? ['type' => 'error', 'message' => 'Widget data unavailable.'])
                @php($widgetBackgroundColor = strtoupper((string) ($widget->background_color ?: '#FFFFFF')))
                @php($widgetTextColor = strtoupper((string) ($widget->text_color ?: '#1F2937')))
                @php($widgetTextRgb = $widgetHexToRgb($widgetTextColor))
                <article
                    class="public-dashboard-widget-shell"
                    style="{{ ($publicDashboard['widgetWidthStyles'][$widget->id] ?? 'width:100%;').'--widget-bg: '.$widgetBackgroundColor.'; --widget-text: '.$widgetTextColor.'; --widget-text-rgb: '.$widgetTextRgb.'; --widget-border: rgba('.$widgetTextRgb.', .14);' }}"
                    data-widget-id="{{ $widget->id }}"
                    data-widget-chart-type="{{ $widget->chart_type }}"
                    data-widget-color="{{ $widget->color_scheme }}"
                    data-widget-text-color="{{ $widgetTextColor }}"
                >
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h3 class="h6 mb-1">{{ $widget->title }}</h3>
                        </div>
                    </div>

                    @if(($payload['type'] ?? '') === 'error')
                        <div class="alert alert-danger mb-0">{{ $payload['message'] ?? 'Unable to render widget.' }}</div>
                    @elseif(($payload['type'] ?? '') === 'stat')
                        <div class="metric-card p-4 d-flex flex-column justify-content-center" style="height: {{ (int) $widget->height_px }}px;">
                            <div class="widget-subtle text-uppercase small mb-2">{{ $payload['label'] ?? 'Metric' }}</div>
                            <div class="metric-value fw-bold">{{ is_numeric($payload['value'] ?? null) ? number_format((float) $payload['value'], ((float) ($payload['value'] ?? 0)) == floor((float) ($payload['value'] ?? 0)) ? 0 : 1) : ($payload['value'] ?? '0') }}</div>
                        </div>
                    @elseif(($payload['type'] ?? '') === 'table')
                        <div class="widget-table-wrap" style="height: {{ (int) $widget->height_px }}px;">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        @foreach($payload['columns'] ?? [] as $column)
                                            <th>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $column)) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payload['rows'] ?? [] as $row)
                                        <tr>
                                            @foreach($payload['columns'] ?? [] as $column)
                                                <td>{{ $row[$column] ?? '' }}</td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ max(1, count($payload['columns'] ?? [])) }}" class="text-center py-4">No data available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="widget-chart-wrap" style="height: {{ (int) $widget->height_px }}px;">
                            <canvas id="{{ $dashboardBlockId }}-widget-canvas-{{ $widget->id }}"></canvas>
                        </div>
                    @endif
                </article>
            @empty
                <div class="dashboard-empty text-secondary">No active public dashboard widgets are configured.</div>
            @endforelse
        </div>

        <script type="application/json" class="public-dashboard-chart-config">
            @json([
                'block_id' => $dashboardBlockId,
                'payloads' => $publicDashboardPayloads,
            ])
        </script>
    </div>
@endif
