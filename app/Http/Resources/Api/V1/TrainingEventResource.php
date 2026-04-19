<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_name' => $this->event_name,
            'organizer_type' => $this->organizer_type,
            'training_city' => $this->training_city,
            'course_venue' => $this->course_venue,
            'workshop_count' => $this->workshop_count,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'training' => $this->whenLoaded('training', fn () => [
                'id' => $this->training?->id,
                'title' => $this->training?->title,
            ]),
            'project' => $this->whenLoaded('trainingOrganizer', fn () => [
                'id' => $this->trainingOrganizer?->id,
                'project_code' => $this->trainingOrganizer?->project_code,
                'project_name' => $this->trainingOrganizer?->project_name ?: $this->trainingOrganizer?->title,
            ]),
            'subawardee' => $this->whenLoaded('projectSubawardee', fn () => [
                'id' => $this->projectSubawardee?->id,
                'name' => $this->projectSubawardee?->subawardee_name,
            ]),
            'training_region' => $this->whenLoaded('trainingRegion', fn () => new RegionResource($this->trainingRegion)),
            'participants_count' => $this->whenCounted('enrollments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
