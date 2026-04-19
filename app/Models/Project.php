<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_id',
        'project_category_id',
        'title',
        'coaching_visit_1',
        'coaching_visit_1_notes',
        'coaching_visit_2',
        'coaching_visit_2_notes',
        'coaching_visit_3',
        'coaching_visit_3_notes',
        'project_file',
    ];

    protected $casts = [
        'coaching_visit_1' => 'date',
        'coaching_visit_2' => 'date',
        'coaching_visit_3' => 'date',
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function participants()
    {
        return $this->belongsToMany(Participant::class, 'project_participants')
            ->withTimestamps();
    }

    public function getParticipantIdsAttribute(): array
    {
        if ($this->relationLoaded('participants')) {
            $ids = $this->participants->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $ids = $this->participants()->pluck('participants.id')->map(fn ($id) => (int) $id)->all();
        }

        if (empty($ids) && $this->participant_id) {
            $ids = [(int) $this->participant_id];
        }

        return $ids;
    }

    public function getParticipantsListAttribute(): string
    {
        if ($this->relationLoaded('participants')) {
            $names = $this->participants->pluck('name')->filter()->values();
        } else {
            $names = $this->participants()->pluck('participants.name')->filter()->values();
        }

        if ($names->isEmpty() && $this->participant?->name) {
            return (string) $this->participant->name;
        }

        return $names->implode(', ');
    }

    public function projectCategory()
    {
        return $this->belongsTo(ProjectCategory::class);
    }
}
