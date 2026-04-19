<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TrainingRoundResource;
use App\Models\TrainingRound;
use Illuminate\Http\Request;

class TrainingRoundsController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'training_rounds');

        $query = TrainingRound::query()->with('trainingEvent')->orderByDesc('id');

        if ($request->filled('training_event_id')) {
            $query->where('training_event_id', (int) $request->integer('training_event_id'));
        }

        if ($request->filled('round_number')) {
            $query->where('round_number', (int) $request->integer('round_number'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), TrainingRoundResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'training_rounds');

        $trainingRound = TrainingRound::query()->create($this->validated($request))->load('trainingEvent');

        return $this->itemResponse($trainingRound, TrainingRoundResource::class, 201);
    }

    public function show(Request $request, TrainingRound $trainingRound)
    {
        $this->ensurePermission($request, 'training_rounds');

        return $this->itemResponse($trainingRound->load('trainingEvent'), TrainingRoundResource::class);
    }

    public function update(Request $request, TrainingRound $trainingRound)
    {
        $this->ensurePermission($request, 'training_rounds');

        $trainingRound->update($this->validated($request));

        return $this->itemResponse($trainingRound->fresh()->load('trainingEvent'), TrainingRoundResource::class);
    }

    public function destroy(Request $request, TrainingRound $trainingRound)
    {
        $this->ensurePermission($request, 'training_rounds');

        $trainingRound->delete();

        return $this->messageResponse('Training round deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'training_event_id' => 'required|exists:training_events,id',
            'round_number' => 'required|integer|min:1|max:20',
            'workshop_title' => 'nullable|string|max:255',
            'round_start_date' => 'nullable|date',
            'round_end_date' => 'nullable|date|after_or_equal:round_start_date',
        ]);
    }
}
