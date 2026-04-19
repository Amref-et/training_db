@extends('layouts.admin')

@section('eyebrow', 'Records')
@section('title', $config['label'])
@section('subtitle', 'Grouped by Event, Training, and Project Name with collapsible details.')

@section('actions')
@if(auth()->user()->hasPermission($config['permission'].'.create'))
    <a href="{{ route('admin.'.$config['path'].'.create') }}" class="btn btn-dark">Add {{ $config['singular'] }}</a>
@endif
@endsection

@section('content')
<div class="panel p-4">
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-10">
            <input type="text" class="form-control" name="q" value="{{ $query }}" placeholder="Search {{ strtolower($config['label']) }}">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Training</th>
                    <th>Project Name</th>
                    <th>Organized By</th>
                    <th>Events</th>
                    <th>Participants</th>
                    <th>Avg Final Score</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $group)
                    @php($collapseId = 'training-event-group-'.$group['group_key'])
                    <tr>
                        <td>{{ $group['event_name'] ?: '-' }}</td>
                        <td>{{ $group['training_title'] ?: '-' }}</td>
                        <td>{{ $group['organizer_title'] ?: '-' }}</td>
                        <td>{{ $group['organized_by'] ?: '-' }}</td>
                        <td>{{ $group['events_count'] }}</td>
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
                            <div class="d-inline-flex gap-2">
                                @if(auth()->user()->hasPermission($config['permission'].'.update') && count($group['events']) === 1)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.'.$config['path'].'.edit', $group['events'][0]->getKey()) }}">Edit</a>
                                @endif
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                    {{ count($group['events']) === 1 ? 'View Details' : 'View / Edit Events' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="10" class="bg-light p-0">
                            <div class="collapse" id="{{ $collapseId }}">
                                <div class="p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Project Name</th>
                                                <th>Organized By</th>
                                                <th>Training Region</th>
                                                <th>Training City/Town</th>
                                                <th>Course Venue</th>
                                                <th>Workshops</th>
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
                                                    <td>{{ data_get($event, 'trainingOrganizer.project_name') ?: data_get($event, 'trainingOrganizer.title') ?: '-' }}</td>
                                                    <td>
                                                        @if($event->organizer_type === 'Subawardee')
                                                            {{ data_get($event, 'projectSubawardee.subawardee_name') ?: '-' }}
                                                        @else
                                                            {{ data_get($event, 'trainingOrganizer.project_name') ?: data_get($event, 'trainingOrganizer.title') ?: '-' }}
                                                        @endif
                                                    </td>
                                                    <td>{{ data_get($event, 'trainingRegion.name') ?: '-' }}</td>
                                                    <td>{{ $event->training_city ?: '-' }}</td>
                                                    <td>{{ $event->course_venue ?: '-' }}</td>
                                                    <td>{{ $event->workshop_count ?: '-' }}</td>
                                                    <td>{{ $event->start_date ? \Illuminate\Support\Carbon::parse($event->start_date)->format('Y-m-d') : '-' }}</td>
                                                    <td>{{ $event->end_date ? \Illuminate\Support\Carbon::parse($event->end_date)->format('Y-m-d') : '-' }}</td>
                                                    <td>{{ $event->status ?: '-' }}</td>
                                                    <td>{{ number_format((int) ($event->enrollments_count ?? 0)) }}</td>
                                                    <td>{{ $event->avg_final_score !== null ? number_format((float) $event->avg_final_score, 1) : '-' }}</td>
                                                    <td class="text-end">
                                                        @if(auth()->user()->hasPermission($config['permission'].'.update'))
                                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.'.$config['path'].'.edit', $event->getKey()) }}">Edit</a>
                                                        @endif
                                                        @if(auth()->user()->hasPermission($config['permission'].'.delete'))
                                                            <form method="POST" action="{{ route('admin.'.$config['path'].'.destroy', $event->getKey()) }}" class="d-inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this record?')">Delete</button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-secondary py-4">No records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $records->links() }}
</div>
@endsection
