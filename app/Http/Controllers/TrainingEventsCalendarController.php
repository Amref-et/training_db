<?php

namespace App\Http\Controllers;

use App\Models\TrainingEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TrainingEventsCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $data = $this->calendarViewData($request);

        return view('admin.training-events-calendar.index', $data + [
            'embedUrl' => route('training-events-calendar.embed', $data['embedQuery']),
        ]);
    }

    public function embed(Request $request): View
    {
        return view('training-events-calendar.embed', $this->calendarViewData($request));
    }

    private function calendarViewData(Request $request): array
    {
        $today = now();
        $viewMode = $this->viewMode($request);
        $monthInput = trim((string) $request->query('month', $today->format('Y-m')));
        $weekInput = trim((string) $request->query('week', $today->format('o-\WW')));
        $yearInput = trim((string) $request->query('year', $today->format('Y')));

        [
            'anchorDate' => $anchorDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'periodTitle' => $periodTitle,
            'periodRangeLabel' => $periodRangeLabel,
            'prevQuery' => $prevQuery,
            'nextQuery' => $nextQuery,
            'switchQueries' => $switchQueries,
            'jumpInputType' => $jumpInputType,
            'jumpInputName' => $jumpInputName,
            'jumpInputValue' => $jumpInputValue,
            'jumpLabel' => $jumpLabel,
        ] = $this->periodConfig($viewMode, $monthInput, $weekInput, $yearInput);

        $events = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'projectSubawardee'])
            ->whereNotNull('start_date')
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orWhereBetween('end_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orWhere(function ($nested) use ($periodStart, $periodEnd) {
                        $nested->whereDate('start_date', '<=', $periodStart->toDateString())
                            ->whereDate('end_date', '>=', $periodEnd->toDateString());
                    });
            })
            ->orderBy('start_date')
            ->orderBy('event_name')
            ->get();

        $formattedEvents = $events
            ->map(fn (TrainingEvent $event) => $this->eventPayload($event))
            ->filter()
            ->values();

        $eventsByDay = $this->eventsByDay($formattedEvents, $gridStart, $gridEnd);
        $weeks = $this->buildWeeks($eventsByDay, $gridStart, $gridEnd, $viewMode, $anchorDate);
        $yearMonths = $viewMode === 'year'
            ? $this->buildYearMonths($formattedEvents, $anchorDate)
            : [];

        $upcomingEvents = $formattedEvents
            ->sortBy('start_date_sort')
            ->take(6)
            ->map(fn (array $event) => [
                'id' => $event['id'],
                'name' => $event['name'],
                'date_label' => $event['date_label'],
                'organized_by' => $event['organized_by'],
                'venue_label' => $event['venue_label'],
            ])
            ->values()
            ->all();

        return [
            'viewMode' => $viewMode,
            'month' => $anchorDate->copy(),
            'monthInput' => $monthInput,
            'weekInput' => $weekInput,
            'yearInput' => $yearInput,
            'periodTitle' => $periodTitle,
            'periodRangeLabel' => $periodRangeLabel,
            'periodLabel' => ucfirst($viewMode).' View',
            'prevQuery' => $prevQuery,
            'nextQuery' => $nextQuery,
            'switchQueries' => $switchQueries,
            'jumpInputType' => $jumpInputType,
            'jumpInputName' => $jumpInputName,
            'jumpInputValue' => $jumpInputValue,
            'jumpLabel' => $jumpLabel,
            'embedQuery' => $this->activeQuery($viewMode, $monthInput, $weekInput, $yearInput),
            'totalEvents' => $formattedEvents->count(),
            'activeDays' => count($eventsByDay),
            'projectCount' => $formattedEvents
                ->pluck('organizer')
                ->filter()
                ->unique()
                ->count(),
            'upcomingEvents' => $upcomingEvents,
            'weeks' => $weeks,
            'yearMonths' => $yearMonths,
        ];
    }

    private function viewMode(Request $request): string
    {
        $viewMode = strtolower(trim((string) $request->query('view', 'month')));

        return in_array($viewMode, ['week', 'month', 'year'], true) ? $viewMode : 'month';
    }

    private function periodConfig(string $viewMode, string $monthInput, string $weekInput, string $yearInput): array
    {
        return match ($viewMode) {
            'week' => $this->weekConfig($weekInput),
            'year' => $this->yearConfig($yearInput),
            default => $this->monthConfig($monthInput),
        };
    }

    private function monthConfig(string $monthInput): array
    {
        try {
            $anchorDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            $anchorDate = now()->startOfMonth();
            $monthInput = $anchorDate->format('Y-m');
        }

        $periodStart = $anchorDate->copy()->startOfMonth();
        $periodEnd = $anchorDate->copy()->endOfMonth();

        return [
            'anchorDate' => $anchorDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'gridStart' => $anchorDate->copy()->startOfWeek(Carbon::MONDAY),
            'gridEnd' => $anchorDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            'periodTitle' => $anchorDate->format('F Y'),
            'periodRangeLabel' => $periodStart->format('M j').' - '.$periodEnd->format('M j, Y'),
            'prevQuery' => ['view' => 'month', 'month' => $anchorDate->copy()->subMonth()->format('Y-m')],
            'nextQuery' => ['view' => 'month', 'month' => $anchorDate->copy()->addMonth()->format('Y-m')],
            'switchQueries' => [
                'week' => ['view' => 'week', 'week' => $anchorDate->copy()->startOfWeek(Carbon::MONDAY)->format('o-\WW')],
                'month' => ['view' => 'month', 'month' => $anchorDate->format('Y-m')],
                'year' => ['view' => 'year', 'year' => $anchorDate->format('Y')],
            ],
            'jumpInputType' => 'month',
            'jumpInputName' => 'month',
            'jumpInputValue' => $anchorDate->format('Y-m'),
            'jumpLabel' => 'Month',
        ];
    }

    private function weekConfig(string $weekInput): array
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekInput, $matches) === 1) {
            try {
                $anchorDate = now()->setISODate((int) $matches[1], (int) $matches[2])->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {
                $anchorDate = now()->startOfWeek(Carbon::MONDAY);
            }
        } else {
            $anchorDate = now()->startOfWeek(Carbon::MONDAY);
        }

        $periodStart = $anchorDate->copy()->startOfWeek(Carbon::MONDAY);
        $periodEnd = $anchorDate->copy()->endOfWeek(Carbon::SUNDAY);

        return [
            'anchorDate' => $anchorDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'gridStart' => $periodStart->copy(),
            'gridEnd' => $periodEnd->copy(),
            'periodTitle' => 'Week of '.$periodStart->format('M j, Y'),
            'periodRangeLabel' => $periodStart->format('M j').' - '.$periodEnd->format('M j, Y'),
            'prevQuery' => ['view' => 'week', 'week' => $anchorDate->copy()->subWeek()->format('o-\WW')],
            'nextQuery' => ['view' => 'week', 'week' => $anchorDate->copy()->addWeek()->format('o-\WW')],
            'switchQueries' => [
                'week' => ['view' => 'week', 'week' => $anchorDate->format('o-\WW')],
                'month' => ['view' => 'month', 'month' => $anchorDate->format('Y-m')],
                'year' => ['view' => 'year', 'year' => $anchorDate->format('Y')],
            ],
            'jumpInputType' => 'week',
            'jumpInputName' => 'week',
            'jumpInputValue' => $anchorDate->format('o-\WW'),
            'jumpLabel' => 'Week',
        ];
    }

    private function yearConfig(string $yearInput): array
    {
        $year = ctype_digit($yearInput) ? (int) $yearInput : (int) now()->format('Y');
        $year = max(2000, min(2100, $year));
        $anchorDate = Carbon::create($year, 1, 1)->startOfYear();
        $periodStart = $anchorDate->copy()->startOfYear();
        $periodEnd = $anchorDate->copy()->endOfYear();

        return [
            'anchorDate' => $anchorDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'gridStart' => $periodStart->copy(),
            'gridEnd' => $periodEnd->copy(),
            'periodTitle' => (string) $year,
            'periodRangeLabel' => $periodStart->format('M j').' - '.$periodEnd->format('M j, Y'),
            'prevQuery' => ['view' => 'year', 'year' => $year - 1],
            'nextQuery' => ['view' => 'year', 'year' => $year + 1],
            'switchQueries' => [
                'week' => ['view' => 'week', 'week' => $anchorDate->copy()->startOfWeek(Carbon::MONDAY)->format('o-\WW')],
                'month' => ['view' => 'month', 'month' => $anchorDate->format('Y-m')],
                'year' => ['view' => 'year', 'year' => $anchorDate->format('Y')],
            ],
            'jumpInputType' => 'number',
            'jumpInputName' => 'year',
            'jumpInputValue' => $anchorDate->format('Y'),
            'jumpLabel' => 'Year',
        ];
    }

    private function activeQuery(string $viewMode, string $monthInput, string $weekInput, string $yearInput): array
    {
        return match ($viewMode) {
            'week' => ['view' => 'week', 'week' => $weekInput],
            'year' => ['view' => 'year', 'year' => $yearInput],
            default => ['view' => 'month', 'month' => $monthInput],
        };
    }

    private function eventPayload(TrainingEvent $event): ?array
    {
        try {
            $startDate = Carbon::parse((string) $event->start_date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        try {
            $endDate = $event->end_date ? Carbon::parse((string) $event->end_date)->startOfDay() : $startDate->copy();
        } catch (\Throwable) {
            $endDate = $startDate->copy();
        }

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        return [
            'id' => $event->id,
            'name' => $event->event_name ?: ($event->training?->title ?: 'Training Event'),
            'training' => $event->training?->title,
            'organizer' => $this->projectName($event),
            'organized_by' => $this->organizedByName($event),
            'status' => $event->status,
            'status_label' => $event->status ? str_replace('_', ' ', ucfirst((string) $event->status)) : 'Planned',
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'date_label' => $this->dateLabel($event),
            'training_city' => $event->training_city,
            'course_venue' => $event->course_venue,
            'venue_label' => $this->venueLabel($event),
            'start_date_carbon' => $startDate,
            'end_date_carbon' => $endDate,
            'start_date_sort' => $startDate->format('Y-m-d'),
        ];
    }

    private function eventsByDay(Collection $formattedEvents, Carbon $gridStart, Carbon $gridEnd): array
    {
        $eventsByDay = [];

        foreach ($formattedEvents as $event) {
            $rangeStart = $event['start_date_carbon']->copy()->greaterThan($gridStart) ? $event['start_date_carbon']->copy() : $gridStart->copy();
            $rangeEnd = $event['end_date_carbon']->copy()->lessThan($gridEnd) ? $event['end_date_carbon']->copy() : $gridEnd->copy();

            for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addDay()) {
                $dayKey = $cursor->toDateString();
                $eventsByDay[$dayKey] ??= [];
                $eventsByDay[$dayKey][] = $event;
            }
        }

        return $eventsByDay;
    }

    private function buildWeeks(array $eventsByDay, Carbon $gridStart, Carbon $gridEnd, string $viewMode, Carbon $anchorDate): array
    {
        if ($viewMode === 'year') {
            return [];
        }

        $weeks = [];
        $week = [];
        $monthNumber = $anchorDate->month;

        for ($cursor = $gridStart->copy(); $cursor->lte($gridEnd); $cursor->addDay()) {
            $dayKey = $cursor->toDateString();
            $week[] = [
                'date' => $cursor->copy(),
                'date_key' => $dayKey,
                'day' => (int) $cursor->format('j'),
                'in_month' => $viewMode === 'week' ? true : $cursor->month === $monthNumber,
                'is_today' => $cursor->isToday(),
                'is_weekend' => $cursor->isWeekend(),
                'events' => $eventsByDay[$dayKey] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    private function buildYearMonths(Collection $formattedEvents, Carbon $anchorDate): array
    {
        $months = [];

        for ($cursor = $anchorDate->copy()->startOfYear(); $cursor->year === $anchorDate->year; $cursor->addMonth()) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $monthEvents = $formattedEvents
                ->filter(fn (array $event) => $event['start_date_carbon']->lte($monthEnd) && $event['end_date_carbon']->gte($monthStart))
                ->values();

            $eventsByDay = $this->eventsByDay($monthEvents, $monthStart, $monthEnd);

            $months[] = [
                'label' => $cursor->format('F'),
                'short_label' => $cursor->format('M'),
                'month_input' => $cursor->format('Y-m'),
                'total_events' => $monthEvents->count(),
                'active_days' => count($eventsByDay),
                'preview_events' => $monthEvents
                    ->take(3)
                    ->map(fn (array $event) => [
                        'id' => $event['id'],
                        'name' => $event['name'],
                        'date_label' => $event['date_label'],
                        'organized_by' => $event['organized_by'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $months;
    }

    private function projectName(TrainingEvent $event): ?string
    {
        return $event->trainingOrganizer?->project_name ?: $event->trainingOrganizer?->title;
    }

    private function organizedByName(TrainingEvent $event): ?string
    {
        if ($event->organizer_type === 'Subawardee') {
            return $event->projectSubawardee?->subawardee_name ?: $this->projectName($event);
        }

        return $this->projectName($event);
    }

    private function dateLabel(TrainingEvent $event): string
    {
        try {
            $startDate = Carbon::parse((string) $event->start_date);
        } catch (\Throwable) {
            return 'Date not set';
        }

        try {
            $endDate = $event->end_date ? Carbon::parse((string) $event->end_date) : null;
        } catch (\Throwable) {
            $endDate = null;
        }

        if (! $endDate || $startDate->isSameDay($endDate)) {
            return $startDate->format('M j, Y');
        }

        if ($startDate->isSameMonth($endDate) && $startDate->isSameYear($endDate)) {
            return $startDate->format('M j').' - '.$endDate->format('j, Y');
        }

        return $startDate->format('M j, Y').' - '.$endDate->format('M j, Y');
    }

    private function venueLabel(TrainingEvent $event): string
    {
        $parts = array_values(array_filter([
            $event->training_city,
            $event->course_venue,
        ]));

        return $parts !== [] ? implode(' | ', $parts) : 'Venue not set';
    }
}
