<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class WebsiteMenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'url',
        'page_id',
        'parent_id',
        'sort_order',
        'target',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class, 'page_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function resolvedUrl(): string
    {
        if (! empty($this->url)) {
            return $this->url;
        }

        if ($this->page) {
            return $this->page->is_homepage
                ? route('home')
                : route('pages.show', $this->page->slug);
        }

        return '#';
    }

    public static function tree(): Collection
    {
        return self::query()
            ->active()
            ->whereNull('parent_id')
            ->with([
                'page',
                'children' => fn ($query) => $query->active()->with('page')->orderBy('sort_order')->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }
}
