<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingEventWorkshopScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_event_participant_id',
        'workshop_number',
        'pre_test_score',
        'mid_test_score',
        'post_test_score',
    ];

    protected $casts = [
        'workshop_number' => 'integer',
        'pre_test_score' => 'decimal:2',
        'mid_test_score' => 'decimal:2',
        'post_test_score' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $score): void {
            $score->trainingEventParticipant?->refreshFinalScore();
        });

        static::deleted(function (self $score): void {
            $score->trainingEventParticipant?->refreshFinalScore();
        });
    }

    public function trainingEventParticipant()
    {
        return $this->belongsTo(TrainingEventParticipant::class);
    }
}
