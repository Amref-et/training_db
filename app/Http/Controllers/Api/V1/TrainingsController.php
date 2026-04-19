<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\TrainingResource;
use App\Models\Training;
use Illuminate\Http\Request;

class TrainingsController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'trainings');

        $query = Training::query()->with('trainingCategory')->orderBy('title');
        $this->applySearch($query, $request, ['title', 'description', 'modality', 'type']);

        if ($request->filled('training_category_id')) {
            $query->where('training_category_id', (int) $request->integer('training_category_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), TrainingResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'trainings');

        $training = Training::query()->create($this->validated($request))->load('trainingCategory');

        return $this->itemResponse($training, TrainingResource::class, 201);
    }

    public function show(Request $request, Training $training)
    {
        $this->ensurePermission($request, 'trainings');

        return $this->itemResponse($training->load('trainingCategory'), TrainingResource::class);
    }

    public function update(Request $request, Training $training)
    {
        $this->ensurePermission($request, 'trainings');

        $training->update($this->validated($request));

        return $this->itemResponse($training->fresh()->load('trainingCategory'), TrainingResource::class);
    }

    public function destroy(Request $request, Training $training)
    {
        $this->ensurePermission($request, 'trainings');

        $training->delete();

        return $this->messageResponse('Training deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'training_category_id' => 'required|exists:training_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'modality' => 'required|in:Face 2 face,Online,Blended',
            'type' => 'required|in:Basic,Refresher,ToT',
        ]);
    }
}
