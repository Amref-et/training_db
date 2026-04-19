<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TrainingOrganizerResource;
use App\Models\TrainingOrganizer;
use Illuminate\Http\Request;

class TrainingOrganizersController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'training_organizers');

        $query = TrainingOrganizer::query()->with('subawardees')->orderBy('project_name');
        $this->applySearch($query, $request, ['project_code', 'project_name', 'title']);

        return $this->paginatedResponse($query->paginate($this->perPage($request)), TrainingOrganizerResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'training_organizers');

        [$data, $subawardees] = $this->validated($request);
        $organizer = TrainingOrganizer::query()->create($data);
        $this->syncSubawardees($organizer, $subawardees);

        return $this->itemResponse($organizer->fresh()->load('subawardees'), TrainingOrganizerResource::class, 201);
    }

    public function show(Request $request, TrainingOrganizer $trainingOrganizer)
    {
        $this->ensurePermission($request, 'training_organizers');

        return $this->itemResponse($trainingOrganizer->load('subawardees'), TrainingOrganizerResource::class);
    }

    public function update(Request $request, TrainingOrganizer $trainingOrganizer)
    {
        $this->ensurePermission($request, 'training_organizers');

        [$data, $subawardees] = $this->validated($request, $trainingOrganizer);
        $trainingOrganizer->update($data);
        $this->syncSubawardees($trainingOrganizer, $subawardees);

        return $this->itemResponse($trainingOrganizer->fresh()->load('subawardees'), TrainingOrganizerResource::class);
    }

    public function destroy(Request $request, TrainingOrganizer $trainingOrganizer)
    {
        $this->ensurePermission($request, 'training_organizers');

        $trainingOrganizer->delete();

        return $this->messageResponse('Training organizer deleted.');
    }

    private function validated(Request $request, ?TrainingOrganizer $trainingOrganizer = null): array
    {
        $data = $request->validate([
            'project_code' => 'required|string|max:255|unique:training_organizers,project_code,'.($trainingOrganizer?->id ?? 'NULL').',id',
            'project_name' => 'required|string|max:255',
            'subawardees' => 'nullable|array',
            'subawardees.*' => 'nullable|string|max:255|distinct',
        ]);

        $subawardees = $data['subawardees'] ?? [];
        unset($data['subawardees']);

        return [$data, $subawardees];
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

        if ($names !== []) {
            $organizer->subawardees()->createMany(
                collect($names)->map(fn (string $name) => ['subawardee_name' => $name])->all()
            );
        }
    }
}
