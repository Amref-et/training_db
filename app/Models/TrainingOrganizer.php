<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingOrganizer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'project_code',
        'project_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $organizer): void {
            $projectName = trim((string) $organizer->project_name);
            $title = trim((string) $organizer->title);

            if ($projectName !== '') {
                $organizer->project_name = $projectName;
                $organizer->title = $projectName;
            } elseif ($title !== '') {
                $organizer->title = $title;
                $organizer->project_name = $title;
            }
        });
    }

    public function trainingEvents()
    {
        return $this->hasMany(TrainingEvent::class);
    }

    public function subawardees()
    {
        return $this->hasMany(ProjectSubawardee::class, 'project_id');
    }

    public function projectSubawardeeEvents()
    {
        return $this->hasMany(TrainingEvent::class, 'training_organizer_id');
    }

    public function getSubawardeesListAttribute(): string
    {
        return $this->subawardees
            ->pluck('subawardee_name')
            ->filter()
            ->implode(', ');
    }
}
