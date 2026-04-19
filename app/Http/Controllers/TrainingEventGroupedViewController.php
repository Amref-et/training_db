<?php

namespace App\Http\Controllers;

use App\Models\TrainingEvent;
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
                    'workshop_count' => max(1, (int) ($event->workshop_count ?? 4)),
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
}

