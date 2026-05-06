<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingEventJoinRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'training_event_id',
        'participant_id',
        'status',
        'requested_message',
        'reviewer_notes',
        'requested_at',
        'reviewed_at',
        'reviewed_by',
        'enrollment_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function trainingEvent()
    {
        return $this->belongsTo(TrainingEvent::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function enrollment()
    {
        return $this->belongsTo(TrainingEventParticipant::class, 'enrollment_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status ?: self::STATUS_PENDING);
    }
}
