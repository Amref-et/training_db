<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\RegionResource;
use App\Models\Region;
use Illuminate\Http\Request;

class RegionsController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'regions');

        $query = Region::query()->orderBy('name');
        $this->applySearch($query, $request, ['name']);

        return $this->paginatedResponse($query->paginate($this->perPage($request)), RegionResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'regions');

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:regions,name',
        ]);

        $region = Region::query()->create($data);

        return $this->itemResponse($region, RegionResource::class, 201);
    }

    public function show(Request $request, Region $region)
    {
        $this->ensurePermission($request, 'regions');

        return $this->itemResponse($region, RegionResource::class);
    }

    public function update(Request $request, Region $region)
    {
        $this->ensurePermission($request, 'regions');

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:regions,name,'.$region->id,
        ]);

        $region->update($data);

        return $this->itemResponse($region->fresh(), RegionResource::class);
    }

    public function destroy(Request $request, Region $region)
    {
        $this->ensurePermission($request, 'regions');

        $region->delete();

        return $this->messageResponse('Region deleted.');
    }
}
