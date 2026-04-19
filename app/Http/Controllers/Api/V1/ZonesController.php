<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ZoneResource;
use App\Models\Region;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZonesController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'zones');

        $query = Zone::query()->with('region')->orderBy('name');
        $this->applySearch($query, $request, ['name', 'description']);

        if ($request->filled('region_id')) {
            $query->where('region_id', (int) $request->integer('region_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), ZoneResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'zones');

        $data = $request->validate([
            'region_id' => 'required|exists:regions,id',
            'name' => 'required|string|max:255|unique:zones,name',
            'description' => 'nullable|string',
        ]);

        $zone = Zone::query()->create($data)->load('region');

        return $this->itemResponse($zone, ZoneResource::class, 201);
    }

    public function show(Request $request, Zone $zone)
    {
        $this->ensurePermission($request, 'zones');

        return $this->itemResponse($zone->load('region'), ZoneResource::class);
    }

    public function update(Request $request, Zone $zone)
    {
        $this->ensurePermission($request, 'zones');

        $data = $request->validate([
            'region_id' => 'required|exists:regions,id',
            'name' => 'required|string|max:255|unique:zones,name,'.$zone->id,
            'description' => 'nullable|string',
        ]);

        $zone->update($data);

        return $this->itemResponse($zone->fresh()->load('region'), ZoneResource::class);
    }

    public function destroy(Request $request, Zone $zone)
    {
        $this->ensurePermission($request, 'zones');

        $zone->delete();

        return $this->messageResponse('Zone deleted.');
    }
}
