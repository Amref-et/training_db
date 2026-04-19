<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function index()
    {
        return Participant::with(['region', 'zone', 'woreda', 'organization'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'grandfather_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:120',
            'region_id' => 'required|exists:regions,id',
            'zone_id' => 'required|exists:zones,id',
            'woreda_id' => 'required|exists:woredas,id',
            'organization_id' => 'required|exists:organizations,id',
            'gender' => 'required|in:male,female',
            'mobile_phone' => 'required|string|max:20',
            'email' => 'required|email|unique:participants,email',
            'profession' => 'required|string|max:255|exists:professions,name',
        ]);

        return Participant::create($data);
    }

    public function show(Participant $participant)
    {
        return $participant->load(['region', 'zone', 'woreda', 'organization']);
    }

    public function update(Request $request, Participant $participant)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'grandfather_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:120',
            'region_id' => 'required|exists:regions,id',
            'zone_id' => 'required|exists:zones,id',
            'woreda_id' => 'required|exists:woredas,id',
            'organization_id' => 'required|exists:organizations,id',
            'gender' => 'required|in:male,female',
            'mobile_phone' => 'required|string|max:20',
            'email' => 'required|email|unique:participants,email,'.$participant->id.',id',
            'profession' => 'required|string|max:255|exists:professions,name',
        ]);

        $participant->update($data);
        return $participant;
    }

    public function destroy(Participant $participant)
    {
        $participant->delete();
        return response()->noContent();
    }
}
