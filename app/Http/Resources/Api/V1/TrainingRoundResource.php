<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingRoundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_event_id' => $this->training_event_id,
            'round_number' => $this->round_number,
            'workshop_title' => $this->workshop_title,
            'round_start_date' => $this->round_start_date?->toDateString(),
            'round_end_date' => $this->round_end_date?->toDateString(),
            'training_event' => $this->whenLoaded('trainingEvent', fn () => [
                'id' => $this->trainingEvent?->id,
                'event_name' => $this->trainingEvent?->event_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
