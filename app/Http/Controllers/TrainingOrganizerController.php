<?php

namespace App\Http\Controllers;

use App\Models\TrainingOrganizer;
use Illuminate\Http\Request;

class TrainingOrganizerController extends Controller
{
    public function index()
    {
        return TrainingOrganizer::with('subawardees')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_code' => 'required|string|max:255|unique:training_organizers,project_code',
            'project_name' => 'required|string|max:255',
            'subawardees' => 'nullable|array',
            'subawardees.*' => 'nullable|string|max:255|distinct',
        ]);

        $subawardees = $data['subawardees'] ?? [];
        unset($data['subawardees']);

        $organizer = TrainingOrganizer::create($data);
        $this->syncSubawardees($organizer, $subawardees);

        return $organizer->load('subawardees');
    }

    public function show(TrainingOrganizer $trainingOrganizer)
    {
        return $trainingOrganizer->load('subawardees');
    }

    public function update(Request $request, TrainingOrganizer $trainingOrganizer)
    {
        $data = $request->validate([
            'project_code' => 'required|string|max:255|unique:training_organizers,project_code,'.$trainingOrganizer->id.',id',
            'project_name' => 'required|string|max:255',
            'subawardees' => 'nullable|array',
            'subawardees.*' => 'nullable|string|max:255|distinct',
        ]);

        $subawardees = $data['subawardees'] ?? [];
        unset($data['subawardees']);

        $trainingOrganizer->update($data);
        $this->syncSubawardees($trainingOrganizer, $subawardees);

        return $trainingOrganizer->load('subawardees');
    }

    public function destroy(TrainingOrganizer $trainingOrganizer)
    {
        $trainingOrganizer->delete();
        return response()->noContent();
    }

    private function syncSubawardees(TrainingOrganizer $organizer, array $values): void
    {
        $names = collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $organizer->subawardees()->delete();

        if (! empty($names)) {
            $organizer->subawardees()->createMany(
                collect($names)->map(fn (string $name) => ['subawardee_name' => $name])->all()
            );
        }
    }
}
