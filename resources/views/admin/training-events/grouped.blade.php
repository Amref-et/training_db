@extends('layouts.admin')

@section('eyebrow', 'Learning Outcomes')
@section('title', 'Grouped Training Events')
@section('subtitle', 'One row per event. Click an event row to expand participant final scores.')

@section('actions')
    <a href="{{ route('admin.training-events.index') }}" class="btn btn-outline-secondary">Back to Training Events</a>
@endsection

@section('content')
<div class="panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Training</th>
                    <th>Organizer</th>
                    <th>Date Range</th>
                    <th>Status</th>
                    <th>Participants</th>
                    <th>Avg Final Score</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groupedEvents as $group)
                    <tr>
                        <td>
                            <button
                                class="btn btn-link p-0 text-start fw-semibold text-decoration-none"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#event-details-{{ $loop->index }}"
                                aria-expanded="false"
                                aria-controls="event-details-{{ $loop->index }}"
                            >
                                {{ $group['event_name'] }}
                            </button>
                        </td>
                        <td>{{ $group['training_title'] }}</td>
                        <td>{{ $group['organizer_title'] }}</td>
                        <td>
                            @if($group['start_date'] || $group['end_date'])
                                {{ $group['start_date'] ?: '—' }} - {{ $group['end_date'] ?: '—' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $group['status'] }}</td>
                        <td>{{ $group['participant_count'] }}</td>
                        <td>{{ $group['average_final_score'] ?? '—' }}</td>
                    </tr>
                    <tr id="event-details-{{ $loop->index }}" class="collapse">
                        <td colspan="7">
                            <div class="border rounded-3 p-3 bg-light-subtle">
                                <div class="fw-semibold mb-2">Participants and Final Scores</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Participant</th>
                                                <th>Final Score (Avg Configured Workshop Post-tests)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($group['participants'] as $participant)
                                                <tr>
                                                    <td>{{ $participant['participant_name'] }}</td>
                                                    <td>{{ $participant['final_score'] ?? 'Pending (needs '.$group['workshop_count'].' post-test scores)' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="text-secondary">No participants found for this event.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">No training event groups found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
