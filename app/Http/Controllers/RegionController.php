<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index()
    {
        return Region::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'region_name' => 'required|string|max:255|unique:regions'
        ]);

        return Region::create($request->all());
    }

    public function show(Region $region)
    {
        return $region;
    }

    public function update(Request $request, Region $region)
    {
        $request->validate([
            'region_name' => 'required|string|max:255|unique:regions,region_name,'.$region->region_id.',region_id'
        ]);

        $region->update($request->all());
        return $region;
    }

    public function destroy(Region $region)
    {
        $region->delete();
        return response()->noContent();
    }
}