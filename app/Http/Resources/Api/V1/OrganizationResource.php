<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'type' => $this->type,
            'city_town' => $this->city_town,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'region' => $this->whenLoaded('region', fn () => new RegionResource($this->region)),
            'zone' => $this->whenLoaded('zoneDefinition', fn () => new ZoneResource($this->zoneDefinition)),
            'woreda' => $this->whenLoaded('woreda', fn () => new WoredaResource($this->woreda)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
