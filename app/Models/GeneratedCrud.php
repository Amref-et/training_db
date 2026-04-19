<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GeneratedCrud extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'table_name',
        'singular_label',
        'plural_label',
        'model_class',
        'schema',
    ];

    protected $casts = [
        'schema' => 'array',
    ];

    protected function singularLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $value ?: Str::headline(Str::singular($attributes['name'] ?? 'Record')),
        );
    }

    protected function pluralLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $value ?: Str::headline($attributes['name'] ?? 'Records'),
        );
    }
}
