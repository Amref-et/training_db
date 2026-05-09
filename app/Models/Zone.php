<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'region_id',
        'name',
        'description',
    ];

    public function getImportIdAttribute(): string
    {
        return (string) ($this->external_id ?: $this->id);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function woredas()
    {
        return $this->hasMany(Woreda::class);
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }
}
