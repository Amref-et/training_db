<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingEventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_event_id',
        'participant_id',
        'final_score',
        'mid_test_score',
        'activity_completion_status',
        'is_trainer',
        'trainer_comments',
        'trainer_name',
        'trainer_signature',
    ];

    protected $casts = [
        'final_score' => 'decimal:2',
        'mid_test_score' => 'decimal:2',
        'is_trainer' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $enrollment): void {
            $enrollment->syncEventAggregates();
        });

        static::deleted(function (self $enrollment): void {
            $enrollment->syncEventAggregates();
        });
    }

    public function trainingEvent()
    {
        return $this->belongsTo(TrainingEvent::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function workshopScores()
    {
        return $this->hasMany(TrainingEventWorkshopScore::class)->orderBy('workshop_number');
    }

    public function getDisplayLabelAttribute(): string
    {
        $event = trim((string) ($this->trainingEvent?->event_name ?? 'Event #'.$this->training_event_id));
        $participant = trim((string) ($this->participant?->name ?? 'Participant #'.$this->participant_id));

        return $event.' - '.$participant;
    }

    public function refreshFinalScore(): void
    {
        $requiredWorkshopCount = max(1, (int) ($this->trainingEvent?->workshop_count ?? 4));

        $aggregate = $this->workshopScores()
            ->where('workshop_number', '<=', $requiredWorkshopCount)
            ->selectRaw('AVG(post_test_score) as avg_post, SUM(CASE WHEN post_test_score IS NOT NULL THEN 1 ELSE 0 END) as post_count')
            ->first();

        $finalScore = ((int) ($aggregate?->post_count ?? 0) >= $requiredWorkshopCount && $aggregate?->avg_post !== null)
            ? round((float) $aggregate->avg_post, 2)
            : null;

        $this->forceFill(['final_score' => $finalScore])->saveQuietly();

        $this->syncEventAggregates();
    }

    private function syncEventAggregates(): void
    {
        $eventId = $this->training_event_id;

        if (! $eventId) {
            return;
        }

        $event = TrainingEvent::query()->select(['id', 'workshop_count'])->find($eventId);
        if (! $event) {
            return;
        }

        $requiredWorkshopCount = max(1, (int) ($event->workshop_count ?? 4));

        $avgPre = TrainingEventWorkshopScore::query()
            ->where('workshop_number', '<=', $requiredWorkshopCount)
            ->whereHas('trainingEventParticipant', fn ($query) => $query->where('training_event_id', $eventId))
            ->avg('pre_test_score');

        $avgFinal = self::query()
            ->where('training_event_id', $eventId)
            ->avg('final_score');

        TrainingEvent::query()
            ->whereKey($eventId)
            ->update([
                'pre_test_score' => $avgPre !== null ? round((float) $avgPre, 2) : null,
                'post_test_score' => $avgFinal !== null ? round((float) $avgFinal, 2) : null,
            ]);
    }
}
