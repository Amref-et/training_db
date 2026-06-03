<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingEventResource;
use App\Models\Participant;
use App\Models\TrainingEvent;
use App\Models\TrainingEventJoinRequest;
use App\Models\TrainingEventParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrainingEventJoinRequestController extends Controller
{
    public function options(): JsonResponse
    {
        $events = $this->requestableEventsQuery()
            ->with(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])
            ->withCount('enrollments')
            ->orderBy('start_date')
            ->orderBy('event_name')
            ->get();

        return response()->json([
            'data' => [
                'events' => TrainingEventResource::collection($events),
            ],
        ]);
    }

    public function participantOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => ['options' => []]]);
        }

        $participants = Participant::query()
            ->select(['id', 'name', 'first_name', 'father_name', 'grandfather_name', 'mobile_phone'])
            ->where(function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query
                    ->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('father_name', 'like', $like)
                    ->orWhere('grandfather_name', 'like', $like);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'options' => $participants
                    ->map(fn (Participant $participant): array => [
                        'value' => $participant->id,
                        'label' => $this->participantLabel($participant),
                        'hint' => $this->phoneHint($participant->mobile_phone),
                        'mobile_phone' => (string) $participant->mobile_phone,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'training_event_id' => ['required', 'integer', 'exists:training_events,id'],
            'participant_id' => ['nullable', 'integer', 'exists:participants,id'],
            'participant_name' => ['required', 'string', 'max:255'],
            'mobile_phone' => ['required', 'string', 'max:30'],
            'requested_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $event = $this->requestableEventsQuery()
            ->with(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])
            ->find((int) $data['training_event_id']);

        if (! $event) {
            throw ValidationException::withMessages([
                'training_event_id' => 'Selected training event is not accepting join requests.',
            ]);
        }

        $participant = $this->resolveParticipant(
            $data['participant_id'] ?? null,
            (string) $data['participant_name'],
            (string) $data['mobile_phone']
        );

        if (! $participant) {
            throw ValidationException::withMessages([
                'participant_name' => 'Participant name and mobile phone do not match an existing participant.',
            ]);
        }

        $enrollment = TrainingEventParticipant::query()
            ->where('training_event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($enrollment) {
            return response()->json([
                'data' => [
                    'status' => 'already_enrolled',
                    'event' => new TrainingEventResource($event),
                    'join_request' => null,
                ],
                'message' => 'Participant is already enrolled in the selected training event.',
            ]);
        }

        $joinRequest = TrainingEventJoinRequest::query()->firstOrNew([
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
        ]);

        if ($joinRequest->exists && $joinRequest->status === TrainingEventJoinRequest::STATUS_PENDING) {
            return response()->json([
                'data' => [
                    'status' => 'already_pending',
                    'event' => new TrainingEventResource($event),
                    'join_request' => $this->joinRequestPayload($joinRequest),
                ],
                'message' => 'Join request is already pending approval.',
            ]);
        }

        $joinRequest->fill([
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => $data['requested_message'] ?? null,
            'reviewer_notes' => null,
            'requested_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'enrollment_id' => null,
        ]);
        $joinRequest->save();

        $this->audit()->logCustom('Mobile training event join request submitted', 'training_event_join_requests.mobile_submitted', [
            'auditable_type' => TrainingEventJoinRequest::class,
            'auditable_id' => $joinRequest->id,
            'auditable_label' => $participant->name.' -> '.$event->event_name,
            'metadata' => [
                'training_event_id' => $event->id,
                'participant_id' => $participant->id,
            ],
        ]);

        return response()->json([
            'data' => [
                'status' => 'pending',
                'event' => new TrainingEventResource($event),
                'join_request' => $this->joinRequestPayload($joinRequest),
            ],
            'message' => 'Join request submitted and pending approval.',
        ], 201);
    }

    private function requestableEventsQuery(): Builder
    {
        return TrainingEvent::query()
            ->whereIn('status', TrainingEvent::REQUESTABLE_STATUSES)
            ->whereDate('end_date', '>=', now()->toDateString());
    }

    private function resolveParticipant(mixed $participantId, string $participantName, string $mobilePhone): ?Participant
    {
        $phoneDigits = $this->phoneDigits($mobilePhone);

        if ($phoneDigits === '') {
            return null;
        }

        if ($participantId) {
            $participant = Participant::query()->find((int) $participantId);

            return $participant && $this->phoneDigits($participant->mobile_phone) === $phoneDigits
                ? $participant
                : null;
        }

        $name = trim($participantName);

        if ($name === '') {
            return null;
        }

        $participants = Participant::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->limit(10)
            ->get();

        if ($participants->isEmpty()) {
            $participants = Participant::query()
                ->where('name', 'like', '%'.$name.'%')
                ->limit(10)
                ->get();
        }

        return $participants
            ->first(fn (Participant $participant): bool => $this->phoneDigits($participant->mobile_phone) === $phoneDigits);
    }

    private function phoneDigits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function phoneHint(mixed $value): string
    {
        $digits = $this->phoneDigits($value);

        return $digits === ''
            ? 'Registered phone unavailable'
            : 'Registered phone ending '.substr($digits, -4);
    }

    private function participantLabel(Participant $participant): string
    {
        $name = trim((string) $participant->name);

        if ($name !== '') {
            return $name;
        }

        $nameParts = array_filter([
            $participant->first_name,
            $participant->father_name,
            $participant->grandfather_name,
        ]);

        return trim(implode(' ', $nameParts)) ?: 'Participant #'.$participant->id;
    }

    private function joinRequestPayload(TrainingEventJoinRequest $joinRequest): array
    {
        return [
            'id' => $joinRequest->id,
            'status' => $joinRequest->status,
            'requested_message' => $joinRequest->requested_message,
            'requested_at' => $joinRequest->requested_at?->toIso8601String(),
        ];
    }
}
