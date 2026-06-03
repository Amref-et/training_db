<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingEventResource;
use App\Models\Participant;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function store(Request $request, TrainingEvent $trainingEvent): JsonResponse
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:participants,id'],
        ]);

        $participant = Participant::query()->findOrFail((int) $data['participant_id']);
        $existing = TrainingEventParticipant::query()
            ->where('training_event_id', $trainingEvent->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => [
                    'status' => 'already_enrolled',
                    'enrollment' => $this->enrollmentPayload($existing),
                    'event' => new TrainingEventResource($this->eventPayload($trainingEvent)),
                    'participant' => $this->participantPayload($participant),
                ],
                'message' => 'Participant is already enrolled in the selected training event.',
            ]);
        }

        $enrollment = TrainingEventParticipant::query()->create([
            'training_event_id' => $trainingEvent->id,
            'participant_id' => $participant->id,
        ]);

        $this->audit()->logCustom('Participant enrolled into training event from mobile', 'training_workflow.enrollment.mobile_created', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => [
                'participant_id' => $participant->id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json([
            'data' => [
                'status' => 'enrolled',
                'enrollment' => $this->enrollmentPayload($enrollment),
                'event' => new TrainingEventResource($this->eventPayload($trainingEvent)),
                'participant' => $this->participantPayload($participant),
            ],
            'message' => 'Participant enrolled successfully.',
        ], 201);
    }

    private function eventPayload(TrainingEvent $trainingEvent): TrainingEvent
    {
        return $trainingEvent
            ->refresh()
            ->load(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])
            ->loadCount('enrollments');
    }

    private function enrollmentPayload(TrainingEventParticipant $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'training_event_id' => $enrollment->training_event_id,
            'participant_id' => $enrollment->participant_id,
        ];
    }

    private function participantPayload(Participant $participant): array
    {
        return [
            'id' => $participant->id,
            'name' => $participant->name,
            'mobile_phone' => $participant->mobile_phone,
            'participant_code' => $participant->participant_code,
        ];
    }
}
