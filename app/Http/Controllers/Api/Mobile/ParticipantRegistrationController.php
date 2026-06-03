<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ParticipantResource;
use App\Http\Resources\Api\V1\TrainingEventResource;
use App\Models\Participant;
use App\Models\TrainingEvent;
use App\Models\TrainingEventJoinRequest;
use App\Models\TrainingEventParticipant;
use App\Services\ParticipantRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ParticipantRegistrationController extends Controller
{
    public function __construct(private ParticipantRegistrationService $registration) {}

    public function options(Request $request): JsonResponse
    {
        $options = $this->registration->formOptions();

        return response()->json([
            'data' => [
                'regions' => $options['regions'],
                'zones' => $options['zones'],
                'woredas' => $options['woredas'],
                'professions' => $options['professions'],
                'selected_organization' => $this->registration->selectedOrganizationOption(
                    $request->query('organization_id')
                ),
            ],
        ]);
    }

    public function organizationOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'options' => $this->registration->organizationOptions(
                    $request->string('q')->toString(),
                    $request->input('selected_id'),
                    $request->input('region_id'),
                    $request->input('zone_id'),
                    $request->input('woreda_id')
                ),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $joinRequestData = $request->validate([
            'training_event_id' => ['nullable', 'integer', 'exists:training_events,id'],
            'requested_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $event = $this->requestableEvent($joinRequestData['training_event_id'] ?? null);
        $data = $this->registration->validateAndPrepare($request->all(), null, false);
        $existingParticipant = $this->registration->existingParticipantForGeneratedCode($data);

        if ($existingParticipant) {
            $participant = $existingParticipant->load(['region', 'zone', 'woreda', 'organization']);
            $created = false;

            $this->audit()->logCustom('Mobile participant registration duplicate loaded existing record', 'participants.mobile_registration_duplicate', [
                'auditable_type' => Participant::class,
                'auditable_id' => $participant->id,
                'auditable_label' => $participant->name,
                'metadata' => [
                    'participant_code' => $participant->participant_code,
                ],
            ]);
        } else {
            $this->registration->ensureEmailIsAvailable($data['email'] ?? null);
            $participant = $this->registration->create($data)->load(['region', 'zone', 'woreda', 'organization']);
            $created = true;

            $this->audit()->logCustom('Mobile participant registration submitted', 'participants.mobile_registration', [
                'auditable_type' => Participant::class,
                'auditable_id' => $participant->id,
                'auditable_label' => $participant->name,
                'new_values' => $this->audit()->snapshotModel($participant),
            ]);
        }

        $joinRequest = $event
            ? $this->submitJoinRequest($event, $participant, $joinRequestData['requested_message'] ?? null)
            : null;

        return response()->json([
            'data' => [
                'participant' => new ParticipantResource($participant),
                'created' => $created,
                'duplicate' => ! $created,
                'join_request' => $joinRequest,
            ],
            'message' => $created ? 'Registration submitted successfully.' : 'Existing participant record returned.',
        ], $created ? 201 : 200);
    }

    private function requestableEvent(mixed $eventId): ?TrainingEvent
    {
        if ($eventId === null || $eventId === '') {
            return null;
        }

        $event = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])
            ->whereIn('status', TrainingEvent::REQUESTABLE_STATUSES)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->find((int) $eventId);

        if (! $event) {
            throw ValidationException::withMessages([
                'training_event_id' => 'Selected training event is not accepting join requests.',
            ]);
        }

        return $event;
    }

    private function submitJoinRequest(TrainingEvent $event, Participant $participant, ?string $message): array
    {
        $enrollment = TrainingEventParticipant::query()
            ->where('training_event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($enrollment) {
            return [
                'status' => 'already_enrolled',
                'event' => new TrainingEventResource($event),
                'request' => null,
            ];
        }

        $joinRequest = TrainingEventJoinRequest::query()->firstOrNew([
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
        ]);

        $joinRequest->fill([
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => $message,
            'reviewer_notes' => null,
            'requested_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'enrollment_id' => null,
        ]);
        $joinRequest->save();

        $this->audit()->logCustom('Mobile training event join request submitted after registration', 'training_event_join_requests.mobile_submitted_after_registration', [
            'auditable_type' => TrainingEventJoinRequest::class,
            'auditable_id' => $joinRequest->id,
            'auditable_label' => $participant->name.' -> '.$event->event_name,
            'metadata' => [
                'training_event_id' => $event->id,
                'participant_id' => $participant->id,
            ],
        ]);

        return [
            'status' => 'pending',
            'event' => new TrainingEventResource($event),
            'request' => [
                'id' => $joinRequest->id,
                'status' => $joinRequest->status,
                'requested_message' => $joinRequest->requested_message,
                'requested_at' => $joinRequest->requested_at?->toIso8601String(),
            ],
        ];
    }
}
