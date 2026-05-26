@extends('layouts.admin')

@section('eyebrow', 'Training Events')
@section('title', 'Grouped Training')
@section('subtitle', 'Training events grouped only by training title.')

@section('actions')
    <a href="{{ route('admin.training-events.grouped') }}" class="btn btn-outline-secondary">Grouped Events</a>
    <a href="{{ route('admin.training-events.index') }}" class="btn btn-outline-secondary">Back to Training Events</a>
@endsection

@section('content')
<div class="panel p-4">
    <form method="GET" action="{{ route('admin.training-events.grouped-training') }}" class="row g-3 mb-4">
        <div class="col-md-10">
            <input type="text" class="form-control" name="q" value="{{ $query }}" placeholder="Search training title">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Training Title</th>
                    <th>Events</th>
                    <th>Participants</th>
                    <th>Avg Final Score</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th class="text-end">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groupedTrainings as $group)
                    @php($collapseId = 'grouped-training-'.$group['group_key'])
                    <tr>
                        <td class="fw-semibold">{{ $group['training_title'] }}</td>
                        <td>{{ number_format((int) $group['events_count']) }}</td>
                        <td>{{ number_format((int) $group['participants_total']) }}</td>
                        <td>{{ $group['avg_final_score'] !== null ? number_format((float) $group['avg_final_score'], 1) : '-' }}</td>
                        <td>
                            @php($start = $group['start_date_min'] ? \Illuminate\Support\Carbon::parse($group['start_date_min'])->format('Y-m-d') : '-')
                            @php($end = $group['end_date_max'] ? \Illuminate\Support\Carbon::parse($group['end_date_max'])->format('Y-m-d') : '-')
                            {{ $start }} to {{ $end }}
                        </td>
                        <td>
                            @if(! empty($group['statuses']))
                                {{ collect($group['statuses'])->join(', ') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                View Events
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" class="bg-light p-0">
                            <div class="collapse" id="{{ $collapseId }}">
                                <div class="p-2">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Event</th>
                                                    <th>Project Name</th>
                                                    <th>Organized By</th>
                                                    <th>Training City/Town</th>
                                                    <th>Course Venue</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                    <th>Status</th>
                                                    <th>Participants</th>
                                                    <th>Avg Final</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($group['events'] as $event)
                                                    <tr>
                                                        <td>#{{ $event->id }}</td>
                                                        <td>{{ $event->event_name ?: 'Event #'.$event->id }}</td>
                                                        <td>{{ data_get($event, 'trainingOrganizer.project_name') ?: data_get($event, 'trainingOrganizer.title') ?: '-' }}</td>
                                                        <td>
                                                            @if($event->organizer_type === 'Subawardee')
                                                                {{ data_get($event, 'projectSubawardee.subawardee_name') ?: '-' }}
                                                            @else
                                                                {{ data_get($event, 'trainingOrganizer.project_name') ?: data_get($event, 'trainingOrganizer.title') ?: '-' }}
                                                            @endif
                                                        </td>
                                                        <td>{{ $event->training_city ?: '-' }}</td>
                                                        <td>{{ $event->course_venue ?: '-' }}</td>
                                                        <td>{{ $event->start_date ? \Illuminate\Support\Carbon::parse($event->start_date)->format('Y-m-d') : '-' }}</td>
                                                        <td>{{ $event->end_date ? \Illuminate\Support\Carbon::parse($event->end_date)->format('Y-m-d') : '-' }}</td>
                                                        <td>{{ $event->status ?: '-' }}</td>
                                                        <td>{{ number_format((int) ($event->enrollments_count ?? 0)) }}</td>
                                                        <td>{{ $event->avg_final_score !== null ? number_format((float) $event->avg_final_score, 1) : '-' }}</td>
                                                        <td class="text-end">
                                                            @if(auth()->user()->hasPermission('training_events.update'))
                                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.training-events.edit', $event->getKey()) }}">Edit</a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="fw-semibold mb-2">Participants grouped by training</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Participant</th>
                                                    <th>Final Score</th>
                                                    <th>Events</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($group['participants'] as $participant)
                                                    <tr>
                                                        <td>{{ $participant['participant_name'] }}</td>
                                                        <td>{{ $participant['final_score'] !== null ? $participant['final_score'] : '-' }}</td>
                                                        <td>{{ implode(', ', $participant['event_names']) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-secondary">No participants found for this training group.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">No grouped training records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
