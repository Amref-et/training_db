@extends('layouts.admin')

@section('eyebrow', 'Training')
@section('title', 'Event Calendar View')
@section('subtitle', 'Weekly, monthly, and yearly training event calendar.')

@section('actions')
<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('training-events-calendar.embed', $embedQuery) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">Open Embed View</a>
    <a href="{{ route('admin.training-events.index') }}" class="btn btn-outline-secondary">Back to Training Events</a>
</div>
@endsection

@section('head')
<style>
    .calendar-shell { display: grid; gap: 1.25rem; }
    .calendar-hero {
        background:
            radial-gradient(circle at top right, rgba(20, 184, 166, .18), transparent 34%),
            linear-gradient(135deg, #0f172a 0%, #123447 48%, #0f766e 100%);
        color: #f8fafc;
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 24px 50px rgba(15, 23, 42, .18);
    }
    .calendar-hero-kicker { font-size: .76rem; letter-spacing: .14em; text-transform: uppercase; color: rgba(226, 232, 240, .72); margin-bottom: .4rem; }
    .calendar-hero-title { font-size: clamp(1.6rem, 2.2vw, 2.35rem); font-weight: 700; line-height: 1.1; margin: 0; }
    .calendar-hero-copy { margin-top: .65rem; color: rgba(226, 232, 240, .8); max-width: 58rem; }
    .calendar-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .85rem; margin-top: 1.15rem; }
    .calendar-summary-card { padding: 1rem 1.05rem; border-radius: 18px; background: rgba(255, 255, 255, .09); border: 1px solid rgba(255, 255, 255, .12); backdrop-filter: blur(10px); }
    .calendar-summary-label { font-size: .74rem; letter-spacing: .08em; text-transform: uppercase; color: rgba(226, 232, 240, .66); }
    .calendar-summary-value { font-size: 1.7rem; line-height: 1; font-weight: 700; margin-top: .5rem; }
    .calendar-summary-note { margin-top: .35rem; font-size: .82rem; color: rgba(226, 232, 240, .75); }
    .calendar-meta-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr); gap: 1.25rem; }
    .calendar-panel-card { background: #fff; border: 1px solid rgba(15, 23, 42, .08); border-radius: 22px; padding: 1.15rem; box-shadow: 0 18px 36px rgba(15, 23, 42, .06); }
    .calendar-panel-title { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: .25rem; }
    .calendar-panel-copy { color: #64748b; font-size: .9rem; margin-bottom: .9rem; }
    .calendar-embed-textarea { min-height: 120px; font-family: Consolas, "Courier New", monospace; font-size: .82rem; border-radius: 16px; }
    .calendar-upcoming-list { display: grid; gap: .8rem; }
    .calendar-upcoming-item { padding: .9rem 1rem; border-radius: 16px; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border: 1px solid rgba(15, 23, 42, .08); }
    .calendar-upcoming-name { font-weight: 700; color: #0f172a; line-height: 1.3; }
    .calendar-upcoming-line { font-size: .82rem; color: #64748b; margin-top: .18rem; }
    .calendar-board { background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border: 1px solid rgba(15, 23, 42, .08); border-radius: 24px; padding: 1.15rem; box-shadow: 0 22px 40px rgba(15, 23, 42, .06); }
    .calendar-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .calendar-toolbar-title { font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 0; }
    .calendar-toolbar-copy { color: #64748b; font-size: .9rem; margin-top: .2rem; }
    .calendar-toolbar-actions, .calendar-toolbar-form, .calendar-view-switch { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
    .calendar-pill-btn, .calendar-view-chip {
        display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: .55rem .9rem; border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, .12); background: #fff; color: #0f172a; text-decoration: none; font-weight: 600; box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
    }
    .calendar-pill-btn:hover, .calendar-view-chip:hover { background: #f8fafc; color: #0f172a; }
    .calendar-view-chip.is-active { background: #0f172a; color: #f8fafc; border-color: #0f172a; box-shadow: none; }
    .calendar-jump-input { min-height: 40px; border-radius: 999px; border-color: rgba(15, 23, 42, .12); padding-inline: .9rem; }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .85rem; }
    .calendar-weekday { font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #64748b; padding: .15rem .45rem; }
    .calendar-week { margin-top: .85rem; }
    .calendar-day { min-height: 178px; border-radius: 20px; padding: .8rem; background: #fff; border: 1px solid rgba(15, 23, 42, .08); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .8); display: flex; flex-direction: column; gap: .55rem; }
    .calendar-day.outside { background: #f8fafc; color: #94a3b8; }
    .calendar-day.is-weekend { background: linear-gradient(180deg, rgba(248, 250, 252, .95) 0%, rgba(241, 245, 249, .9) 100%); }
    .calendar-day.today { border-color: rgba(15, 118, 110, .45); box-shadow: 0 0 0 3px rgba(20, 184, 166, .12); }
    .calendar-day-header { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    .calendar-day-number { width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; font-weight: 700; color: #0f172a; background: rgba(15, 23, 42, .05); }
    .calendar-day.today .calendar-day-number { background: #0f766e; color: #fff; }
    .calendar-day-count { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
    .calendar-events { display: grid; gap: .45rem; }
    .calendar-empty { margin-top: auto; font-size: .8rem; color: #94a3b8; }
    .calendar-event { position: relative; display: block; padding: .58rem .68rem; border-radius: 16px; text-decoration: none; background: linear-gradient(135deg, rgba(20, 184, 166, .12) 0%, rgba(14, 165, 233, .08) 100%); border: 1px solid rgba(15, 118, 110, .18); color: #134e4a; box-shadow: 0 8px 18px rgba(15, 118, 110, .08); }
    .calendar-event:hover { color: #0f3f3b; background: linear-gradient(135deg, rgba(20, 184, 166, .18) 0%, rgba(14, 165, 233, .12) 100%); }
    .calendar-event-title { display: block; font-size: .8rem; font-weight: 700; line-height: 1.3; }
    .calendar-event-meta { display: block; margin-top: .2rem; font-size: .72rem; color: #285e61; line-height: 1.35; }
    .calendar-more { margin-top: .1rem; font-size: .75rem; color: #64748b; font-weight: 600; }
    .calendar-event-tooltip { position: absolute; left: 0; top: calc(100% + .45rem); width: min(300px, 75vw); background: #0f172a; color: #e2e8f0; border-radius: 16px; padding: .85rem .9rem; box-shadow: 0 20px 36px rgba(15, 23, 42, .3); opacity: 0; visibility: hidden; transform: translateY(6px); transition: opacity .16s ease, transform .16s ease, visibility .16s ease; z-index: 30; pointer-events: none; }
    .calendar-event:hover .calendar-event-tooltip, .calendar-event:focus-visible .calendar-event-tooltip { opacity: 1; visibility: visible; transform: translateY(0); }
    .calendar-event-tooltip-title { font-size: .85rem; font-weight: 700; color: #f8fafc; margin-bottom: .45rem; }
    .calendar-event-tooltip-line { display: block; font-size: .73rem; line-height: 1.5; color: rgba(226, 232, 240, .84); }
    .calendar-year-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
    .calendar-year-card { background: #fff; border: 1px solid rgba(15, 23, 42, .08); border-radius: 20px; padding: 1rem; box-shadow: 0 14px 28px rgba(15, 23, 42, .05); }
    .calendar-year-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; margin-bottom: .8rem; }
    .calendar-year-card-title { font-size: 1rem; font-weight: 700; color: #0f172a; }
    .calendar-year-card-count { font-size: .8rem; color: #64748b; font-weight: 600; }
    .calendar-year-metrics { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: .8rem; }
    .calendar-year-metric { font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #0f766e; background: rgba(20, 184, 166, .1); border-radius: 999px; padding: .35rem .55rem; }
    .calendar-year-events { display: grid; gap: .45rem; }
    .calendar-year-event { padding: .65rem .75rem; border-radius: 14px; background: #f8fafc; border: 1px solid rgba(15, 23, 42, .06); }
    .calendar-year-event-name { font-size: .82rem; font-weight: 700; color: #0f172a; line-height: 1.35; }
    .calendar-year-event-line { font-size: .74rem; color: #64748b; margin-top: .16rem; }
    .calendar-year-link { display: inline-flex; margin-top: .8rem; text-decoration: none; font-size: .82rem; font-weight: 700; color: #0f766e; }
    @media (max-width: 1199.98px) { .calendar-meta-grid, .calendar-year-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 991.98px) { .calendar-grid { gap: .6rem; } .calendar-day { min-height: 150px; } .calendar-year-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 767.98px) {
        .calendar-hero, .calendar-board, .calendar-panel-card { border-radius: 20px; }
        .calendar-grid, .calendar-year-grid { grid-template-columns: 1fr; }
        .calendar-weekday { display: none; }
        .calendar-week { margin-top: 0; }
        .calendar-day { min-height: auto; }
    }
</style>
@endsection

@section('content')
<div class="calendar-shell">
    <div class="calendar-hero">
        <div class="calendar-hero-kicker">Training Calendar</div>
        <h2 class="calendar-hero-title">{{ $periodTitle }}</h2>
        <div class="calendar-hero-copy">Professional {{ strtolower($periodLabel) }} of scheduled learning activity, project ownership, and delivery coverage.</div>
        <div class="calendar-summary">
            <div class="calendar-summary-card">
                <div class="calendar-summary-label">Events In View</div>
                <div class="calendar-summary-value">{{ number_format($totalEvents) }}</div>
                <div class="calendar-summary-note">{{ $periodRangeLabel }}</div>
            </div>
            <div class="calendar-summary-card">
                <div class="calendar-summary-label">Active Days</div>
                <div class="calendar-summary-value">{{ number_format($activeDays) }}</div>
                <div class="calendar-summary-note">Days with at least one event</div>
            </div>
            <div class="calendar-summary-card">
                <div class="calendar-summary-label">Projects</div>
                <div class="calendar-summary-value">{{ number_format($projectCount) }}</div>
                <div class="calendar-summary-note">Distinct project lines represented</div>
            </div>
        </div>
    </div>

    @php($iframeCode = '<iframe src="'.$embedUrl.'" title="Training Events Calendar" width="100%" height="900" style="border:0; max-width:100%;" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>')

    <div class="calendar-meta-grid">
        <div class="calendar-panel-card">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                <div>
                    <div class="calendar-panel-title">Embed Calendar</div>
                    <div class="calendar-panel-copy">Use the public embed view inside CMS pages or external dashboards without exposing the admin interface.</div>
                </div>
                <button type="button" class="btn btn-dark btn-sm" id="copy-calendar-embed">Copy Code</button>
            </div>
            <textarea id="calendar-embed-code" class="form-control calendar-embed-textarea" rows="4" readonly>{{ $iframeCode }}</textarea>
            <div id="calendar-embed-feedback" class="form-text mt-2">Embed URL: <a href="{{ $embedUrl }}" target="_blank" rel="noopener">{{ $embedUrl }}</a></div>
        </div>

        <div class="calendar-panel-card">
            <div class="calendar-panel-title">Upcoming In View</div>
            <div class="calendar-panel-copy">A quick agenda snapshot for the current {{ strtolower($periodLabel) }}.</div>
            <div class="calendar-upcoming-list">
                @forelse($upcomingEvents as $event)
                    <div class="calendar-upcoming-item">
                        <div class="calendar-upcoming-name">{{ $event['name'] }}</div>
                        <div class="calendar-upcoming-line">{{ $event['date_label'] }}</div>
                        <div class="calendar-upcoming-line">{{ $event['organized_by'] ?: 'Project not set' }}</div>
                        <div class="calendar-upcoming-line">{{ $event['venue_label'] }}</div>
                    </div>
                @empty
                    <div class="calendar-upcoming-item">
                        <div class="calendar-upcoming-name">No events scheduled</div>
                        <div class="calendar-upcoming-line">There are no training events in this {{ strtolower($periodLabel) }}.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="calendar-board">
        <div class="calendar-toolbar">
            <div>
                <h3 class="calendar-toolbar-title">{{ $periodTitle }}</h3>
                <div class="calendar-toolbar-copy">{{ $periodRangeLabel }}</div>
            </div>
            <div class="calendar-toolbar-actions">
                <div class="calendar-view-switch">
                    @foreach(['week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $mode => $label)
                        <a href="{{ route('admin.training-events-calendar.index', $switchQueries[$mode]) }}" class="calendar-view-chip {{ $viewMode === $mode ? 'is-active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <a href="{{ route('admin.training-events-calendar.index', $prevQuery) }}" class="calendar-pill-btn">&larr; Previous</a>
                <a href="{{ route('admin.training-events-calendar.index', $nextQuery) }}" class="calendar-pill-btn">Next &rarr;</a>
                <form method="GET" action="{{ route('admin.training-events-calendar.index') }}" class="calendar-toolbar-form">
                    <input type="hidden" name="view" value="{{ $viewMode }}">
                    <input type="{{ $jumpInputType }}" name="{{ $jumpInputName }}" value="{{ $jumpInputValue }}" class="form-control form-control-sm calendar-jump-input" aria-label="{{ $jumpLabel }}">
                    <button class="btn btn-dark btn-sm rounded-pill px-3" type="submit">Go</button>
                </form>
            </div>
        </div>

        @if($viewMode === 'year')
            <div class="calendar-year-grid">
                @foreach($yearMonths as $yearMonth)
                    <div class="calendar-year-card">
                        <div class="calendar-year-card-header">
                            <div class="calendar-year-card-title">{{ $yearMonth['label'] }}</div>
                            <div class="calendar-year-card-count">{{ number_format($yearMonth['total_events']) }} events</div>
                        </div>
                        <div class="calendar-year-metrics">
                            <span class="calendar-year-metric">{{ $yearMonth['active_days'] }} active days</span>
                        </div>
                        <div class="calendar-year-events">
                            @forelse($yearMonth['preview_events'] as $event)
                                <div class="calendar-year-event">
                                    <div class="calendar-year-event-name">{{ $event['name'] }}</div>
                                    <div class="calendar-year-event-line">{{ $event['date_label'] }}</div>
                                    <div class="calendar-year-event-line">{{ $event['organized_by'] ?: 'Project not set' }}</div>
                                </div>
                            @empty
                                <div class="calendar-year-event">
                                    <div class="calendar-year-event-name">No events scheduled</div>
                                </div>
                            @endforelse
                        </div>
                        <a class="calendar-year-link" href="{{ route('admin.training-events-calendar.index', ['view' => 'month', 'month' => $yearMonth['month_input']]) }}">Open Month &rarr;</a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="calendar-grid">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)
                    <div class="calendar-weekday">{{ $weekday }}</div>
                @endforeach
            </div>

            @foreach($weeks as $week)
                <div class="calendar-grid calendar-week">
                    @foreach($week as $day)
                        @php($visibleEvents = collect($day['events'])->take(3))
                        <div class="calendar-day {{ $day['in_month'] ? '' : 'outside' }} {{ $day['is_today'] ? 'today' : '' }} {{ $day['is_weekend'] ? 'is-weekend' : '' }}">
                            <div class="calendar-day-header">
                                <div class="calendar-day-number">{{ $day['day'] }}</div>
                                <div class="calendar-day-count">{{ count($day['events']) > 0 ? count($day['events']).' events' : 'Open' }}</div>
                            </div>

                            @if($visibleEvents->isNotEmpty())
                                <div class="calendar-events">
                                    @foreach($visibleEvents as $event)
                                        <a class="calendar-event" href="{{ route('admin.training-events.edit', $event['id']) }}">
                                            <span class="calendar-event-title">{{ $event['name'] }}</span>
                                            <span class="calendar-event-meta">{{ $event['organized_by'] ?: ($event['organizer'] ?: 'Project not set') }}</span>
                                            <span class="calendar-event-tooltip">
                                                <span class="calendar-event-tooltip-title">{{ $event['name'] }}</span>
                                                @if($event['training'])
                                                    <span class="calendar-event-tooltip-line">Training: {{ $event['training'] }}</span>
                                                @endif
                                                @if($event['organizer'])
                                                    <span class="calendar-event-tooltip-line">Project: {{ $event['organizer'] }}</span>
                                                @endif
                                                @if($event['organized_by'])
                                                    <span class="calendar-event-tooltip-line">Organized By: {{ $event['organized_by'] }}</span>
                                                @endif
                                                <span class="calendar-event-tooltip-line">Dates: {{ $event['date_label'] }}</span>
                                                <span class="calendar-event-tooltip-line">Venue: {{ $event['venue_label'] }}</span>
                                                <span class="calendar-event-tooltip-line">Status: {{ $event['status_label'] }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="calendar-empty">No events scheduled.</div>
                            @endif

                            @if(count($day['events']) > 3)
                                <div class="calendar-more">+{{ count($day['events']) - 3 }} more</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    (() => {
        const button = document.getElementById('copy-calendar-embed');
        const textarea = document.getElementById('calendar-embed-code');
        const feedback = document.getElementById('calendar-embed-feedback');

        if (!button || !textarea || !feedback) {
            return;
        }

        button.addEventListener('click', async () => {
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);

            try {
                await navigator.clipboard.writeText(textarea.value);
            } catch (error) {
                document.execCommand('copy');
            }

            feedback.textContent = 'Embed code copied.';
        });
    })();
</script>
@endsection
