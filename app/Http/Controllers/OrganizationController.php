<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        return Organization::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'organization_name' => 'required|string|max:255',
            'organization_category' => 'required|in:Government,NGO,Private,International',
            'organization_type' => 'required|in:Education,Health,Agriculture,Other'
        ]);

        return Organization::create($request->all());
    }

    public function show(Organization $organization)
    {
        return $organization;
    }

    public function update(Request $request, Organization $organization)
    {
        $request->validate([
            'organization_name' => 'required|string|max:255',
            'organization_category' => 'required|in:Government,NGO,Private,International',
            'organization_type' => 'required|in:Education,Health,Agriculture,Other'
        ]);

        $organization->update($request->all());
        return $organization;
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();
        return response()->noContent();
    }
}