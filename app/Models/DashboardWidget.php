<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    public const CHART_TYPES = ['bar', 'line', 'pie', 'doughnut', 'radar', 'table', 'stat'];

    public const SIZE_PRESETS = ['small', 'medium', 'large', 'full', 'custom'];

    public const WIDTH_MODES = ['columns', 'pixels'];

    public const COLOR_SCHEMES = [
        'teal_amber',
        'blue_pink',
        'emerald_slate',
        'sunset',
        'ocean_mint',
        'royal_coral',
        'forest_gold',
        'mono_gray',
        'berry_lime',
        'earth_clay',
    ];

    protected $fillable = [
        'dashboard_tab_id',
        'title',
        'chart_type',
        'sql_query',
        'refresh_interval_seconds',
        'size_preset',
        'width_mode',
        'width_columns',
        'width_px',
        'height_px',
        'color_scheme',
        'background_color',
        'text_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(DashboardTab::class, 'dashboard_tab_id');
    }
}
