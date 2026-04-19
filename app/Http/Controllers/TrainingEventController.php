<?php

namespace App\Http\Controllers;

use App\Models\ProjectSubawardee;
use App\Models\TrainingEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrainingEventController extends Controller
{
    public function index()
    {
        return TrainingEvent::with(['training', 'trainingOrganizer', 'projectSubawardee', 'enrollments.participant', 'enrollments.workshopScores'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_name' => 'required|string|max:255',
            'training_id' => 'required|exists:trainings,id',
            'training_organizer_id' => 'required|exists:training_organizers,id',
            'organizer_type' => 'required|in:The project,Subawardee',
            'project_subawardee_id' => 'nullable|exists:project_subawardees,id',
            'training_region_id' => 'nullable|exists:regions,id',
            'training_city' => 'nullable|string|max:255',
            'course_venue' => 'nullable|string|max:255',
            'workshop_count' => 'nullable|integer|min:1|max:20',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:Pending,Ongoing,Completed,Cancelled',
        ]);

        $this->validateOrganizerSelection($data);

        return TrainingEvent::create($data);
    }

    public function show(TrainingEvent $trainingEvent)
    {
        return $trainingEvent->load(['training', 'trainingOrganizer', 'projectSubawardee', 'enrollments.participant', 'enrollments.workshopScores']);
    }

    public function update(Request $request, TrainingEvent $trainingEvent)
    {
        $data = $request->validate([
            'event_name' => 'required|string|max:255',
            'training_id' => 'required|exists:trainings,id',
            'training_organizer_id' => 'required|exists:training_organizers,id',
            'organizer_type' => 'required|in:The project,Subawardee',
            'project_subawardee_id' => 'nullable|exists:project_subawardees,id',
            'training_region_id' => 'nullable|exists:regions,id',
            'training_city' => 'nullable|string|max:255',
            'course_venue' => 'nullable|string|max:255',
            'workshop_count' => 'nullable|integer|min:1|max:20',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:Pending,Ongoing,Completed,Cancelled',
        ]);

        $this->validateOrganizerSelection($data);

        $trainingEvent->update($data);
        return $trainingEvent;
    }

    public function destroy(TrainingEvent $trainingEvent)
    {
        $trainingEvent->delete();
        return response()->noContent();
    }

    private function validateOrganizerSelection(array &$data): void
    {
        if (($data['organizer_type'] ?? null) !== 'Subawardee') {
            $data['project_subawardee_id'] = null;

            return;
        }

        $subawardeeId = (int) ($data['project_subawardee_id'] ?? 0);
        if ($subawardeeId <= 0) {
            throw ValidationException::withMessages([
                'project_subawardee_id' => 'Subawardee Name is required when Type of Organizer is Subawardee.',
            ]);
        }

        $subawardee = ProjectSubawardee::query()->find($subawardeeId);
        if (! $subawardee || (int) $subawardee->project_id !== (int) ($data['training_organizer_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'project_subawardee_id' => 'Selected Subawardee does not belong to the selected Organizer.',
            ]);
        }
    }
}
