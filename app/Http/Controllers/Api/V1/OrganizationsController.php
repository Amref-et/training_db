<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use App\Models\Woreda;
use App\Models\Zone;
use Illuminate\Http\Request;

class OrganizationsController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'organizations');

        $query = Organization::query()->with(['region', 'zoneDefinition', 'woreda'])->orderBy('name');
        $this->applySearch($query, $request, ['name', 'category', 'type', 'city_town', 'phone']);

        foreach (['region_id', 'zone_id', 'woreda_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (int) $request->integer($filter));
            }
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), OrganizationResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'organizations');

        $data = $this->validated($request);
        $organization = Organization::query()->create($data)->load(['region', 'zoneDefinition', 'woreda']);

        return $this->itemResponse($organization, OrganizationResource::class, 201);
    }

    public function show(Request $request, Organization $organization)
    {
        $this->ensurePermission($request, 'organizations');

        return $this->itemResponse($organization->load(['region', 'zoneDefinition', 'woreda']), OrganizationResource::class);
    }

    public function update(Request $request, Organization $organization)
    {
        $this->ensurePermission($request, 'organizations');

        $data = $this->validated($request);
        $organization->update($data);

        return $this->itemResponse($organization->fresh()->load(['region', 'zoneDefinition', 'woreda']), OrganizationResource::class);
    }

    public function destroy(Request $request, Organization $organization)
    {
        $this->ensurePermission($request, 'organizations');

        $organization->delete();

        return $this->messageResponse('Organization deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'region_id' => 'nullable|exists:regions,id',
            'zone_id' => 'nullable|exists:zones,id',
            'woreda_id' => 'nullable|exists:woredas,id',
            'city_town' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'fax' => 'nullable|string|max:30',
        ]);

        $zone = ! empty($data['zone_id']) ? Zone::query()->find((int) $data['zone_id']) : null;
        $woreda = ! empty($data['woreda_id']) ? Woreda::query()->find((int) $data['woreda_id']) : null;

        if ($woreda && $zone && (int) $woreda->zone_id !== (int) $zone->id) {
            abort(422, 'Selected woreda does not belong to the selected zone.');
        }

        if ($woreda && ! $zone) {
            $zone = $woreda->zone;
            $data['zone_id'] = $zone?->id;
        }

        if ($zone) {
            $data['region_id'] = $zone->region_id;
            $data['zone'] = $zone->name;
        }

        return $data;
    }
}
