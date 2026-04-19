<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Events Calendar</title>
    <style>
        :root {
            color-scheme: light;
            --calendar-bg: #eef4f7;
            --calendar-panel: rgba(255, 255, 255, .94);
            --calendar-border: rgba(15, 23, 42, .08);
            --calendar-text: #0f172a;
            --calendar-muted: #64748b;
            --calendar-accent: #0f766e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(20, 184, 166, .14), transparent 30%),
                linear-gradient(180deg, #f8fafc 0%, var(--calendar-bg) 100%);
            color: var(--calendar-text);
        }
        .embed-shell { padding: 1rem; }
        .embed-panel { border-radius: 24px; border: 1px solid var(--calendar-border); background: var(--calendar-panel); box-shadow: 0 26px 48px rgba(15, 23, 42, .08); overflow: hidden; }
        .embed-hero {
            padding: 1.2rem 1.2rem 1rem;
            background:
                radial-gradient(circle at top right, rgba(20, 184, 166, .18), transparent 32%),
                linear-gradient(135deg, #0f172a 0%, #123447 48%, #0f766e 100%);
            color: #f8fafc;
        }
        .embed-kicker { font-size: .74rem; letter-spacing: .14em; text-transform: uppercase; color: rgba(226, 232, 240, .72); }
        .embed-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-top: .35rem; }
        .embed-title { font-size: clamp(1.35rem, 2vw, 2rem); font-weight: 700; line-height: 1.1; margin: 0; }
        .embed-subtitle { margin-top: .45rem; color: rgba(226, 232, 240, .8); font-size: .9rem; }
        .embed-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .75rem; margin-top: 1rem; }
        .embed-summary-card { padding: .85rem .95rem; border-radius: 18px; background: rgba(255, 255, 255, .1); border: 1px solid rgba(255, 255, 255, .12); }
        .embed-summary-label { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: rgba(226, 232, 240, .7); }
        .embed-summary-value { font-size: 1.45rem; font-weight: 700; line-height: 1; margin-top: .45rem; }
        .embed-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.2rem .8rem; background: rgba(248, 250, 252, .9); border-bottom: 1px solid var(--calendar-border); }
        .embed-toolbar-copy { font-size: .9rem; color: var(--calendar-muted); }
        .embed-nav, .embed-view-switch, .embed-jump-form { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
        .embed-nav a, .embed-view-chip {
            display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: .55rem .9rem;
            text-decoration: none; color: var(--calendar-text); border-radius: 999px; border: 1px solid rgba(15, 23, 42, .12);
            background: #fff; font-weight: 600; box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
        }
        .embed-view-chip.is-active { background: #0f172a; color: #f8fafc; border-color: #0f172a; box-shadow: none; }
        .embed-jump-input { min-height: 40px; border-radius: 999px; border: 1px solid rgba(15, 23, 42, .12); padding-inline: .9rem; }
        .embed-jump-submit { min-height: 40px; border: 0; border-radius: 999px; background: #0f172a; color: #fff; padding: .55rem 1rem; font-weight: 700; }
        .embed-board { padding: 1rem 1.2rem 1.2rem; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .75rem; }
        .calendar-weekday { font-size: .76rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--calendar-muted); padding: .1rem .35rem; }
        .calendar-week { margin-top: .75rem; }
        .calendar-day { min-height: 168px; border-radius: 18px; background: #fff; border: 1px solid var(--calendar-border); padding: .75rem; display: flex; flex-direction: column; gap: .5rem; box-shadow: 0 14px 28px rgba(15, 23, 42, .04); }
        .calendar-day.outside { background: #f8fafc; color: #94a3b8; }
        .calendar-day.today { border-color: rgba(15, 118, 110, .42); box-shadow: 0 0 0 3px rgba(20, 184, 166, .1); }
        .calendar-day-header { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .calendar-day-number { width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: rgba(15, 23, 42, .05); font-size: .92rem; font-weight: 700; color: #0f172a; }
        .calendar-day.today .calendar-day-number { background: var(--calendar-accent); color: #fff; }
        .calendar-day-count { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--calendar-muted); }
        .calendar-events { display: grid; gap: .45rem; }
        .calendar-event { position: relative; display: block; border-radius: 14px; padding: .55rem .65rem; background: linear-gradient(135deg, rgba(20, 184, 166, .12) 0%, rgba(14, 165, 233, .08) 100%); border: 1px solid rgba(15, 118, 110, .16); box-shadow: 0 8px 18px rgba(15, 118, 110, .08); }
        .calendar-event-title { display: block; font-size: .8rem; font-weight: 700; line-height: 1.3; color: #134e4a; }
        .calendar-event-meta { display: block; margin-top: .18rem; font-size: .72rem; line-height: 1.35; color: #285e61; }
        .calendar-more { margin-top: .1rem; font-size: .75rem; font-weight: 600; color: var(--calendar-muted); }
        .calendar-empty { margin-top: auto; font-size: .8rem; color: #94a3b8; }
        .calendar-event-tooltip { position: absolute; left: 0; top: calc(100% + .45rem); width: min(300px, 78vw); background: #0f172a; color: #e2e8f0; border-radius: 16px; padding: .85rem .9rem; box-shadow: 0 20px 36px rgba(15, 23, 42, .28); opacity: 0; visibility: hidden; transform: translateY(6px); transition: opacity .16s ease, transform .16s ease, visibility .16s ease; z-index: 30; pointer-events: none; }
        .calendar-event:hover .calendar-event-tooltip { opacity: 1; visibility: visible; transform: translateY(0); }
        .calendar-event-tooltip-title { display: block; font-size: .84rem; font-weight: 700; color: #f8fafc; margin-bottom: .45rem; }
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
        @media (max-width: 1199.98px) { .calendar-year-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 900px) { .calendar-grid { gap: .55rem; } .calendar-day { min-height: 140px; } .calendar-year-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 767.98px) {
            .embed-panel { border-radius: 20px; }
            .calendar-grid, .calendar-year-grid { grid-template-columns: 1fr; }
            .calendar-weekday { display: none; }
            .calendar-day { min-height: auto; }
        }
    </style>
</head>
<body>
    <div class="embed-shell">
        <div class="embed-panel">
            <div class="embed-hero">
                <div class="embed-kicker">Training Calendar</div>
                <div class="embed-header">
                    <div>
                        <h1 class="embed-title">{{ $periodTitle }}</h1>
                        <div class="embed-subtitle">{{ $periodRangeLabel }}</div>
                    </div>
                    <div class="embed-nav">
                        <a href="{{ route('training-events-calendar.embed', $prevQuery) }}">&larr; Previous</a>
                        <a href="{{ route('training-events-calendar.embed', $nextQuery) }}">Next &rarr;</a>
                    </div>
                </div>
                <div class="embed-summary">
                    <div class="embed-summary-card">
                        <div class="embed-summary-label">Events</div>
                        <div class="embed-summary-value">{{ number_format($totalEvents) }}</div>
                    </div>
                    <div class="embed-summary-card">
                        <div class="embed-summary-label">Active Days</div>
                        <div class="embed-summary-value">{{ number_format($activeDays) }}</div>
                    </div>
                    <div class="embed-summary-card">
                        <div class="embed-summary-label">Projects</div>
                        <div class="embed-summary-value">{{ number_format($projectCount) }}</div>
                    </div>
                </div>
            </div>

            <div class="embed-toolbar">
                <div class="embed-view-switch">
                    @foreach(['week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $mode => $label)
                        <a href="{{ route('training-events-calendar.embed', $switchQueries[$mode]) }}" class="embed-view-chip {{ $viewMode === $mode ? 'is-active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('training-events-calendar.embed') }}" class="embed-jump-form">
                    <input type="hidden" name="view" value="{{ $viewMode }}">
                    <input type="{{ $jumpInputType }}" name="{{ $jumpInputName }}" value="{{ $jumpInputValue }}" class="embed-jump-input" aria-label="{{ $jumpLabel }}">
                    <button type="submit" class="embed-jump-submit">Go</button>
                </form>
            </div>

            <div class="embed-board">
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
                                <a class="calendar-year-link" href="{{ route('training-events-calendar.embed', ['view' => 'month', 'month' => $yearMonth['month_input']]) }}">Open Month &rarr;</a>
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
                                <div class="calendar-day {{ $day['in_month'] ? '' : 'outside' }} {{ $day['is_today'] ? 'today' : '' }}">
                                    <div class="calendar-day-header">
                                        <div class="calendar-day-number">{{ $day['day'] }}</div>
                                        <div class="calendar-day-count">{{ count($day['events']) > 0 ? count($day['events']).' events' : 'Open' }}</div>
                                    </div>

                                    @if($visibleEvents->isNotEmpty())
                                        <div class="calendar-events">
                                            @foreach($visibleEvents as $event)
                                                <div class="calendar-event">
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
                                                </div>
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
    </div>
</body>
</html>
