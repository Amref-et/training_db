<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_event_id',
        'round_number',
        'workshop_title',
        'round_start_date',
        'round_end_date',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'round_start_date' => 'date',
        'round_end_date' => 'date',
    ];

    public function trainingEvent()
    {
        return $this->belongsTo(TrainingEvent::class);
    }
}
