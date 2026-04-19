<?php

namespace App\Services;

use App\Models\UserActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class AuditLogService
{
    private const REDACTED = '[REDACTED]';

    private const DEFAULT_IGNORED_FIELDS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
    ];

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'token',
        'db_password',
        'mail_password',
        'api_key',
        'secret',
        'secret_key',
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    public function logRequest(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        $this->record([
            'log_type' => 'activity',
            'event_key' => 'http.request',
            'user_id' => $user->id,
            'action' => $this->resolveRequestAction($request),
            'method' => strtoupper((string) $request->method()),
            'path' => (string) $request->path(),
            'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'status_code' => $response->getStatusCode(),
            'metadata' => [
                'query' => $this->sanitizePayload($request->query()),
                'payload' => $this->sanitizePayload($request->except(['_token', '_method'])),
            ],
            'occurred_at' => now(),
        ]);
    }

    public function snapshotModel(Model $model, ?array $onlyFields = null): array
    {
        $attributes = $model->attributesToArray();

        foreach (self::DEFAULT_IGNORED_FIELDS as $field) {
            unset($attributes[$field]);
        }

        if ($onlyFields !== null) {
            $attributes = Arr::only($attributes, $onlyFields);
        }

        return $this->sanitizePayload($attributes);
    }

    public function logModelCreated(Model $model, ?string $summary = null, array $metadata = []): void
    {
        $this->recordForModel(
            'model.created',
            $summary ?: 'Created '.class_basename($model),
            $model,
            [],
            $this->snapshotModel($model),
            $metadata
        );
    }

    public function logModelUpdated(Model $model, array $beforeState, ?string $summary = null, array $metadata = []): void
    {
        $afterState = $this->snapshotModel($model);
        $changedFields = collect(array_unique(array_merge(array_keys($beforeState), array_keys($afterState))))
            ->filter(fn (string $field) => ($beforeState[$field] ?? null) !== ($afterState[$field] ?? null))
            ->values()
            ->all();

        if ($changedFields === []) {
            return;
        }

        $metadata['changed_fields'] = $changedFields;

        $this->recordForModel(
            'model.updated',
            $summary ?: 'Updated '.class_basename($model),
            $model,
            Arr::only($beforeState, $changedFields),
            Arr::only($afterState, $changedFields),
            $metadata
        );
    }

    public function logModelDeleted(string $modelClass, mixed $modelKey, ?string $label, array $beforeState = [], ?string $summary = null, array $metadata = []): void
    {
        $this->record([
            'log_type' => 'audit',
            'event_key' => 'model.deleted',
            'action' => $summary ?: 'Deleted '.class_basename($modelClass),
            'auditable_type' => $modelClass,
            'auditable_id' => is_numeric($modelKey) ? (int) $modelKey : null,
            'auditable_label' => $label,
            'old_values' => $this->sanitizePayload($beforeState),
            'new_values' => null,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function logCustom(string $action, string $eventKey, array $options = []): void
    {
        $this->record([
            'log_type' => $options['log_type'] ?? 'audit',
            'event_key' => $eventKey,
            'action' => $action,
            'auditable_type' => $options['auditable_type'] ?? null,
            'auditable_id' => isset($options['auditable_id']) && is_numeric($options['auditable_id']) ? (int) $options['auditable_id'] : null,
            'auditable_label' => $options['auditable_label'] ?? null,
            'old_values' => $options['old_values'] ?? null,
            'new_values' => $options['new_values'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'status_code' => $options['status_code'] ?? null,
            'occurred_at' => now(),
        ]);
    }

    public function logAuthEvent(string $eventKey, string $action, ?Authenticatable $user = null, array $metadata = []): void
    {
        $this->record([
            'log_type' => 'auth',
            'event_key' => $eventKey,
            'action' => $action,
            'user_id' => $user?->getAuthIdentifier(),
            'auditable_type' => $user ? get_class($user) : null,
            'auditable_id' => $user && is_numeric($user->getAuthIdentifier()) ? (int) $user->getAuthIdentifier() : null,
            'auditable_label' => $user instanceof Model ? $this->modelLabel($user) : null,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function sanitizePayload(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $clean = [];
            foreach ($payload as $key => $value) {
                $lowerKey = strtolower((string) $key);
                if ($this->isSensitiveKey($lowerKey)) {
                    $clean[$key] = self::REDACTED;
                    continue;
                }

                $clean[$key] = $this->sanitizePayload($value);
            }

            return $clean;
        }

        if ($payload instanceof \BackedEnum) {
            return $payload->value;
        }

        if ($payload instanceof \DateTimeInterface) {
            return $payload->format(\DateTimeInterface::ATOM);
        }

        if (is_object($payload)) {
            return method_exists($payload, '__toString')
                ? (string) $payload
                : json_encode($payload, JSON_UNESCAPED_SLASHES);
        }

        return is_scalar($payload) || $payload === null
            ? $payload
            : json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    private function recordForModel(string $eventKey, string $action, Model $model, array $oldValues = [], ?array $newValues = null, array $metadata = []): void
    {
        $this->record([
            'log_type' => 'audit',
            'event_key' => $eventKey,
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => is_numeric($model->getKey()) ? (int) $model->getKey() : null,
            'auditable_label' => $this->modelLabel($model),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function record(array $attributes): void
    {
        try {
            $request = $this->currentRequest();

            UserActivityLog::query()->create([
                'user_id' => $attributes['user_id'] ?? $request?->user()?->id,
                'log_type' => $attributes['log_type'] ?? 'activity',
                'event_key' => $attributes['event_key'] ?? null,
                'action' => $attributes['action'] ?? null,
                'method' => $attributes['method'] ?? ($request ? strtoupper((string) $request->method()) : null),
                'path' => $attributes['path'] ?? ($request?->path()),
                'route_name' => $attributes['route_name'] ?? ($request?->route()?->getName()),
                'ip_address' => $attributes['ip_address'] ?? ($request?->ip()),
                'user_agent' => $attributes['user_agent'] ?? ($request ? substr((string) $request->userAgent(), 0, 2000) : null),
                'status_code' => $attributes['status_code'] ?? null,
                'auditable_type' => $attributes['auditable_type'] ?? null,
                'auditable_id' => $attributes['auditable_id'] ?? null,
                'auditable_label' => $attributes['auditable_label'] ?? null,
                'old_values' => $this->sanitizePayload($attributes['old_values'] ?? null),
                'new_values' => $this->sanitizePayload($attributes['new_values'] ?? null),
                'metadata' => $this->sanitizePayload($attributes['metadata'] ?? []),
                'occurred_at' => $attributes['occurred_at'] ?? now(),
            ]);
        } catch (\Throwable) {
            // Audit logging should never block the primary request flow.
        }
    }

    private function resolveRequestAction(Request $request): string
    {
        $method = strtoupper((string) $request->method());
        $routeName = (string) ($request->route()?->getName() ?? '');
        $target = $routeName !== '' ? $routeName : (string) $request->path();

        return match ($method) {
            'GET' => 'Viewed '.$target,
            'POST' => 'Created/Submitted '.$target,
            'PUT', 'PATCH' => 'Updated '.$target,
            'DELETE' => 'Deleted '.$target,
            default => $method.' '.$target,
        };
    }

    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request ? $request : null;
    }

    private function modelLabel(Model $model): string
    {
        foreach (['project_name', 'event_name', 'title', 'name', 'email', 'slug', 'project_code', 'participant_code'] as $field) {
            $value = trim((string) data_get($model, $field, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($key === $sensitiveKey || str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
