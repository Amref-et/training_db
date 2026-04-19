<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'title',
        'description',
        'resource_file',
        'external_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'training_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }
}

