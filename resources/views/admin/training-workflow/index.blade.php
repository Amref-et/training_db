@extends('layouts.admin')

@php($activeStep = max(1, min(4, (int) request('step', 1))))

@section('eyebrow', 'Workflow')
@section('title', 'Training Workflow Management')
@section('subtitle', 'Step-by-step flow for event setup, participant enrollment, workshops, and reporting.')
@section('uses_charts', '1')

@section('actions')
    <a href="{{ route('admin.training-events.index') }}" class="btn btn-outline-secondary">Training Events</a>
    <a href="{{ route('admin.training-events.grouped') }}" class="btn btn-outline-secondary">Grouped View</a>
@endsection

@section('content')
<div class="panel p-4 mb-4">
    <form method="GET" action="{{ route('admin.training-workflow.index') }}" class="row g-3 align-items-end">
        <div class="col-lg-8">
            <label class="form-label">Selected Training Event</label>
            <select name="event_id" class="form-select">
                <option value="">Select an event</option>
                @foreach($events as $event)
                    <option value="{{ $event->id }}" @selected(optional($selectedEvent)->id === $event->id)>
                        {{ $event->event_name ?: 'Event #'.$event->id }} | {{ $event->training?->title ?: 'No training' }} | {{ $event->start_date ?: 'No start date' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <input type="hidden" name="step" value="{{ $activeStep }}">
            <input type="hidden" name="workshop" value="{{ $selectedWorkshop }}">
            <button type="submit" class="btn btn-dark w-100">Load Event</button>
        </div>
        <div class="col-lg-2">
            <a href="{{ route('admin.training-workflow.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

<div class="panel p-3 mb-4">
    <ul class="nav nav-tabs">
        @foreach($stepStatus as $stepNumber => $step)
            <li class="nav-item me-1 mb-1">
                <a
                    href="{{ route('admin.training-workflow.index', ['event_id' => optional($selectedEvent)->id, 'step' => $stepNumber, 'workshop' => $selectedWorkshop]) }}"
                    class="nav-link {{ $activeStep === $stepNumber ? 'active' : '' }}"
                >
                    Step {{ $stepNumber }}: {{ $step['title'] }}
                    <span class="ms-2 badge {{ $step['complete'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $step['complete'] ? 'Complete' : 'Pending' }}
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</div>

@if($activeStep === 1)
<div class="panel p-4 mb-4" id="step-1">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Step 1: Create Training Event</h2>
    </div>
    <form method="POST" action="{{ route('admin.training-workflow.events.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6">
            <label class="form-label">Event Name</label>
            <input type="text" name="event_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Training</label>
            <select name="training_id" class="form-select" required>
                <option value="">Select training</option>
                @foreach($trainings as $training)
                    <option value="{{ $training->id }}">{{ $training->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Training Organizer</label>
            <select name="training_organizer_id" class="form-select" required>
                <option value="">Select organizer</option>
                @foreach($organizers as $organizer)
                    <option value="{{ $organizer->id }}">{{ $organizer->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Training Region</label>
            <select name="training_region_id" class="form-select">
                <option value="">Select region</option>
                @foreach($regions as $region)
                    <option value="{{ $region->id }}">{{ $region->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Training City/Town</label>
            <input type="text" name="training_city" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Course Venue</label>
            <input type="text" name="course_venue" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="Pending">Pending</option>
                <option value="Ongoing">Ongoing</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-dark">Create Event and Continue</button>
        </div>
    </form>
</div>
@endif

@if($activeStep === 2)
<div class="panel p-4 mb-4" id="step-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Step 2: Participant Enrollment</h2>
    </div>

    @if(!$selectedEvent)
        <div class="alert alert-warning mb-0">Select or create an event first.</div>
    @else
        @php($enrolledParticipantIds = $enrollments->pluck('participant_id')->all())
        @php($availableParticipants = $participantsForEnrollment->reject(fn ($participant) => in_array($participant->id, $enrolledParticipantIds, true))->values())
        @php($bulkParticipantSelectId = 'workflow-bulk-participant-select')

        <form method="POST" action="{{ route('admin.training-workflow.enrollments.store', $selectedEvent) }}" class="row g-3 mb-4">
            @csrf
            <div class="col-md-9">
                <label class="form-label">Bulk Participant Enrollment</label>
                <input
                    type="text"
                    class="form-control mb-2 js-workflow-participant-search"
                    data-target="{{ $bulkParticipantSelectId }}"
                    placeholder="Search participant by name or ID"
                    autocomplete="off"
                    @disabled($availableParticipants->isEmpty())
                >
                <div id="{{ $bulkParticipantSelectId }}-selected" class="d-flex flex-wrap gap-2 mb-2 js-workflow-selected-chips" data-target="{{ $bulkParticipantSelectId }}"></div>
                <select id="{{ $bulkParticipantSelectId }}" name="participant_ids[]" class="form-select" multiple size="8" @disabled($availableParticipants->isEmpty())>
                    @foreach($availableParticipants as $participant)
                        <option value="{{ $participant->id }}" @selected(in_array((string) $participant->id, array_map('strval', old('participant_ids', [])), true))>{{ ($participant->participant_code ? $participant->participant_code.' - ' : '') . $participant->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Type to filter participants, select multiple, and remove selected ones from chips using X.</div>
            </div>
            <div class="col-md-3 d-grid">
                <label class="form-label text-transparent">.</label>
                <button type="submit" class="btn btn-outline-dark" @disabled($availableParticipants->isEmpty())>Enroll Selected Participants</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Participant ID</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->participant?->name ?: 'Participant #'.$enrollment->participant_id }}</td>
                            <td>{{ $enrollment->participant?->participant_code ?: 'N/A' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.training-workflow.enrollments.destroy', [$selectedEvent, $enrollment]) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this participant from the event?')">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-secondary">No participants enrolled yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif

@if($activeStep === 3)
<div class="panel p-4 mb-4" id="step-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Step 3: Workshop Scores</h2>
    </div>

    @if(!$selectedEvent)
        <div class="alert alert-warning mb-0">Select or create an event first.</div>
    @elseif($enrollments->isEmpty())
        <div class="alert alert-warning mb-0">Enroll at least one participant before entering workshop scores.</div>
    @else
        <form method="POST" action="{{ route('admin.training-workflow.workshop-count.store', $selectedEvent) }}" class="row g-3 align-items-end mb-4">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Number of Workshops</label>
                <input type="number" name="workshop_count" class="form-control" min="1" max="20" value="{{ old('workshop_count', $workshopCount ?: 4) }}" required>
                <div class="form-text">Set total workshops, then the score structure and report are built from this number.</div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-dark w-100">Create/Update Structure</button>
            </div>
        </form>

        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach(range(1, max(1, (int) $workshopCount)) as $workshopNumber)
                @php($progress = $workshopProgress[$workshopNumber] ?? ['completed' => 0, 'total' => 0, 'is_complete' => false])
                <a
                    href="{{ route('admin.training-workflow.index', ['event_id' => $selectedEvent->id, 'step' => 3, 'workshop' => $workshopNumber]) }}"
                    class="btn {{ $selectedWorkshop === $workshopNumber ? 'btn-dark' : 'btn-outline-secondary' }}"
                >
                    Workshop {{ $workshopNumber }} ({{ $progress['completed'] }}/{{ $progress['total'] }})
                </a>
            @endforeach
        </div>

        <div class="row g-3 align-items-end mb-3">
            <div class="col-md-4">
                <form method="GET" action="{{ route('admin.training-workflow.workshops.export', $selectedEvent) }}">
                    <input type="hidden" name="workshop" value="{{ $selectedWorkshop }}">
                    <button type="submit" class="btn btn-outline-secondary w-100">Export Workshop {{ $selectedWorkshop }} CSV</button>
                </form>
            </div>
            <div class="col-md-8">
                <form method="POST" action="{{ route('admin.training-workflow.workshops.import', $selectedEvent) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="workshop_number" value="{{ $selectedWorkshop }}">
                    <div class="col-md-8">
                        <label class="form-label">Import Workshop {{ $selectedWorkshop }} Scores (CSV)</label>
                        <input type="file" name="score_file" class="form-control" accept=".csv,text/csv" required>
                        <div class="form-text">Use the exported CSV format (participant_id or participant_code with pre/mid/post score columns).</div>
                    </div>
                    <div class="col-md-4 d-grid">
                        <label class="form-label text-transparent">.</label>
                        <button type="submit" class="btn btn-outline-dark">Import CSV</button>
                    </div>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.training-workflow.workshops.save', $selectedEvent) }}">
            @csrf
            <input type="hidden" name="workshop_number" value="{{ $selectedWorkshop }}">
            <div class="table-responsive">
                <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Participant ID</th>
                        <th>Pre-test Score (%)</th>
                            <th>Mid-test Score (%) (Optional)</th>
                            <th>Post-test Score (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $enrollment)
                            @php($score = $enrollment->workshopScores->firstWhere('workshop_number', $selectedWorkshop))
                            <tr>
                                <td>{{ $enrollment->participant?->name ?: 'Participant #'.$enrollment->participant_id }}</td>
                                <td>{{ $enrollment->participant?->participant_code ?: 'N/A' }}</td>
                                <td>
                                    <input
                                        type="number"
                                        name="pre_scores[{{ $enrollment->id }}]"
                                        class="form-control"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value="{{ old('pre_scores.'.$enrollment->id, $score?->pre_test_score) }}"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        name="mid_scores[{{ $enrollment->id }}]"
                                        class="form-control"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value="{{ old('mid_scores.'.$enrollment->id, $score?->mid_test_score) }}"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        name="post_scores[{{ $enrollment->id }}]"
                                        class="form-control"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value="{{ old('post_scores.'.$enrollment->id, $score?->post_test_score) }}"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-dark">Save Workshop {{ $selectedWorkshop }} Scores</button>
        </form>
    @endif
</div>
@endif

@if($activeStep === 4)
<div class="panel p-4" id="step-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Step 4: Report</h2>
        @if($selectedEvent)
            <a href="{{ route('admin.training-workflow.report.export', $selectedEvent) }}" class="btn btn-outline-secondary">Export Full Participant Report (CSV)</a>
        @endif
    </div>

    @if(!$selectedEvent)
        <div class="alert alert-warning mb-0">Select or create an event first.</div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="metric-card p-3">
                    <div class="section-title">Participants</div>
                    <div class="metric-value">{{ $reportSummary['participants_count'] }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card p-3">
                    <div class="section-title">Average Pre-test Score</div>
                    <div class="metric-value">{{ $reportSummary['avg_pre_score'] !== null ? number_format((float) $reportSummary['avg_pre_score'], 2) : 'N/A' }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card p-3">
                    <div class="section-title">Average Post-test Score</div>
                    <div class="metric-value">{{ $reportSummary['avg_post_score'] !== null ? number_format((float) $reportSummary['avg_post_score'], 2) : 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="panel p-3 mb-4">
            <h3 class="h6 mb-3">Average Pre/Post Score Chart</h3>
            <div style="position: relative; width: 100%; height: 320px;">
                <canvas id="finalScoreChart"></canvas>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Workshop</th>
                        <th>Average Pre-test Score</th>
                        <th>Average Post-test Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportWorkshopAverages as $row)
                        <tr>
                            <td>Workshop {{ $row['workshop_number'] }}</td>
                            <td>{{ $row['avg_pre_score'] !== null ? number_format((float) $row['avg_pre_score'], 2) : 'N/A' }}</td>
                            <td>{{ $row['avg_post_score'] !== null ? number_format((float) $row['avg_post_score'], 2) : 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-secondary">No score data available for reporting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <h3 class="h6 mb-3">Participant Score List</h3>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Participant</th>
                            <th>Participant ID</th>
                            <th>Pre-test Score (Avg)</th>
                            <th>Post-test Score (Avg)</th>
                            <th>Final Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportParticipantScores as $row)
                            <tr>
                                <td>{{ $row['participant_name'] }}</td>
                                <td>{{ $row['participant_code'] ?? 'N/A' }}</td>
                                <td>{{ $row['avg_pre_score'] !== null ? number_format((float) $row['avg_pre_score'], 2) : 'N/A' }}</td>
                                <td>{{ $row['avg_post_score'] !== null ? number_format((float) $row['avg_post_score'], 2) : 'N/A' }}</td>
                                <td>{{ $row['final_score'] !== null ? number_format((float) $row['final_score'], 2) : 'Pending' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-secondary">No participant score data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endif
@endsection

@section('scripts')
@if($selectedEvent && ($reportSummary['avg_pre_score'] !== null || $reportSummary['avg_post_score'] !== null))
<script>
    (() => {
        const labels = ['Average Pre-test', 'Average Post-test'];
        const averageScores = @json([
            $reportSummary['avg_pre_score'] !== null ? (float) $reportSummary['avg_pre_score'] : 0,
            $reportSummary['avg_post_score'] !== null ? (float) $reportSummary['avg_post_score'] : 0,
        ]);
        const ctx = document.getElementById('finalScoreChart');

        if (!ctx || typeof Chart === 'undefined') {
            return;
        }

        const existingChart = Chart.getChart(ctx);
        if (existingChart) {
            existingChart.destroy();
        }

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Average Score (%)',
                    data: averageScores,
                    backgroundColor: [
                        'rgba(13, 110, 253, 0.72)',
                        'rgba(25, 135, 84, 0.72)'
                    ],
                    borderColor: [
                        'rgba(13, 110, 253, 1)',
                        'rgba(25, 135, 84, 1)'
                    ],
                    borderWidth: 1.2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        title: { display: true, text: 'Score (%)' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    })();
</script>
@endif
@if($activeStep === 2 && $selectedEvent)
<script>
    (() => {
        const searchInputs = document.querySelectorAll('.js-workflow-participant-search');
        if (!searchInputs.length) {
            return;
        }

        searchInputs.forEach((input) => {
            const targetId = input.getAttribute('data-target');
            if (!targetId) {
                return;
            }

            const select = document.getElementById(targetId);
            if (!select) {
                return;
            }

            const chipsWrap = document.querySelector(`.js-workflow-selected-chips[data-target="${targetId}"]`);
            const originalOptions = Array.from(select.options).map((option, index) => ({
                index,
                value: option.value,
                text: option.text,
                normalizedText: (option.text || '').toLowerCase(),
            }));

            const renderOptions = () => {
                const term = (input.value || '').trim().toLowerCase();
                const selectedValues = new Set(Array.from(select.selectedOptions).map((option) => option.value));
                const currentValue = select.multiple ? null : select.value;

                select.innerHTML = '';

                originalOptions.forEach((option) => {
                    const isPlaceholder = option.index === 0 && option.value === '';
                    const matches = term === '' || option.normalizedText.includes(term);
                    const keep = isPlaceholder || matches || selectedValues.has(option.value) || (!select.multiple && currentValue === option.value);
                    if (!keep) {
                        return;
                    }

                    const node = document.createElement('option');
                    node.value = option.value;
                    node.textContent = option.text;
                    if (select.multiple) {
                        node.selected = selectedValues.has(option.value);
                    } else {
                        node.selected = currentValue === option.value;
                    }

                    select.appendChild(node);
                });
            };

            const enableClickOnlyMultiSelect = () => {
                if (!select.multiple || select.dataset.clickOnlyEnabled === '1') {
                    return;
                }

                select.dataset.clickOnlyEnabled = '1';
                select.addEventListener('mousedown', (event) => {
                    const option = event.target;
                    if (!(option instanceof HTMLOptionElement)) {
                        return;
                    }

                    event.preventDefault();
                    option.selected = !option.selected;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            };

            const renderChips = () => {
                if (!chipsWrap || !select.multiple) {
                    return;
                }

                chipsWrap.innerHTML = '';
                const selectedOptions = Array.from(select.selectedOptions).filter((option) => option.value !== '');
                selectedOptions.forEach((selectedOption) => {
                    const chip = document.createElement('span');
                    chip.className = 'badge text-bg-light border d-inline-flex align-items-center gap-2 py-2 px-2';
                    chip.textContent = selectedOption.text;

                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'btn btn-sm p-0 border-0 bg-transparent text-danger fw-bold';
                    removeButton.textContent = 'X';
                    removeButton.setAttribute('aria-label', `Remove ${selectedOption.text}`);
                    removeButton.addEventListener('click', () => {
                        const optionToRemove = Array.from(select.options).find((option) => option.value === selectedOption.value);
                        if (optionToRemove) {
                            optionToRemove.selected = false;
                        }

                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    chip.appendChild(removeButton);
                    chipsWrap.appendChild(chip);
                });
            };

            input.addEventListener('input', renderOptions);
            select.addEventListener('change', () => {
                renderOptions();
                renderChips();
            });
            enableClickOnlyMultiSelect();
            renderOptions();
            renderChips();
        });
    })();
</script>
@endif
@endsection
