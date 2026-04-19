<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'provider',
        'base_url',
        'api_version',
        'auth_type',
        'username',
        'password',
        'bearer_token',
        'client_id',
        'client_secret',
        'token_url',
        'event_endpoint',
        'program_id',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_error',
        'settings',
        'mappings',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'bearer_token' => 'encrypted',
        'client_secret' => 'encrypted',
        'settings' => 'array',
        'mappings' => 'array',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ApiSyncLog::class)->latest('synced_at')->latest('id');
    }

    public static function dhis2(): self
    {
        return static::query()->firstOrCreate(
            ['code' => 'dhis2'],
            [
                'name' => 'DHIS2',
                'provider' => 'dhis2',
                'auth_type' => 'basic',
                'api_version' => '40',
                'event_endpoint' => '/api/events',
                'is_active' => false,
                'settings' => [
                    'default_org_unit' => null,
                    'org_unit_strategy' => 'default',
                    'org_unit_map' => [],
                    'default_headers' => [],
                ],
                'mappings' => [
                    'event_name' => null,
                    'training_title' => null,
                    'project_name' => null,
                    'organized_by' => null,
                    'participant_count' => null,
                    'avg_final_score' => null,
                    'status' => null,
                    'venue' => null,
                    'city' => null,
                    'workshop_count' => null,
                ],
            ]
        );
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }
}
