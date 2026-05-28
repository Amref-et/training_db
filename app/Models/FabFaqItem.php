<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FabFaqItem extends Model
{
    use HasFactory;

    public const TYPE_CATEGORY = 'category';
    public const TYPE_QUESTION = 'question';
    public const TYPES = [self::TYPE_CATEGORY, self::TYPE_QUESTION];
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_ADMIN = 'admin';
    public const VISIBILITY_BOTH = 'both';
    public const VISIBILITIES = [self::VISIBILITY_PUBLIC, self::VISIBILITY_ADMIN, self::VISIBILITY_BOTH];

    protected $fillable = [
        'parent_id',
        'type',
        'visibility',
        'title',
        'answer',
        'link_label',
        'link_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleFor(Builder $query, ?string $visibility): Builder
    {
        if (! in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_ADMIN], true)) {
            return $query;
        }

        return $query->whereIn('visibility', [$visibility, self::VISIBILITY_BOTH]);
    }

    public static function tree(bool $activeOnly = false, ?string $visibility = null): Collection
    {
        if (! Schema::hasTable('fab_faq_items')) {
            return collect();
        }

        $query = self::query()
            ->whereNull('parent_id')
            ->ordered()
            ->with(['children' => fn ($children) => self::treeRelation($children, $activeOnly, $visibility)]);

        if ($activeOnly) {
            $query->active();
        }

        $query->visibleFor($visibility);

        $items = $query->get();

        if (in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_ADMIN], true)) {
            return self::withoutEmptyCategories($items);
        }

        return $items;
    }

    public static function flattened(bool $activeOnly = false, ?string $visibility = null): Collection
    {
        $flat = collect();

        $walk = function (Collection $items, int $depth = 0) use (&$walk, $flat): void {
            foreach ($items as $item) {
                $item->setAttribute('depth', $depth);
                $flat->push($item);
                $walk($item->children, $depth + 1);
            }
        };

        $walk(self::tree($activeOnly, $visibility));

        return $flat;
    }

    public function descendantIds(): array
    {
        $this->loadMissing('children');

        return $this->children
            ->flatMap(fn (self $child) => array_merge([$child->id], $child->descendantIds()))
            ->all();
    }

    public function toChatbotNode(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'answer' => $this->type === self::TYPE_QUESTION ? (string) $this->answer : null,
            'link_label' => $this->link_label,
            'link_url' => $this->link_url,
            'children' => $this->children->map(fn (self $child) => $child->toChatbotNode())->values()->all(),
        ];
    }

    private static function treeRelation($query, bool $activeOnly, ?string $visibility = null)
    {
        $query
            ->ordered()
            ->with(['children' => fn ($children) => self::treeRelation($children, $activeOnly, $visibility)]);

        if ($activeOnly) {
            $query->active();
        }

        $query->visibleFor($visibility);

        return $query;
    }

    private static function withoutEmptyCategories(Collection $items): Collection
    {
        return $items
            ->map(function (self $item) {
                $item->setRelation('children', self::withoutEmptyCategories($item->children));

                return $item;
            })
            ->filter(fn (self $item) => $item->type === self::TYPE_QUESTION || $item->children->isNotEmpty())
            ->values();
    }
}
