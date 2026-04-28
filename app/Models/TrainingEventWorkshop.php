<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingEventWorkshop extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_event_id',
        'workshop_number',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'workshop_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function trainingEvent()
    {
        return $this->belongsTo(TrainingEvent::class);
    }
}
