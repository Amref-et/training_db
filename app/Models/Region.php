<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function zones()
    {
        return $this->hasMany(Zone::class);
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
