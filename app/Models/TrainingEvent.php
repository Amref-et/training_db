<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'training_id', 'training_organizer_id', 'organizer_type', 'project_subawardee_id', 'training_region_id', 'participant_id',
        'training_city', 'course_venue', 'workshop_count',
        'start_date', 'end_date', 'status',
    ];

    protected $casts = [
        'workshop_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $trainingEvent): void {
            if (! $trainingEvent->wasChanged('workshop_count')) {
                return;
            }

            $trainingEvent->loadMissing('enrollments');

            foreach ($trainingEvent->enrollments as $enrollment) {
                $enrollment->refreshFinalScore();
            }
        });
    }

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function trainingOrganizer()
    {
        return $this->belongsTo(TrainingOrganizer::class);
    }

    public function projectSubawardee()
    {
        return $this->belongsTo(ProjectSubawardee::class, 'project_subawardee_id');
    }

    public function trainingRegion()
    {
        return $this->belongsTo(Region::class, 'training_region_id');
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function enrollments()
    {
        return $this->hasMany(TrainingEventParticipant::class)->orderBy('id');
    }

    public function workshopScores()
    {
        return $this->hasManyThrough(
            TrainingEventWorkshopScore::class,
            TrainingEventParticipant::class,
            'training_event_id',
            'training_event_participant_id'
        );
    }

    public function workshops()
    {
        return $this->hasMany(TrainingEventWorkshop::class)->orderBy('workshop_number');
    }

    public function rounds()
    {
        return $this->hasMany(TrainingRound::class)->orderBy('round_number');
    }

    public function getDisplayLabelAttribute(): string
    {
        $name = trim((string) ($this->event_name ?: ($this->training?->title ?? 'Training Event #'.$this->id)));
        $start = $this->start_date ? (string) $this->start_date : null;
        $end = $this->end_date ? (string) $this->end_date : null;

        if ($start || $end) {
            return $name.' ['.($start ?: '-').' to '.($end ?: '-').']';
        }

        return $name;
    }
}
