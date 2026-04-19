<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'project_category' => $this->whenLoaded('projectCategory', fn () => [
                'id' => $this->projectCategory?->id,
                'name' => $this->projectCategory?->name,
            ]),
            'participant_ids' => $this->participant_ids,
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'name' => $participant->name,
                'participant_code' => $participant->participant_code,
            ])->values()->all()),
            'coaching_visit_1' => $this->coaching_visit_1?->toDateString(),
            'coaching_visit_1_notes' => $this->coaching_visit_1_notes,
            'coaching_visit_2' => $this->coaching_visit_2?->toDateString(),
            'coaching_visit_2_notes' => $this->coaching_visit_2_notes,
            'coaching_visit_3' => $this->coaching_visit_3?->toDateString(),
            'coaching_visit_3_notes' => $this->coaching_visit_3_notes,
            'project_file' => $this->project_file,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
