<?php

namespace App\Http\Controllers;

use App\Models\TrainingEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingEventGroupedViewController extends Controller
{
    public function index(): View
    {
        $groupedEvents = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'enrollments.participant'])
            ->orderByDesc('start_date')
            ->orderBy('event_name')
            ->get()
            ->map(function (TrainingEvent $event) {
                $participants = $event->enrollments
                    ->sortBy(fn ($enrollment) => mb_strtolower((string) ($enrollment->participant?->name ?? '')))
                    ->values()
                    ->map(fn ($enrollment) => [
                        'participant_name' => $enrollment->participant?->name ?: '-',
                        'final_score' => $enrollment->final_score !== null ? round((float) $enrollment->final_score, 2) : null,
                    ]);

                $avgFinalScore = $participants
                    ->pluck('final_score')
                    ->filter(fn ($score) => $score !== null)
                    ->avg();

                return [
                    'event_name' => $event->event_name ?: ($event->training?->title ?: 'Training Event'),
                    'training_title' => $event->training?->title ?: '-',
                    'organizer_title' => $event->trainingOrganizer?->title ?: '-',
                    'status' => $event->status ?: '-',
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'workshop_count' => max(1, (int) ($event->workshop_count ?? 1)),
                    'participant_count' => $participants->count(),
                    'average_final_score' => $avgFinalScore !== null ? round((float) $avgFinalScore, 2) : null,
                    'participants' => $participants,
                ];
            })
            ->values();

        return view('admin.training-events.grouped', [
            'groupedEvents' => $groupedEvents,
        ]);
    }

    public function byTrainingTitle(Request $request): View
    {
        $searchTerm = $request->string('q')->toString();

        $eventsQuery = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'projectSubawardee', 'enrollments.participant'])
            ->withCount('enrollments')
            ->withCount([
                'enrollments as scored_enrollments_count' => fn ($query) => $query->whereNotNull('final_score'),
            ])
            ->withAvg('enrollments as avg_final_score', 'final_score')
            ->orderByDesc('start_date')
            ->orderBy('event_name');

        if ($searchTerm !== '') {
            $eventsQuery->whereHas('training', function ($query) use ($searchTerm) {
                $query->where('title', 'like', '%'.$searchTerm.'%');
            });
        }

        $groupedTrainings = $eventsQuery
            ->get()
            ->groupBy(fn (TrainingEvent $event) => $this->trainingTitleGroupKey($event->training?->title))
            ->map(function ($events) {
                $firstEvent = $events->first();
                $trainingTitle = trim((string) ($firstEvent?->training?->title ?? ''));

                $participantRows = $events->flatMap(function (TrainingEvent $event) {
                    return $event->enrollments->map(fn ($enrollment) => [
                        'participant_id' => $enrollment->participant_id,
                        'participant_name' => $enrollment->participant?->name ?: '-',
                        'event_name' => $event->event_name ?: 'Event #'.$event->id,
                        'final_score' => $enrollment->final_score !== null ? round((float) $enrollment->final_score, 2) : null,
                    ]);
                });

                $participants = $participantRows
                    ->groupBy('participant_id')
                    ->map(function ($rows) {
                        $firstRow = $rows->first();
                        $scores = $rows->pluck('final_score')->filter(fn ($score) => $score !== null);

                        return [
                            'participant_id' => $firstRow['participant_id'],
                            'participant_name' => $firstRow['participant_name'],
                            'event_names' => $rows->pluck('event_name')->unique()->values()->all(),
                            'final_score' => $scores->count() ? round($scores->avg(), 2) : null,
                        ];
                    })
                    ->sortBy(fn ($row) => mb_strtolower((string) $row['participant_name']))
                    ->values();

                $participantTotal = $participants->count();
                $scoredParticipantTotal = $events->sum(fn (TrainingEvent $event) => (int) ($event->scored_enrollments_count ?? 0));
                $weightedScoreTotal = $events->sum(function (TrainingEvent $event) {
                    $scoredCount = (int) ($event->scored_enrollments_count ?? 0);

                    return $event->avg_final_score !== null ? ((float) $event->avg_final_score * $scoredCount) : 0;
                });

                return [
                    'group_key' => sha1($this->trainingTitleGroupKey($trainingTitle)),
                    'training_title' => $trainingTitle !== '' ? $trainingTitle : 'No training title',
                    'events_count' => $events->count(),
                    'participants_total' => $participantTotal,
                    'avg_final_score' => $scoredParticipantTotal > 0 ? round($weightedScoreTotal / $scoredParticipantTotal, 1) : null,
                    'start_date_min' => $events->pluck('start_date')->filter()->min(),
                    'end_date_max' => $events->pluck('end_date')->filter()->max(),
                    'statuses' => $events->pluck('status')->filter()->unique()->values()->all(),
                    'events' => $events->values(),
                    'participants' => $participants,
                ];
            })
            ->sortBy(fn ($group) => mb_strtolower($group['training_title']))
            ->values();

        return view('admin.training-events.grouped-training', [
            'groupedTrainings' => $groupedTrainings,
            'query' => $searchTerm,
        ]);
    }

    private function trainingTitleGroupKey(?string $trainingTitle): string
    {
        $title = mb_strtolower(trim((string) $trainingTitle));

        return $title !== '' ? $title : 'no-training-title';
    }
}
