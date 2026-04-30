<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'body',
        'blocks',
        'sections',
        'status',
        'is_homepage',
        'show_page_heading',
        'meta_title',
    ];

    protected $casts = [
        'blocks' => 'array',
        'sections' => 'array',
        'is_homepage' => 'boolean',
        'show_page_heading' => 'boolean',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
