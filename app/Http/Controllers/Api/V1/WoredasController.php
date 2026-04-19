<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\WoredaResource;
use App\Models\Woreda;
use App\Models\Zone;
use Illuminate\Http\Request;

class WoredasController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'woredas');

        $query = Woreda::query()->with(['region', 'zone'])->orderBy('name');
        $this->applySearch($query, $request, ['name', 'description']);

        if ($request->filled('region_id')) {
            $query->where('region_id', (int) $request->integer('region_id'));
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', (int) $request->integer('zone_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), WoredaResource::class);
    }

    public function byRegion(Request $request, int $regionId)
    {
        $request->merge(['region_id' => $regionId]);

        return $this->index($request);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'woredas');

        $data = $request->validate([
            'region_id' => 'nullable|exists:regions,id',
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $zone = Zone::query()->findOrFail((int) $data['zone_id']);
        $data['region_id'] = (int) $zone->region_id;

        $woreda = Woreda::query()->create($data)->load(['region', 'zone']);

        return $this->itemResponse($woreda, WoredaResource::class, 201);
    }

    public function show(Request $request, Woreda $woreda)
    {
        $this->ensurePermission($request, 'woredas');

        return $this->itemResponse($woreda->load(['region', 'zone']), WoredaResource::class);
    }

    public function update(Request $request, Woreda $woreda)
    {
        $this->ensurePermission($request, 'woredas');

        $data = $request->validate([
            'region_id' => 'nullable|exists:regions,id',
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $zone = Zone::query()->findOrFail((int) $data['zone_id']);
        $data['region_id'] = (int) $zone->region_id;

        $woreda->update($data);

        return $this->itemResponse($woreda->fresh()->load(['region', 'zone']), WoredaResource::class);
    }

    public function destroy(Request $request, Woreda $woreda)
    {
        $this->ensurePermission($request, 'woredas');

        $woreda->delete();

        return $this->messageResponse('Woreda deleted.');
    }
}
