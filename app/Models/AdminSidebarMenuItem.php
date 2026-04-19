<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class AdminSidebarMenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'icon',
        'route_name',
        'url',
        'target',
        'required_permission',
        'section_id',
        'section_title',
        'section_sort_order',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'section_id' => 'integer',
        'section_sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AdminSidebarMenuSection::class, 'section_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function resolvedUrl(): string
    {
        $routeName = trim((string) $this->route_name);
        if ($routeName !== '' && Route::has($routeName)) {
            return route($routeName);
        }

        $url = trim((string) $this->url);
        if ($url !== '') {
            return $url;
        }

        return '#';
    }

    public function iconClass(): ?string
    {
        $icon = strtolower(trim((string) $this->icon));
        if ($icon === '') {
            return null;
        }

        if (preg_match('/\bbi-([a-z0-9-]+)\b/', $icon, $matches) === 1) {
            return 'bi-'.$matches[1];
        }

        if (str_starts_with($icon, 'bi-')) {
            return $icon;
        }

        if (str_starts_with($icon, 'bi:')) {
            return 'bi-'.substr($icon, 3);
        }

        $map = [
            'chart-line' => 'bi-bar-chart-line',
            'file-text' => 'bi-file-earmark-text',
            'palette' => 'bi-palette',
            'users' => 'bi-people',
            'shield' => 'bi-shield-lock',
            'menu' => 'bi-list',
            'hammer' => 'bi-tools',
            'map' => 'bi-geo',
            'map-pin' => 'bi-geo-alt',
            'building' => 'bi-building',
            'users-cog' => 'bi-person-gear',
            'book' => 'bi-book',
            'target' => 'bi-bullseye',
            'calendar' => 'bi-calendar-event',
            'user-check' => 'bi-person-check',
            'clipboard' => 'bi-clipboard-data',
            'layers' => 'bi-collection',
            'workflow' => 'bi-diagram-3',
            'cloud-arrow-up' => 'bi-cloud-arrow-up',
        ];

        if (isset($map[$icon])) {
            return $map[$icon];
        }

        if (preg_match('/^[a-z0-9-]+$/', $icon) === 1) {
            return 'bi-'.$icon;
        }

        return null;
    }

    public function isVisibleTo(?User $user): bool
    {
        $permission = trim((string) $this->required_permission);

        if ($permission === '') {
            return true;
        }

        if (! $user || ! method_exists($user, 'hasPermission')) {
            return false;
        }

        return (bool) $user->hasPermission($permission);
    }

    public function isActiveForRequest(Request $request): bool
    {
        $routeName = trim((string) $this->route_name);
        if ($routeName !== '') {
            return $request->routeIs($routeName) || $request->routeIs($routeName.'.*');
        }

        $rawUrl = trim((string) $this->url);
        if ($rawUrl === '' || $rawUrl === '#' || str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
            return false;
        }

        $path = parse_url($rawUrl, PHP_URL_PATH) ?: $rawUrl;
        $normalized = '/'.trim((string) $path, '/');
        $current = '/'.trim($request->path(), '/');

        if ($normalized === '/') {
            return $current === '/';
        }

        return $current === $normalized || str_starts_with($current, $normalized.'/');
    }

    public static function navigationTreeFor(?User $user): Collection
    {
        $roots = self::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('title')])
            ->orderBy('section_sort_order')
            ->orderBy('section_title')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return $roots
            ->map(function (self $item) use ($user) {
                $visibleChildren = $item->children
                    ->filter(fn (self $child) => $child->isVisibleTo($user))
                    ->values();

                $item->setRelation('children', $visibleChildren);

                return $item;
            })
            ->filter(fn (self $item) => $item->isVisibleTo($user) || $item->children->isNotEmpty())
            ->values();
    }
}
