<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    use HasFactory;

    protected $fillable = ['training_category_id', 'title', 'description', 'modality', 'type'];

    public function trainingCategory()
    {
        return $this->belongsTo(TrainingCategory::class);
    }

    public function trainingEvents()
    {
        return $this->hasMany(TrainingEvent::class);
    }

    public function trainingMaterials()
    {
        return $this->hasMany(TrainingMaterial::class);
    }
}
