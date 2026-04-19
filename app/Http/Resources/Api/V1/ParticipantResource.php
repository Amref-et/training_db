<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'participant_code' => $this->participant_code,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'gender' => $this->gender,
            'home_phone' => $this->home_phone,
            'mobile_phone' => $this->mobile_phone,
            'email' => $this->email,
            'profession' => $this->profession,
            'region' => $this->whenLoaded('region', fn () => new RegionResource($this->region)),
            'zone' => $this->whenLoaded('zone', fn () => new ZoneResource($this->zone)),
            'woreda' => $this->whenLoaded('woreda', fn () => new WoredaResource($this->woreda)),
            'organization' => $this->whenLoaded('organization', fn () => new OrganizationResource($this->organization)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
