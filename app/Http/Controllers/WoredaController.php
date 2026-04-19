<?php

namespace App\Http\Controllers;

use App\Models\Woreda;
use Illuminate\Http\Request;

class WoredaController extends Controller
{
    public function index()
    {
        return Woreda::with('region')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'region_id' => 'required|exists:regions,region_id',
            'woreda_name' => 'required|string|max:255',
            'woreda_description' => 'nullable|string'
        ]);

        return Woreda::create($request->all());
    }

    public function show(Woreda $woreda)
    {
        return $woreda->load('region');
    }

    public function update(Request $request, Woreda $woreda)
    {
        $request->validate([
            'region_id' => 'required|exists:regions,region_id',
            'woreda_name' => 'required|string|max:255',
            'woreda_description' => 'nullable|string'
        ]);

        $woreda->update($request->all());
        return $woreda;
    }

    public function destroy(Woreda $woreda)
    {
        $woreda->delete();
        return response()->noContent();
    }

    public function byRegion($regionId)
    {
        return Woreda::where('region_id', $regionId)->get();
    }
}