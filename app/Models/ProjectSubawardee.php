<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectSubawardee extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'subawardee_name',
    ];

    public function project()
    {
        return $this->belongsTo(TrainingOrganizer::class, 'project_id');
    }
}
