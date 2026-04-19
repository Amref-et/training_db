<?php

namespace App\Http\Controllers;

use App\Models\Training;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function index()
    {
        return Training::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'training_title' => 'required|string|max:255',
            'training_description' => 'required|string',
            'modality' => 'required|in:Face 2 Face,Online,Blended',
            'type' => 'required|in:Basic,Refresher,ToT'
        ]);

        return Training::create($request->all());
    }

    public function show(Training $training)
    {
        return $training;
    }

    public function update(Request $request, Training $training)
    {
        $request->validate([
            'training_title' => 'required|string|max:255',
            'training_description' => 'required|string',
            'modality' => 'required|in:Face 2 Face,Online,Blended',
            'type' => 'required|in:Basic,Refresher,ToT'
        ]);

        $training->update($request->all());
        return $training;
    }

    public function destroy(Training $training)
    {
        $training->delete();
        return response()->noContent();
    }
}