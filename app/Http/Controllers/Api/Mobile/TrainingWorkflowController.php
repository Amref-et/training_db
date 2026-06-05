<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingEventResource;
use App\Models\TrainingEvent;
use App\Models\TrainingEventJoinRequest;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingEventWorkshop;
use App\Models\TrainingEventWorkshopScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainingWorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'trainingRegion'])
            ->withCount([
                'enrollments',
                'joinRequests as pending_join_requests_count' => fn ($query) => $query
                    ->where('status', TrainingEventJoinRequest::STATUS_PENDING),
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = '%'.trim((string) $request->query('q')).'%';

                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('event_name', 'like', $search)
                        ->orWhere('training_city', 'like', $search)
                        ->orWhere('course_venue', 'like', $search)
                        ->orWhere('status', 'like', $search)
                        ->orWhereHas('training', fn ($training) => $training->where('title', 'like', $search));
                });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'events' => $events->map(fn (TrainingEvent $event): array => $this->eventSummaryPayload($event))->values(),
            ],
        ]);
    }

    public function show(TrainingEvent $trainingEvent): JsonResponse
    {
        return response()->json([
            'data' => $this->workflowPayload($trainingEvent),
        ]);
    }

    public function approveJoinRequest(Request $request, TrainingEvent $trainingEvent, TrainingEventJoinRequest $joinRequest): JsonResponse
    {
        $this->ensureJoinRequestBelongsToEvent($trainingEvent, $joinRequest);

        $data = $request->validate([
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $participant = $joinRequest->participant;
        abort_unless($participant, 404);

        $enrollment = TrainingEventParticipant::query()->firstOrCreate([
            'training_event_id' => $trainingEvent->id,
            'participant_id' => $participant->id,
        ]);

        $joinRequest->update([
            'status' => TrainingEventJoinRequest::STATUS_APPROVED,
            'reviewer_notes' => $data['reviewer_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()?->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->audit()->logCustom('Training event join request approved from mobile', 'training_event_join_requests.mobile_approved', [
            'auditable_type' => TrainingEventJoinRequest::class,
            'auditable_id' => $joinRequest->id,
            'auditable_label' => $participant->name.' -> '.$trainingEvent->event_name,
            'metadata' => [
                'training_event_id' => $trainingEvent->id,
                'participant_id' => $participant->id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json([
            'data' => $this->workflowPayload($trainingEvent),
            'message' => 'Join request approved and participant enrolled.',
        ]);
    }

    public function rejectJoinRequest(Request $request, TrainingEvent $trainingEvent, TrainingEventJoinRequest $joinRequest): JsonResponse
    {
        $this->ensureJoinRequestBelongsToEvent($trainingEvent, $joinRequest);

        if ($joinRequest->status === TrainingEventJoinRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'join_request' => 'Approved requests cannot be rejected.',
            ]);
        }

        $data = $request->validate([
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $joinRequest->update([
            'status' => TrainingEventJoinRequest::STATUS_REJECTED,
            'reviewer_notes' => $data['reviewer_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()?->id,
        ]);

        $this->audit()->logCustom('Training event join request rejected from mobile', 'training_event_join_requests.mobile_rejected', [
            'auditable_type' => TrainingEventJoinRequest::class,
            'auditable_id' => $joinRequest->id,
            'auditable_label' => ($joinRequest->participant?->name ?? 'Participant #'.$joinRequest->participant_id).' -> '.$trainingEvent->event_name,
            'metadata' => [
                'training_event_id' => $trainingEvent->id,
                'participant_id' => $joinRequest->participant_id,
                'user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json([
            'data' => $this->workflowPayload($trainingEvent),
            'message' => 'Join request rejected.',
        ]);
    }

    public function storeWorkshopCount(Request $request, TrainingEvent $trainingEvent): JsonResponse
    {
        $data = $request->validate([
            'workshop_count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $trainingEvent->update([
            'workshop_count' => (int) $data['workshop_count'],
        ]);

        $this->syncWorkshopStructure($trainingEvent, (int) $data['workshop_count']);

        $this->audit()->logCustom('Training event workshop count updated from mobile', 'training_workflow.mobile_workshop_count_updated', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => [
                'workshop_count' => (int) $data['workshop_count'],
                'user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json([
            'data' => $this->workflowPayload($trainingEvent),
            'message' => 'Workshop structure updated.',
        ]);
    }

    public function saveWorkshopScores(Request $request, TrainingEvent $trainingEvent): JsonResponse
    {
        $maxWorkshop = max(1, (int) ($trainingEvent->workshop_count ?? 1));

        $data = $request->validate([
            'workshop_number' => ['required', 'integer', 'min:1', 'max:'.$maxWorkshop],
            'workshop_start_date' => ['nullable', 'date'],
            'workshop_end_date' => ['nullable', 'date', 'after_or_equal:workshop_start_date'],
            'scores' => ['array'],
            'scores.*.enrollment_id' => ['required', 'integer', 'exists:training_event_participants,id'],
            'scores.*.pre_test_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.mid_test_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.post_test_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $eventEnrollmentIds = TrainingEventParticipant::query()
            ->where('training_event_id', $trainingEvent->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $eventEnrollmentLookup = array_flip($eventEnrollmentIds);
        $workshopNumber = (int) $data['workshop_number'];

        foreach ($data['scores'] ?? [] as $row) {
            if (! isset($eventEnrollmentLookup[(int) $row['enrollment_id']])) {
                throw ValidationException::withMessages([
                    'scores' => 'One or more selected participants do not belong to this training event.',
                ]);
            }
        }

        $this->upsertWorkshopDates(
            $trainingEvent,
            $workshopNumber,
            $data['workshop_start_date'] ?? null,
            $data['workshop_end_date'] ?? null
        );

        foreach ($data['scores'] ?? [] as $row) {
            $enrollmentId = (int) $row['enrollment_id'];

            $pre = $this->toNullableFloat($row['pre_test_score'] ?? null);
            $mid = $this->toNullableFloat($row['mid_test_score'] ?? null);
            $post = $this->toNullableFloat($row['post_test_score'] ?? null);
            $existing = TrainingEventWorkshopScore::query()
                ->where('training_event_participant_id', $enrollmentId)
                ->where('workshop_number', $workshopNumber)
                ->first();

            if ($pre === null && $mid === null && $post === null) {
                $existing?->delete();

                continue;
            }

            TrainingEventWorkshopScore::query()->updateOrCreate(
                [
                    'training_event_participant_id' => $enrollmentId,
                    'workshop_number' => $workshopNumber,
                ],
                [
                    'pre_test_score' => $pre,
                    'mid_test_score' => $mid,
                    'post_test_score' => $post,
                ]
            );
        }

        $this->audit()->logCustom('Workshop scores saved from mobile', 'training_workflow.mobile_workshop_scores_saved', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => [
                'workshop_number' => $workshopNumber,
                'score_rows' => count($data['scores'] ?? []),
                'user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json([
            'data' => $this->workflowPayload($trainingEvent),
            'message' => 'Workshop scores saved.',
        ]);
    }

    public function updateCloseout(Request $request, TrainingEvent $trainingEvent): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', TrainingEvent::STATUSES)],
            'training_event_report' => ['nullable', 'file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,jpg,jpeg,png'],
            'training_event_pictures' => ['nullable', 'array', 'max:20'],
            'training_event_pictures.*' => ['image', 'max:8192'],
            'remove_report' => ['nullable', 'boolean'],
            'remove_existing_pictures' => ['nullable', 'array'],
            'remove_existing_pictures.*' => ['string'],
        ]);

        $beforeState = $this->audit()->snapshotModel($trainingEvent, [
            'status',
            'training_event_report_path',
            'training_event_picture_paths',
        ]);

        $reportPath = $trainingEvent->training_event_report_path;
        $picturePaths = collect($trainingEvent->training_event_picture_paths ?? [])
            ->filter(fn ($path): bool => is_string($path) && trim($path) !== '')
            ->values()
            ->all();

        if ($request->boolean('remove_report') && $reportPath) {
            Storage::disk('public')->delete($reportPath);
            $reportPath = null;
        }

        if ($request->hasFile('training_event_report')) {
            if ($reportPath) {
                Storage::disk('public')->delete($reportPath);
            }

            $reportPath = $request
                ->file('training_event_report')
                ->store('training-events/'.$trainingEvent->id.'/reports', 'public');
        }

        $removePicturePaths = collect($data['remove_existing_pictures'] ?? [])
            ->map(fn ($path): string => (string) $path)
            ->intersect($picturePaths)
            ->values()
            ->all();

        if ($removePicturePaths !== []) {
            Storage::disk('public')->delete($removePicturePaths);
            $picturePaths = array_values(array_diff($picturePaths, $removePicturePaths));
        }

        foreach ($request->file('training_event_pictures', []) as $picture) {
            $picturePaths[] = $picture->store('training-events/'.$trainingEvent->id.'/pictures', 'public');
        }

        $trainingEvent->forceFill([
            'status' => $data['status'],
            'training_event_report_path' => $reportPath,
            'training_event_picture_paths' => array_values($picturePaths),
        ])->save();

        $this->audit()->logCustom('Training event closeout updated from mobile', 'training_workflow.mobile_closeout_updated', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'old_values' => $beforeState,
            'new_values' => $this->audit()->snapshotModel($trainingEvent->fresh(), [
                'status',
                'training_event_report_path',
                'training_event_picture_paths',
            ]),
            'metadata' => [
                'uploaded_pictures' => count($request->file('training_event_pictures', [])),
                'removed_pictures' => count($removePicturePaths),
                'report_uploaded' => $request->hasFile('training_event_report'),
                'report_removed' => $request->boolean('remove_report'),
                'user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json([
            'data' => $this->workflowPayload($trainingEvent),
            'message' => 'Training event closeout updated.',
        ]);
    }

    private function workflowPayload(TrainingEvent $trainingEvent): array
    {
        $event = $trainingEvent
            ->refresh()
            ->load([
                'training',
                'trainingOrganizer',
                'trainingRegion',
                'workshops',
                'enrollments.participant',
                'enrollments.workshopScores',
            ])
            ->loadCount('enrollments');

        $workshopCount = max(1, (int) ($event->workshop_count ?? 1));
        $enrollments = $event->enrollments
            ->sortBy(fn (TrainingEventParticipant $enrollment) => mb_strtolower((string) $enrollment->participant?->name))
            ->values();
        $joinRequests = TrainingEventJoinRequest::query()
            ->with(['participant', 'reviewer', 'enrollment'])
            ->where('training_event_id', $event->id)
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        $workshops = $event->workshops->keyBy('workshop_number');
        $workshopProgress = $this->workshopProgress($enrollments, $workshopCount);
        $reportSummary = $this->reportSummary($event, $enrollments, $workshopCount);

        return [
            'event' => new TrainingEventResource($event),
            'steps' => $this->stepStatus($event, $enrollments, $workshopProgress, $reportSummary),
            'join_requests' => $joinRequests->map(fn (TrainingEventJoinRequest $joinRequest): array => $this->joinRequestPayload($joinRequest))->values(),
            'enrollments' => $enrollments->map(fn (TrainingEventParticipant $enrollment): array => $this->enrollmentPayload($enrollment, $workshopCount))->values(),
            'workshops' => collect(range(1, $workshopCount))->map(function (int $workshopNumber) use ($event, $workshops, $workshopProgress): array {
                $workshop = $workshops->get($workshopNumber);
                $defaults = $this->defaultWorkshopDates($event, $workshopNumber);

                return [
                    'workshop_number' => $workshopNumber,
                    'start_date' => $workshop?->start_date?->toDateString() ?? $defaults['start_date'],
                    'end_date' => $workshop?->end_date?->toDateString() ?? $defaults['end_date'],
                    'progress' => $workshopProgress[$workshopNumber],
                ];
            })->values(),
            'summary' => $reportSummary,
            'report_workshops' => $this->reportWorkshopPayload($event, $workshopCount, $workshops),
            'report_participants' => $enrollments
                ->map(fn (TrainingEventParticipant $enrollment): array => $this->reportParticipantPayload($enrollment, $workshopCount))
                ->values(),
            'closeout' => $this->closeoutPayload($event),
        ];
    }

    private function eventSummaryPayload(TrainingEvent $event): array
    {
        return [
            'event' => new TrainingEventResource($event),
            'pending_join_requests_count' => (int) ($event->pending_join_requests_count ?? 0),
            'enrollments_count' => (int) ($event->enrollments_count ?? 0),
            'workshop_count' => max(1, (int) ($event->workshop_count ?? 1)),
        ];
    }

    private function joinRequestPayload(TrainingEventJoinRequest $joinRequest): array
    {
        return [
            'id' => $joinRequest->id,
            'status' => $joinRequest->status,
            'requested_message' => $joinRequest->requested_message,
            'reviewer_notes' => $joinRequest->reviewer_notes,
            'requested_at' => $joinRequest->requested_at?->toIso8601String(),
            'reviewed_at' => $joinRequest->reviewed_at?->toIso8601String(),
            'participant' => $joinRequest->participant ? $this->participantPayload($joinRequest->participant) : null,
        ];
    }

    private function enrollmentPayload(TrainingEventParticipant $enrollment, int $workshopCount): array
    {
        $scores = $enrollment->workshopScores
            ->where('workshop_number', '<=', $workshopCount)
            ->keyBy('workshop_number');

        return [
            'id' => $enrollment->id,
            'training_event_id' => $enrollment->training_event_id,
            'participant_id' => $enrollment->participant_id,
            'final_score' => $enrollment->final_score !== null ? round((float) $enrollment->final_score, 2) : null,
            'participant' => $enrollment->participant ? $this->participantPayload($enrollment->participant) : null,
            'scores' => collect(range(1, $workshopCount))->map(function (int $workshopNumber) use ($scores): array {
                $score = $scores->get($workshopNumber);

                return [
                    'workshop_number' => $workshopNumber,
                    'pre_test_score' => $score?->pre_test_score !== null ? round((float) $score->pre_test_score, 2) : null,
                    'mid_test_score' => $score?->mid_test_score !== null ? round((float) $score->mid_test_score, 2) : null,
                    'post_test_score' => $score?->post_test_score !== null ? round((float) $score->post_test_score, 2) : null,
                ];
            })->values(),
        ];
    }

    private function participantPayload($participant): array
    {
        return [
            'id' => $participant->id,
            'name' => $participant->name,
            'mobile_phone' => $participant->mobile_phone,
            'participant_code' => $participant->participant_code,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\TrainingEventParticipant> $enrollments
     * @return array<int, array{completed:int,total:int,is_complete:bool}>
     */
    private function workshopProgress(Collection $enrollments, int $workshopCount): array
    {
        return collect(range(1, $workshopCount))
            ->mapWithKeys(function (int $workshopNumber) use ($enrollments): array {
                $total = $enrollments->count();
                $completed = $enrollments->filter(function (TrainingEventParticipant $enrollment) use ($workshopNumber): bool {
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
    }

    private function reportSummary(TrainingEvent $event, Collection $enrollments, int $workshopCount): array
    {
        $overallAveragePre = TrainingEventWorkshopScore::query()
            ->where('workshop_number', '<=', $workshopCount)
            ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $event->id))
            ->avg('pre_test_score');
        $overallAveragePost = TrainingEventWorkshopScore::query()
            ->where('workshop_number', '<=', $workshopCount)
            ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $event->id))
            ->avg('post_test_score');

        return [
            'participants_count' => $enrollments->count(),
            'with_final_scores' => $enrollments->filter(fn (TrainingEventParticipant $enrollment) => $enrollment->final_score !== null)->count(),
            'avg_final_score' => $enrollments->whereNotNull('final_score')->avg('final_score') !== null
                ? round((float) $enrollments->whereNotNull('final_score')->avg('final_score'), 2)
                : null,
            'avg_pre_score' => $overallAveragePre !== null ? round((float) $overallAveragePre, 2) : null,
            'avg_post_score' => $overallAveragePost !== null ? round((float) $overallAveragePost, 2) : null,
            'required_workshop_count' => $workshopCount,
        ];
    }

    private function reportWorkshopPayload(TrainingEvent $event, int $workshopCount, Collection $workshops): Collection
    {
        $averageRows = TrainingEventWorkshopScore::query()
            ->where('workshop_number', '<=', $workshopCount)
            ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $event->id))
            ->selectRaw('workshop_number, AVG(pre_test_score) as avg_pre_score, AVG(post_test_score) as avg_post_score')
            ->groupBy('workshop_number')
            ->orderBy('workshop_number')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->workshop_number);

        return collect(range(1, $workshopCount))
            ->map(function (int $workshopNumber) use ($event, $workshops, $averageRows): array {
                $workshop = $workshops->get($workshopNumber);
                $average = $averageRows->get($workshopNumber);
                $defaults = $this->defaultWorkshopDates($event, $workshopNumber);

                return [
                    'workshop_number' => $workshopNumber,
                    'start_date' => $workshop?->start_date?->toDateString() ?? $defaults['start_date'],
                    'end_date' => $workshop?->end_date?->toDateString() ?? $defaults['end_date'],
                    'avg_pre_score' => $average?->avg_pre_score !== null ? round((float) $average->avg_pre_score, 2) : null,
                    'avg_post_score' => $average?->avg_post_score !== null ? round((float) $average->avg_post_score, 2) : null,
                ];
            })
            ->values();
    }

    private function reportParticipantPayload(TrainingEventParticipant $enrollment, int $workshopCount): array
    {
        $scores = $enrollment->workshopScores
            ->where('workshop_number', '<=', $workshopCount)
            ->values();
        $avgPre = $scores->whereNotNull('pre_test_score')->avg('pre_test_score');
        $avgPost = $scores->whereNotNull('post_test_score')->avg('post_test_score');

        return [
            'enrollment_id' => $enrollment->id,
            'participant' => $enrollment->participant ? $this->participantPayload($enrollment->participant) : null,
            'avg_pre_score' => $avgPre !== null ? round((float) $avgPre, 2) : null,
            'avg_post_score' => $avgPost !== null ? round((float) $avgPost, 2) : null,
            'final_score' => $enrollment->final_score !== null ? round((float) $enrollment->final_score, 2) : null,
        ];
    }

    private function closeoutPayload(TrainingEvent $event): array
    {
        $picturePaths = collect($event->training_event_picture_paths ?? [])
            ->filter(fn ($path): bool => is_string($path) && trim($path) !== '')
            ->values();

        return [
            'statuses' => TrainingEvent::STATUSES,
            'report_path' => $event->training_event_report_path,
            'report_url' => $this->mediaUrl($event->training_event_report_path),
            'pictures' => $picturePaths
                ->map(fn (string $path): array => [
                    'path' => $path,
                    'url' => $this->mediaUrl($path),
                ])
                ->values(),
        ];
    }

    private function stepStatus(TrainingEvent $event, Collection $enrollments, array $workshopProgress, array $reportSummary): array
    {
        return [
            ['step' => 1, 'title' => 'Training Event', 'complete' => true],
            ['step' => 2, 'title' => 'Participant Enrollment', 'complete' => $enrollments->count() > 0],
            [
                'step' => 3,
                'title' => 'Workshops',
                'complete' => ! empty($workshopProgress) && collect($workshopProgress)->every(fn (array $step): bool => $step['is_complete']),
            ],
            [
                'step' => 4,
                'title' => 'Report',
                'complete' => $reportSummary['participants_count'] > 0
                    && $reportSummary['with_final_scores'] === $reportSummary['participants_count'],
            ],
            [
                'step' => 5,
                'title' => 'Closeout',
                'complete' => in_array((string) $event->status, ['Completed', 'Cancelled'], true)
                    && filled($event->training_event_report_path),
            ],
        ];
    }

    private function ensureJoinRequestBelongsToEvent(TrainingEvent $trainingEvent, TrainingEventJoinRequest $joinRequest): void
    {
        abort_unless((int) $joinRequest->training_event_id === (int) $trainingEvent->id, 404);
    }

    private function syncWorkshopStructure(TrainingEvent $trainingEvent, int $workshopCount): void
    {
        $workshopCount = max(1, $workshopCount);

        foreach (range(1, $workshopCount) as $workshopNumber) {
            $defaults = $this->defaultWorkshopDates($trainingEvent, $workshopNumber);

            TrainingEventWorkshop::query()->firstOrCreate(
                [
                    'training_event_id' => $trainingEvent->id,
                    'workshop_number' => $workshopNumber,
                ],
                $defaults
            );
        }

        TrainingEventWorkshop::query()
            ->where('training_event_id', $trainingEvent->id)
            ->where('workshop_number', '>', $workshopCount)
            ->delete();
    }

    private function upsertWorkshopDates(
        TrainingEvent $trainingEvent,
        int $workshopNumber,
        ?string $startDate,
        ?string $endDate
    ): TrainingEventWorkshop {
        $defaults = $this->defaultWorkshopDates($trainingEvent, $workshopNumber);

        $workshop = TrainingEventWorkshop::query()->firstOrCreate(
            [
                'training_event_id' => $trainingEvent->id,
                'workshop_number' => $workshopNumber,
            ],
            $defaults
        );

        $workshop->update([
            'start_date' => $startDate ?: $defaults['start_date'],
            'end_date' => $endDate ?: $defaults['end_date'],
        ]);

        return $workshop->fresh();
    }

    private function defaultWorkshopDates(TrainingEvent $trainingEvent, int $workshopNumber): array
    {
        $startDate = $trainingEvent->start_date ? Carbon::parse($trainingEvent->start_date) : null;
        $endDate = $trainingEvent->end_date ? Carbon::parse($trainingEvent->end_date) : null;

        if (! $startDate || ! $endDate) {
            return ['start_date' => null, 'end_date' => null];
        }

        if ($workshopNumber === 1) {
            return [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ];
        }

        return ['start_date' => null, 'end_date' => null];
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function mediaUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return url(ltrim($value, '/'));
        }

        $storageUrl = Storage::disk('public')->url($value);

        return str_starts_with($storageUrl, 'http://') || str_starts_with($storageUrl, 'https://')
            ? $storageUrl
            : url(ltrim($storageUrl, '/'));
    }
}
