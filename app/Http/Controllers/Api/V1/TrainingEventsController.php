<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TrainingEventResource;
use App\Models\ProjectSubawardee;
use App\Models\TrainingEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrainingEventsController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'training_events');

        $query = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])
            ->withCount('enrollments')
            ->orderByDesc('start_date');

        $this->applySearch($query, $request, ['event_name', 'status', 'training_city', 'course_venue']);

        foreach (['training_id', 'training_organizer_id', 'training_region_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (int) $request->integer($filter));
            }
        }

        if ($request->filled('organizer_type')) {
            $query->where('organizer_type', (string) $request->query('organizer_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('start_date_from')) {
            $query->whereDate('start_date', '>=', (string) $request->query('start_date_from'));
        }

        if ($request->filled('start_date_to')) {
            $query->whereDate('start_date', '<=', (string) $request->query('start_date_to'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), TrainingEventResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'training_events');

        $data = $this->validated($request);
        $trainingEvent = TrainingEvent::query()->create($data);

        return $this->itemResponse($trainingEvent->fresh()->load(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])->loadCount('enrollments'), TrainingEventResource::class, 201);
    }

    public function show(Request $request, TrainingEvent $trainingEvent)
    {
        $this->ensurePermission($request, 'training_events');

        return $this->itemResponse($trainingEvent->load(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])->loadCount('enrollments'), TrainingEventResource::class);
    }

    public function update(Request $request, TrainingEvent $trainingEvent)
    {
        $this->ensurePermission($request, 'training_events');

        $trainingEvent->update($this->validated($request));

        return $this->itemResponse($trainingEvent->fresh()->load(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])->loadCount('enrollments'), TrainingEventResource::class);
    }

    public function destroy(Request $request, TrainingEvent $trainingEvent)
    {
        $this->ensurePermission($request, 'training_events');

        $trainingEvent->delete();

        return $this->messageResponse('Training event deleted.');
    }

    private function validated(Request $request): array
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

        if (($data['organizer_type'] ?? null) !== 'Subawardee') {
            $data['project_subawardee_id'] = null;

            return $data;
        }

        $subawardeeId = (int) ($data['project_subawardee_id'] ?? 0);
        if ($subawardeeId <= 0) {
            throw ValidationException::withMessages([
                'project_subawardee_id' => 'Subawardee Name is required when organizer type is Subawardee.',
            ]);
        }

        $subawardee = ProjectSubawardee::query()->find($subawardeeId);
        if (! $subawardee || (int) $subawardee->project_id !== (int) ($data['training_organizer_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'project_subawardee_id' => 'Selected subawardee does not belong to the selected project.',
            ]);
        }

        return $data;
    }
}
