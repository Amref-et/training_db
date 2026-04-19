<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_integration_id',
        'direction',
        'entity_type',
        'entity_id',
        'status',
        'endpoint',
        'request_payload',
        'response_payload',
        'error_message',
        'synced_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ApiIntegration::class, 'api_integration_id');
    }
}
