<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Participant;
use App\Models\WebsiteMenuItem;
use App\Models\WebsiteSetting;
use App\Services\ParticipantRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicParticipantRegistrationController extends Controller
{
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
        $data = $this->registration->validateAndPrepare($request->all());
        $participant = $this->registration->create($data);

        $this->audit()->logCustom('Public participant registration submitted', 'participants.public_registration', [
            'auditable_type' => Participant::class,
            'auditable_id' => $participant->id,
            'auditable_label' => $participant->name,
            'new_values' => $this->audit()->snapshotModel($participant),
            'metadata' => [
                'route' => 'participant-registration.store',
            ],
        ]);

        return redirect()
            ->route('participant-registration.create')
            ->with('success', 'Registration submitted successfully.')
            ->with('participant_registration', [
                'participant_code' => $participant->participant_code,
                'name' => $participant->name,
            ]);
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

        return $data + [
            'navigationPages' => ContentPage::published()->orderBy('title')->get(),
            'navigationMenu' => WebsiteMenuItem::tree(),
            'websiteSettings' => WebsiteSetting::current(),
            'selectedOrganization' => $selectedOrganization,
        ];
    }
}
