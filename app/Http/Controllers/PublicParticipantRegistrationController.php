<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Participant;
use App\Models\TrainingEvent;
use App\Models\TrainingEventJoinRequest;
use App\Models\TrainingEventParticipant;
use App\Models\WebsiteMenuItem;
use App\Models\WebsiteSetting;
use App\Services\ParticipantRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicParticipantRegistrationController extends Controller
{
    private const PENDING_JOIN_REQUEST_SESSION_KEY = 'pending_training_event_join_request';

    public function __construct(private ParticipantRegistrationService $registration)
    {
    }

    public function create(Request $request): View
    {
        return view('website.participant-registration', $this->viewData(
            $request,
            $this->registration->formOptions()
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->registration->validateAndPrepare($request->all(), null, false);
        $existingParticipant = $this->registration->existingParticipantForGeneratedCode($data);

        if ($existingParticipant) {
            $pendingJoinRequest = $this->submitPendingTrainingEventJoinRequest($request, $existingParticipant);

            $this->audit()->logCustom('Public participant registration duplicate loaded existing record', 'participants.public_registration_duplicate', [
                'auditable_type' => Participant::class,
                'auditable_id' => $existingParticipant->id,
                'auditable_label' => $existingParticipant->name,
                'metadata' => [
                    'route' => 'participant-registration.store',
                    'participant_code' => $existingParticipant->participant_code,
                ],
            ]);

            $warningMessage = 'A participant with generated ID '.$existingParticipant->participant_code.' already exists. We loaded the previous record instead of creating a duplicate.';

            if ($pendingJoinRequest) {
                $warningMessage .= $pendingJoinRequest['join_request']
                    ? ' Your request to join '.$pendingJoinRequest['event_name'].' has also been submitted and is pending approval.'
                    : ' Your request to join '.$pendingJoinRequest['event_name'].' was already on file.';
            }

            return redirect()
                ->route('participant-registration.create')
                ->with('warning', $warningMessage)
                ->with('participant_registration', $this->participantRegistrationSession($existingParticipant))
                ->withInput($this->registration->formInput($existingParticipant));
        }

        $this->registration->ensureEmailIsAvailable($data['email'] ?? null);
        $participant = $this->registration->create($data);
        $pendingJoinRequest = $this->submitPendingTrainingEventJoinRequest($request, $participant);

        $this->audit()->logCustom('Public participant registration submitted', 'participants.public_registration', [
            'auditable_type' => Participant::class,
            'auditable_id' => $participant->id,
            'auditable_label' => $participant->name,
            'new_values' => $this->audit()->snapshotModel($participant),
            'metadata' => [
                'route' => 'participant-registration.store',
            ],
        ]);

        $successMessage = 'Registration submitted successfully.';

        if ($pendingJoinRequest) {
            $successMessage .= ' Your request to join '.$pendingJoinRequest['event_name'].' has also been submitted and is pending approval.';
        }

        return redirect()
            ->route('participant-registration.create')
            ->with('success', $successMessage)
            ->with('participant_registration', $this->participantRegistrationSession($participant));
    }

    public function organizationOptions(Request $request): JsonResponse
    {
        return response()->json([
            'options' => $this->registration->organizationOptions(
                $request->string('q')->toString(),
                $request->input('selected_id'),
                $request->input('region_id'),
                $request->input('zone_id'),
                $request->input('woreda_id')
            ),
        ]);
    }

    private function viewData(Request $request, array $data = []): array
    {
        $selectedOrganization = $this->registration->selectedOrganizationOption(
            old('organization_id', $request->query('organization_id'))
        );
        $pendingJoinRequest = $request->session()->get(self::PENDING_JOIN_REQUEST_SESSION_KEY);

        return $data + [
            'navigationPages' => ContentPage::published()->orderBy('title')->get(),
            'navigationMenu' => WebsiteMenuItem::tree(),
            'websiteSettings' => WebsiteSetting::current(),
            'selectedOrganization' => $selectedOrganization,
            'pendingTrainingEventJoinRequest' => is_array($pendingJoinRequest) ? $pendingJoinRequest : null,
        ];
    }

    private function submitPendingTrainingEventJoinRequest(Request $request, Participant $participant): ?array
    {
        $pendingJoinRequest = $request->session()->pull(self::PENDING_JOIN_REQUEST_SESSION_KEY);

        if (! is_array($pendingJoinRequest) || empty($pendingJoinRequest['training_event_id'])) {
            return null;
        }

        $event = TrainingEvent::query()
            ->whereIn('status', TrainingEvent::REQUESTABLE_STATUSES)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->find((int) $pendingJoinRequest['training_event_id']);

        if (! $event) {
            return null;
        }

        $enrollment = TrainingEventParticipant::query()
            ->where('training_event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($enrollment) {
            return [
                'event_name' => $event->event_name ?: 'Event #'.$event->id,
                'join_request' => null,
            ];
        }

        $joinRequest = TrainingEventJoinRequest::query()->firstOrNew([
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
        ]);

        $joinRequest->fill([
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => $pendingJoinRequest['requested_message'] ?? null,
            'reviewer_notes' => null,
            'requested_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'enrollment_id' => null,
        ]);
        $joinRequest->save();

        $this->audit()->logCustom('Public training event join request submitted after registration', 'training_event_join_requests.submitted_after_registration', [
            'auditable_type' => TrainingEventJoinRequest::class,
            'auditable_id' => $joinRequest->id,
            'auditable_label' => $participant->name.' -> '.$event->event_name,
            'metadata' => [
                'training_event_id' => $event->id,
                'participant_id' => $participant->id,
            ],
        ]);

        return [
            'event_name' => $event->event_name ?: 'Event #'.$event->id,
            'join_request' => $joinRequest,
        ];
    }

    private function participantRegistrationSession(Participant $participant): array
    {
        return [
            'participant_code' => $participant->participant_code,
            'name' => $participant->name,
        ];
    }
}
