<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'type',
        'region_id',
        'zone_id',
        'zone',
        'woreda_id',
        'city_town',
        'phone',
        'fax',
    ];

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function zoneDefinition()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function woreda()
    {
        return $this->belongsTo(Woreda::class);
    }
}
