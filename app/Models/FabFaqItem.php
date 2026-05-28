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

    protected $fillable = [
        'parent_id',
        'type',
        'title',
        'answer',
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

    public static function tree(bool $activeOnly = false): Collection
    {
        if (! Schema::hasTable('fab_faq_items')) {
            return collect();
        }

        $query = self::query()
            ->whereNull('parent_id')
            ->ordered()
            ->with(['children' => fn ($children) => self::treeRelation($children, $activeOnly)]);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    public static function flattened(bool $activeOnly = false): Collection
    {
        $flat = collect();

        $walk = function (Collection $items, int $depth = 0) use (&$walk, $flat): void {
            foreach ($items as $item) {
                $item->setAttribute('depth', $depth);
                $flat->push($item);
                $walk($item->children, $depth + 1);
            }
        };

        $walk(self::tree($activeOnly));

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
            'children' => $this->children->map(fn (self $child) => $child->toChatbotNode())->values()->all(),
        ];
    }

    private static function treeRelation($query, bool $activeOnly)
    {
        $query
            ->ordered()
            ->with(['children' => fn ($children) => self::treeRelation($children, $activeOnly)]);

        if ($activeOnly) {
            $query->active();
        }

        return $query;
    }
}
