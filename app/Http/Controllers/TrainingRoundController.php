<?php

namespace App\Http\Controllers;

use App\Models\TrainingRound;
use Illuminate\Http\Request;

class TrainingRoundController extends Controller
{
    public function index()
    {
        return TrainingRound::with(['trainingEvent', 'trainingEvent.training', 'trainingEvent.participant'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'training_event_id' => 'required|exists:training_events,id',
            'round_number' => 'required|integer|min:1|max:4',
            'workshop_title' => 'nullable|string|max:255',
            'round_start_date' => 'nullable|date',
            'round_end_date' => 'nullable|date|after_or_equal:round_start_date',
            'pre_test_score' => 'nullable|numeric|min:0|max:100',
            'post_test_score' => 'nullable|numeric|min:0|max:100',
        ]);

        return TrainingRound::create($data);
    }

    public function show(TrainingRound $trainingRound)
    {
        return $trainingRound->load(['trainingEvent', 'trainingEvent.training', 'trainingEvent.participant']);
    }

    public function update(Request $request, TrainingRound $trainingRound)
    {
        $data = $request->validate([
            'training_event_id' => 'required|exists:training_events,id',
            'round_number' => 'required|integer|min:1|max:4',
            'workshop_title' => 'nullable|string|max:255',
            'round_start_date' => 'nullable|date',
            'round_end_date' => 'nullable|date|after_or_equal:round_start_date',
            'pre_test_score' => 'nullable|numeric|min:0|max:100',
            'post_test_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $trainingRound->update($data);

        return $trainingRound;
    }

    public function destroy(TrainingRound $trainingRound)
    {
        $trainingRound->delete();

        return response()->noContent();
    }
}
