<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Region;
use App\Models\Training;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingEventWorkshopScore;
use App\Models\TrainingOrganizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class TrainingWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $selectedEventId = $request->integer('event_id');
        $requestedWorkshop = max(1, $request->integer('workshop', 1));

        $events = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $selectedEvent = null;
        $enrollments = collect();
        $workshopProgress = [];
        $reportWorkshopAverages = collect();
        $reportParticipantScores = collect();
        $workshopCount = 0;
        $selectedWorkshop = 1;
        $reportSummary = [
            'participants_count' => 0,
            'with_final_scores' => 0,
            'avg_final_score' => null,
            'avg_pre_score' => null,
            'avg_post_score' => null,
            'required_workshop_count' => 0,
        ];

        if ($selectedEventId) {
            $selectedEvent = TrainingEvent::query()
                ->with([
                    'training',
                    'trainingOrganizer',
                    'trainingRegion',
                    'enrollments.participant',
                    'enrollments.workshopScores',
                ])
                ->find($selectedEventId);
        }

        if ($selectedEvent) {
            $workshopCount = max(1, (int) ($selectedEvent->workshop_count ?? 4));
            $selectedWorkshop = min($requestedWorkshop, $workshopCount);

            $enrollments = $selectedEvent->enrollments
                ->sortBy(fn (TrainingEventParticipant $enrollment) => mb_strtolower((string) $enrollment->participant?->name))
                ->values();

            $workshopProgress = collect(range(1, $workshopCount))
                ->mapWithKeys(function (int $workshopNumber) use ($enrollments) {
                    $total = $enrollments->count();
                    $completed = $enrollments->filter(function (TrainingEventParticipant $enrollment) use ($workshopNumber) {
                        $score = $enrollment->workshopScores->firstWhere('workshop_number', $workshopNumber);

                        return $score
                            && $score->pre_test_score !== null
                            && $score->post_test_score !== null;
                    })->count();

                    return [$workshopNumber => [
                        'completed' => $completed,
                        'total' => $total,
                        'is_complete' => $total > 0 && $completed === $total,
                    ]];
                })
                ->all();

            $reportWorkshopAverages = TrainingEventWorkshopScore::query()
                ->where('workshop_number', '<=', $workshopCount)
                ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $selectedEvent->id))
                ->selectRaw('workshop_number, AVG(pre_test_score) as avg_pre_score, AVG(post_test_score) as avg_post_score')
                ->groupBy('workshop_number')
                ->orderBy('workshop_number')
                ->get()
                ->map(fn ($row) => [
                    'workshop_number' => (int) $row->workshop_number,
                    'avg_pre_score' => $row->avg_pre_score !== null ? round((float) $row->avg_pre_score, 2) : null,
                    'avg_post_score' => $row->avg_post_score !== null ? round((float) $row->avg_post_score, 2) : null,
                ]);

            $reportParticipantScores = $enrollments
                ->map(function (TrainingEventParticipant $enrollment) use ($workshopCount) {
                    $workshopScores = $enrollment->workshopScores
                        ->where('workshop_number', '<=', $workshopCount)
                        ->values();

                    $avgPre = $workshopScores->whereNotNull('pre_test_score')->avg('pre_test_score');
                    $avgPost = $workshopScores->whereNotNull('post_test_score')->avg('post_test_score');
                    $participantName = (string) ($enrollment->participant?->name ?: 'Participant #'.$enrollment->participant_id);
                    $participantCode = $enrollment->participant?->participant_code;

                    return [
                        'participant_name' => $participantName,
                        'participant_code' => $participantCode,
                        'avg_pre_score' => $avgPre !== null ? round((float) $avgPre, 2) : null,
                        'avg_post_score' => $avgPost !== null ? round((float) $avgPost, 2) : null,
                        'final_score' => $enrollment->final_score !== null ? round((float) $enrollment->final_score, 2) : null,
                    ];
                })
                ->values();

            $overallAveragePre = TrainingEventWorkshopScore::query()
                ->where('workshop_number', '<=', $workshopCount)
                ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $selectedEvent->id))
                ->avg('pre_test_score');

            $overallAveragePost = TrainingEventWorkshopScore::query()
                ->where('workshop_number', '<=', $workshopCount)
                ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $selectedEvent->id))
                ->avg('post_test_score');

            $reportSummary = [
                'participants_count' => $enrollments->count(),
                'with_final_scores' => $enrollments->filter(fn (TrainingEventParticipant $enrollment) => $enrollment->final_score !== null)->count(),
                'avg_final_score' => $enrollments->whereNotNull('final_score')->avg('final_score'),
                'avg_pre_score' => $overallAveragePre !== null ? round((float) $overallAveragePre, 2) : null,
                'avg_post_score' => $overallAveragePost !== null ? round((float) $overallAveragePost, 2) : null,
                'required_workshop_count' => $workshopCount,
            ];
        } else {
            $selectedWorkshop = $requestedWorkshop;
        }

        $stepStatus = [
            1 => ['title' => 'Training Event', 'complete' => (bool) $selectedEvent],
            2 => ['title' => 'Participant Enrollment', 'complete' => $enrollments->count() > 0],
            3 => [
                'title' => 'Workshops',
                'complete' => $workshopCount > 0
                    && ! empty($workshopProgress)
                    && collect($workshopProgress)->every(fn (array $step) => $step['is_complete']),
            ],
            4 => [
                'title' => 'Report',
                'complete' => $reportSummary['participants_count'] > 0
                    && $reportSummary['required_workshop_count'] > 0
                    && $reportSummary['with_final_scores'] === $reportSummary['participants_count'],
            ],
        ];

        $participantsForEnrollment = Participant::query()
            ->orderBy('name')
            ->get();

        return view('admin.training-workflow.index', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'selectedWorkshop' => $selectedWorkshop,
            'workshopCount' => $workshopCount,
            'stepStatus' => $stepStatus,
            'enrollments' => $enrollments,
            'workshopProgress' => $workshopProgress,
            'reportWorkshopAverages' => $reportWorkshopAverages,
            'reportParticipantScores' => $reportParticipantScores,
            'reportSummary' => $reportSummary,
            'participantsForEnrollment' => $participantsForEnrollment,
            'trainings' => Training::query()->orderBy('title')->get(),
            'organizers' => TrainingOrganizer::query()->orderBy('title')->get(),
            'regions' => Region::query()->orderBy('name')->get(),
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event_name' => 'required|string|max:255',
            'training_id' => 'required|exists:trainings,id',
            'training_organizer_id' => 'required|exists:training_organizers,id',
            'training_region_id' => 'nullable|exists:regions,id',
            'training_city' => 'nullable|string|max:255',
            'course_venue' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|string|max:255',
        ]);

        $event = TrainingEvent::query()->create($data);
        $this->audit()->logModelCreated($event, 'Training workflow event created');

        return redirect()
            ->route('admin.training-workflow.index', ['event_id' => $event->id, 'step' => 2])
            ->with('success', 'Training event created. Continue with participant enrollment.');
    }

    public function storeEnrollment(Request $request, TrainingEvent $trainingEvent): RedirectResponse
    {
        $data = $request->validate([
            'participant_id' => 'nullable|integer|exists:participants,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer|exists:participants,id',
        ]);

        $participantIds = collect([$data['participant_id'] ?? null])
            ->merge($data['participant_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($participantIds)) {
            return redirect()
                ->route('admin.training-workflow.index', ['event_id' => $trainingEvent->id, 'step' => 2])
                ->with('error', 'Select at least one participant to enroll.');
        }

        $alreadyEnrolledIds = TrainingEventParticipant::query()
            ->where('training_event_id', $trainingEvent->id)
            ->whereIn('participant_id', $participantIds)
            ->pluck('participant_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $newParticipantIds = array_values(array_diff($participantIds, $alreadyEnrolledIds));

        if (empty($newParticipantIds)) {
            return redirect()
                ->route('admin.training-workflow.index', ['event_id' => $trainingEvent->id, 'step' => 2])
                ->with('error', 'Selected participant(s) are already enrolled in the selected event.');
        }

        foreach ($newParticipantIds as $participantId) {
            TrainingEventParticipant::query()->create([
                'training_event_id' => $trainingEvent->id,
                'participant_id' => $participantId,
            ]);
        }

        $this->audit()->logCustom('Participants enrolled into training event', 'training_workflow.enrollment.created', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => [
                'participant_ids' => $newParticipantIds,
                'already_enrolled_ids' => $alreadyEnrolledIds,
            ],
        ]);

        $message = count($newParticipantIds).' participant(s) enrolled successfully.';

        if (! empty($alreadyEnrolledIds)) {
            $message .= ' '.count($alreadyEnrolledIds).' already enrolled and skipped.';
        }

        return redirect()
            ->route('admin.training-workflow.index', ['event_id' => $trainingEvent->id, 'step' => 2])
            ->with('success', $message.' Continue to workshop scoring.');
    }

    public function destroyEnrollment(TrainingEvent $trainingEvent, TrainingEventParticipant $enrollment): RedirectResponse
    {
        abort_unless($enrollment->training_event_id === $trainingEvent->id, 404);

        $beforeValues = [
            'training_event_id' => $enrollment->training_event_id,
            'participant_id' => $enrollment->participant_id,
            'final_score' => $enrollment->final_score,
        ];
        $enrollmentId = $enrollment->id;
        $enrollment->delete();
        $this->audit()->logCustom('Participant removed from training event', 'training_workflow.enrollment.deleted', [
            'auditable_type' => TrainingEventParticipant::class,
            'auditable_id' => $enrollmentId,
            'auditable_label' => 'Enrollment #'.$enrollmentId,
            'old_values' => $beforeValues,
            'metadata' => [
                'training_event_id' => $trainingEvent->id,
                'training_event_name' => $trainingEvent->event_name,
            ],
        ]);

        return redirect()
            ->route('admin.training-workflow.index', ['event_id' => $trainingEvent->id, 'step' => 2])
            ->with('success', 'Participant removed from the event.');
    }

    public function storeWorkshopCount(Request $request, TrainingEvent $trainingEvent): RedirectResponse
    {
        $beforeState = $this->audit()->snapshotModel($trainingEvent, ['workshop_count']);
        $data = $request->validate([
            'workshop_count' => 'required|integer|min:1|max:20',
        ]);

        $workshopCount = (int) $data['workshop_count'];

        $trainingEvent->update([
            'workshop_count' => $workshopCount,
        ]);

        $this->syncWorkshopStructure($trainingEvent, $workshopCount);
        $trainingEvent->refresh();
        $this->audit()->logModelUpdated($trainingEvent, $beforeState, 'Training event workshop count updated');

        return redirect()
            ->route('admin.training-workflow.index', [
                'event_id' => $trainingEvent->id,
                'step' => 3,
                'workshop' => 1,
            ])
            ->with('success', 'Workshop structure created for '.$workshopCount.' workshop(s).');
    }

    public function saveWorkshopScores(Request $request, TrainingEvent $trainingEvent): RedirectResponse
    {
        $maxWorkshop = max(1, (int) ($trainingEvent->workshop_count ?? 4));

        $data = $request->validate([
            'workshop_number' => 'required|integer|min:1|max:'.$maxWorkshop,
            'pre_scores' => 'array',
            'pre_scores.*' => 'nullable|numeric|min:0|max:100',
            'mid_scores' => 'array',
            'mid_scores.*' => 'nullable|numeric|min:0|max:100',
            'post_scores' => 'array',
            'post_scores.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $workshopNumber = (int) $data['workshop_number'];
        $preScores = collect($data['pre_scores'] ?? []);
        $midScores = collect($data['mid_scores'] ?? []);
        $postScores = collect($data['post_scores'] ?? []);

        $enrollmentIds = TrainingEventParticipant::query()
            ->where('training_event_id', $trainingEvent->id)
            ->pluck('id');

        foreach ($enrollmentIds as $enrollmentId) {
            $pre = $this->toNullableFloat($preScores->get((string) $enrollmentId, $preScores->get($enrollmentId)));
            $mid = $this->toNullableFloat($midScores->get((string) $enrollmentId, $midScores->get($enrollmentId)));
            $post = $this->toNullableFloat($postScores->get((string) $enrollmentId, $postScores->get($enrollmentId)));

            $existing = TrainingEventWorkshopScore::query()
                ->where('training_event_participant_id', $enrollmentId)
                ->where('workshop_number', $workshopNumber)
                ->first();

            if ($pre === null && $mid === null && $post === null) {
                if ($existing) {
                    $existing->delete();
                }

                continue;
            }

            if ($existing) {
                $existing->update([
                    'pre_test_score' => $pre,
                    'mid_test_score' => $mid,
                    'post_test_score' => $post,
                ]);
            } else {
                TrainingEventWorkshopScore::query()->create([
                    'training_event_participant_id' => $enrollmentId,
                    'workshop_number' => $workshopNumber,
                    'pre_test_score' => $pre,
                    'mid_test_score' => $mid,
                    'post_test_score' => $post,
                ]);
            }
        }

        $this->audit()->logCustom('Workshop scores saved', 'training_workflow.workshop_scores.saved', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => [
                'workshop_number' => $workshopNumber,
                'enrollment_ids' => $enrollmentIds->all(),
            ],
        ]);

        return redirect()
            ->route('admin.training-workflow.index', [
                'event_id' => $trainingEvent->id,
                'step' => 3,
                'workshop' => $workshopNumber,
            ])
            ->with('success', 'Workshop scores saved.');
    }

    public function exportWorkshopScores(Request $request, TrainingEvent $trainingEvent): StreamedResponse
    {
        $maxWorkshop = max(1, (int) ($trainingEvent->workshop_count ?? 4));

        $data = $request->validate([
            'workshop' => 'required|integer|min:1|max:'.$maxWorkshop,
        ]);

        $workshopNumber = (int) $data['workshop'];

        $enrollments = TrainingEventParticipant::query()
            ->with([
                'participant',
                'workshopScores' => fn ($query) => $query->where('workshop_number', $workshopNumber),
            ])
            ->where('training_event_id', $trainingEvent->id)
            ->orderBy('id')
            ->get();

        $eventLabel = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) ($trainingEvent->event_name ?: 'event-'.$trainingEvent->id));
        $eventLabel = trim((string) $eventLabel, '-') ?: 'event-'.$trainingEvent->id;
        $filename = 'workshop-'.$workshopNumber.'-scores-'.$eventLabel.'.csv';
        $this->audit()->logCustom('Workshop scores exported', 'training_workflow.workshop_scores.exported', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => [
                'workshop_number' => $workshopNumber,
                'file_name' => $filename,
            ],
        ]);

        return response()->streamDownload(function () use ($enrollments, $trainingEvent, $workshopNumber): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'training_event_id',
                'workshop_number',
                'participant_id',
                'participant_code',
                'participant_name',
                'pre_test_score',
                'mid_test_score',
                'post_test_score',
            ]);

            foreach ($enrollments as $enrollment) {
                $score = $enrollment->workshopScores->first();

                fputcsv($handle, [
                    $trainingEvent->id,
                    $workshopNumber,
                    $enrollment->participant_id,
                    (string) ($enrollment->participant?->participant_code ?? ''),
                    (string) ($enrollment->participant?->name ?? 'Participant #'.$enrollment->participant_id),
                    $score?->pre_test_score,
                    $score?->mid_test_score,
                    $score?->post_test_score,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importWorkshopScores(Request $request, TrainingEvent $trainingEvent): RedirectResponse
    {
        $maxWorkshop = max(1, (int) ($trainingEvent->workshop_count ?? 4));

        $data = $request->validate([
            'workshop_number' => 'required|integer|min:1|max:'.$maxWorkshop,
            'score_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $workshopNumber = (int) $data['workshop_number'];
        $path = $data['score_file']->getRealPath();
        $handle = is_string($path) ? fopen($path, 'r') : false;

        if ($handle === false) {
            return redirect()
                ->route('admin.training-workflow.index', [
                    'event_id' => $trainingEvent->id,
                    'step' => 3,
                    'workshop' => $workshopNumber,
                ])
                ->with('error', 'Unable to read the uploaded CSV file.');
        }

        $headerRow = fgetcsv($handle);
        if (! is_array($headerRow)) {
            fclose($handle);

            return redirect()
                ->route('admin.training-workflow.index', [
                    'event_id' => $trainingEvent->id,
                    'step' => 3,
                    'workshop' => $workshopNumber,
                ])
                ->with('error', 'CSV file is empty.');
        }

        $headerMap = collect($headerRow)
            ->mapWithKeys(function ($value, $index) {
                $normalized = strtolower(trim((string) $value));

                return [$normalized => $index];
            })
            ->all();

        if (! isset($headerMap['participant_id']) && ! isset($headerMap['participant_code'])) {
            fclose($handle);

            return redirect()
                ->route('admin.training-workflow.index', [
                    'event_id' => $trainingEvent->id,
                    'step' => 3,
                    'workshop' => $workshopNumber,
                ])
                ->with('error', 'CSV must include at least participant_id or participant_code column.');
        }

        $enrollments = TrainingEventParticipant::query()
            ->with('participant')
            ->where('training_event_id', $trainingEvent->id)
            ->get();

        $enrollmentByParticipantId = $enrollments->keyBy(fn (TrainingEventParticipant $enrollment) => (string) $enrollment->participant_id);
        $enrollmentByParticipantCode = $enrollments
            ->filter(fn (TrainingEventParticipant $enrollment) => filled($enrollment->participant?->participant_code))
            ->keyBy(fn (TrainingEventParticipant $enrollment) => strtoupper((string) $enrollment->participant?->participant_code));

        $existingScores = TrainingEventWorkshopScore::query()
            ->whereIn('training_event_participant_id', $enrollments->pluck('id'))
            ->where('workshop_number', $workshopNumber)
            ->get()
            ->keyBy('training_event_participant_id');

        $updated = 0;
        $deleted = 0;
        $skipped = 0;
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            $participantIdRaw = $this->csvCell($row, $headerMap, 'participant_id');
            $participantCodeRaw = $this->csvCell($row, $headerMap, 'participant_code');
            $participantCode = strtoupper(trim((string) $participantCodeRaw));

            $enrollment = null;
            if (filled($participantIdRaw) && $enrollmentByParticipantId->has((string) trim((string) $participantIdRaw))) {
                $enrollment = $enrollmentByParticipantId->get((string) trim((string) $participantIdRaw));
            } elseif ($participantCode !== '' && $enrollmentByParticipantCode->has($participantCode)) {
                $enrollment = $enrollmentByParticipantCode->get($participantCode);
            }

            if (! $enrollment instanceof TrainingEventParticipant) {
                $skipped++;
                continue;
            }

            $preResult = $this->parseCsvScore($this->csvCell($row, $headerMap, 'pre_test_score'));
            $midResult = $this->parseCsvScore($this->csvCell($row, $headerMap, 'mid_test_score'));
            $postResult = $this->parseCsvScore($this->csvCell($row, $headerMap, 'post_test_score'));

            if (! $preResult['valid'] || ! $midResult['valid'] || ! $postResult['valid']) {
                $skipped++;
                continue;
            }

            $pre = $preResult['value'];
            $mid = $midResult['value'];
            $post = $postResult['value'];

            $existing = $existingScores->get($enrollment->id);

            if ($pre === null && $mid === null && $post === null) {
                if ($existing) {
                    $existing->delete();
                    $deleted++;
                }

                continue;
            }

            if ($existing) {
                $existing->update([
                    'pre_test_score' => $pre,
                    'mid_test_score' => $mid,
                    'post_test_score' => $post,
                ]);
            } else {
                $existing = TrainingEventWorkshopScore::query()->create([
                    'training_event_participant_id' => $enrollment->id,
                    'workshop_number' => $workshopNumber,
                    'pre_test_score' => $pre,
                    'mid_test_score' => $mid,
                    'post_test_score' => $post,
                ]);
                $existingScores->put($enrollment->id, $existing);
            }

            $updated++;
        }

        fclose($handle);

        $message = 'Workshop '.$workshopNumber.' import completed: '.$updated.' row(s) saved';
        if ($deleted > 0) {
            $message .= ', '.$deleted.' row(s) cleared';
        }
        if ($skipped > 0) {
            $message .= ', '.$skipped.' row(s) skipped';
        }
        $message .= '.';

        $this->audit()->logCustom('Workshop scores imported', 'training_workflow.workshop_scores.imported', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => [
                'workshop_number' => $workshopNumber,
                'updated' => $updated,
                'deleted' => $deleted,
                'skipped' => $skipped,
            ],
        ]);

        return redirect()
            ->route('admin.training-workflow.index', [
                'event_id' => $trainingEvent->id,
                'step' => 3,
                'workshop' => $workshopNumber,
            ])
            ->with('success', $message);
    }

    public function exportReport(TrainingEvent $trainingEvent): StreamedResponse
    {
        $workshopCount = max(1, (int) ($trainingEvent->workshop_count ?? 4));

        $event = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'trainingRegion'])
            ->findOrFail($trainingEvent->id);

        $enrollments = TrainingEventParticipant::query()
            ->with([
                'participant.region',
                'participant.woreda',
                'participant.organization.region',
                'participant.organization.woreda',
                'workshopScores' => fn ($query) => $query
                    ->where('workshop_number', '<=', $workshopCount)
                    ->orderBy('workshop_number'),
            ])
            ->where('training_event_id', $event->id)
            ->orderBy('id')
            ->get();

        $eventLabel = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) ($event->event_name ?: 'event-'.$event->id));
        $eventLabel = trim((string) $eventLabel, '-') ?: 'event-'.$event->id;
        $filename = 'training-report-'.$eventLabel.'.csv';
        $this->audit()->logCustom('Training event report exported', 'training_workflow.report.exported', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $event->id,
            'auditable_label' => $event->event_name,
            'metadata' => [
                'file_name' => $filename,
                'workshop_count' => $workshopCount,
                'participants' => $enrollments->count(),
            ],
        ]);

        return response()->streamDownload(function () use ($event, $enrollments, $workshopCount): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $header = [
                'event_id',
                'event_name',
                'training',
                'organizer',
                'event_region',
                'training_city',
                'course_venue',
                'start_date',
                'end_date',
                'status',
                'workshop_count',
                'participant_db_id',
                'participant_code',
                'first_name',
                'father_name',
                'grandfather_name',
                'participant_name',
                'gender',
                'date_of_birth',
                'age',
                'home_phone',
                'mobile_phone',
                'email',
                'profession',
                'participant_region',
                'participant_woreda',
                'organization_name',
                'organization_category',
                'organization_type',
                'organization_region',
                'organization_woreda',
                'organization_zone',
                'organization_city_town',
                'organization_phone',
                'organization_fax',
                'avg_pre_score',
                'avg_post_score',
                'final_score',
            ];

            foreach (range(1, $workshopCount) as $workshopNumber) {
                $header[] = 'workshop_'.$workshopNumber.'_pre';
                $header[] = 'workshop_'.$workshopNumber.'_mid';
                $header[] = 'workshop_'.$workshopNumber.'_post';
            }

            fputcsv($handle, $header);

            foreach ($enrollments as $enrollment) {
                $participant = $enrollment->participant;
                $organization = $participant?->organization;
                $scoresByWorkshop = $enrollment->workshopScores->keyBy('workshop_number');
                $avgPre = $enrollment->workshopScores->whereNotNull('pre_test_score')->avg('pre_test_score');
                $avgPost = $enrollment->workshopScores->whereNotNull('post_test_score')->avg('post_test_score');

                $row = [
                    $event->id,
                    (string) ($event->event_name ?? ''),
                    (string) ($event->training?->title ?? ''),
                    (string) ($event->trainingOrganizer?->title ?? ''),
                    (string) ($event->trainingRegion?->name ?? ''),
                    (string) ($event->training_city ?? ''),
                    (string) ($event->course_venue ?? ''),
                    (string) ($event->start_date ?? ''),
                    (string) ($event->end_date ?? ''),
                    (string) ($event->status ?? ''),
                    $workshopCount,
                    $participant?->id,
                    (string) ($participant?->participant_code ?? ''),
                    (string) ($participant?->first_name ?? ''),
                    (string) ($participant?->father_name ?? ''),
                    (string) ($participant?->grandfather_name ?? ''),
                    (string) ($participant?->name ?? 'Participant #'.$enrollment->participant_id),
                    (string) ($participant?->gender ?? ''),
                    (string) ($participant?->date_of_birth ?? ''),
                    $participant?->age,
                    (string) ($participant?->home_phone ?? ''),
                    (string) ($participant?->mobile_phone ?? ''),
                    (string) ($participant?->email ?? ''),
                    (string) ($participant?->profession ?? ''),
                    (string) ($participant?->region?->name ?? ''),
                    (string) ($participant?->woreda?->name ?? ''),
                    (string) ($organization?->name ?? ''),
                    (string) ($organization?->category ?? ''),
                    (string) ($organization?->type ?? ''),
                    (string) ($organization?->region?->name ?? ''),
                    (string) ($organization?->woreda?->name ?? ''),
                    (string) ($organization?->zone ?? ''),
                    (string) ($organization?->city_town ?? ''),
                    (string) ($organization?->phone ?? ''),
                    (string) ($organization?->fax ?? ''),
                    $avgPre !== null ? round((float) $avgPre, 2) : null,
                    $avgPost !== null ? round((float) $avgPost, 2) : null,
                    $enrollment->final_score !== null ? round((float) $enrollment->final_score, 2) : null,
                ];

                foreach (range(1, $workshopCount) as $workshopNumber) {
                    $score = $scoresByWorkshop->get($workshopNumber);
                    $row[] = $score?->pre_test_score;
                    $row[] = $score?->mid_test_score;
                    $row[] = $score?->post_test_score;
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function syncWorkshopStructure(TrainingEvent $trainingEvent, int $workshopCount): void
    {
        $workshopCount = max(1, $workshopCount);

        $enrollments = TrainingEventParticipant::query()
            ->where('training_event_id', $trainingEvent->id)
            ->get();

        foreach ($enrollments as $enrollment) {
            foreach (range(1, $workshopCount) as $workshopNumber) {
                TrainingEventWorkshopScore::query()->firstOrCreate(
                    [
                        'training_event_participant_id' => $enrollment->id,
                        'workshop_number' => $workshopNumber,
                    ],
                    [
                        'pre_test_score' => null,
                        'mid_test_score' => null,
                        'post_test_score' => null,
                    ]
                );
            }
        }

        TrainingEventWorkshopScore::query()
            ->where('workshop_number', '>', $workshopCount)
            ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $trainingEvent->id))
            ->get()
            ->each
            ->delete();

        $enrollments->each->refreshFinalScore();
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function csvCell(array $row, array $headerMap, string $column): ?string
    {
        if (! array_key_exists($column, $headerMap)) {
            return null;
        }

        $value = $row[$headerMap[$column]] ?? null;
        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    private function parseCsvScore(?string $value): array
    {
        if ($value === null || $value === '') {
            return ['valid' => true, 'value' => null];
        }

        if (! is_numeric($value)) {
            return ['valid' => false, 'value' => null];
        }

        $numeric = (float) $value;
        if ($numeric < 0 || $numeric > 100) {
            return ['valid' => false, 'value' => null];
        }

        return ['valid' => true, 'value' => $numeric];
    }
}
