<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request)) {
            return $response;
        }

        $user = $request->user();
        if (! $user) {
            return $response;
        }

        try {
            UserActivityLog::query()->create([
                'user_id' => $user->id,
                'action' => $this->resolveAction($request),
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
        } catch (\Throwable) {
            // Keep request flow unaffected if logging fails.
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (! $request->is('admin*')) {
            return false;
        }

        if ($request->isMethod('OPTIONS')) {
            return false;
        }

        if ($request->routeIs('admin.dashboard.widgets.data')) {
            return false;
        }

        return true;
    }

    private function resolveAction(Request $request): string
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

    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'new_password_confirmation',
            'token',
        ];

        $clean = [];
        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $clean[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizePayload($value);
                continue;
            }

            $clean[$key] = is_scalar($value) || $value === null
                ? $value
                : (string) json_encode($value);
        }

        return $clean;
    }
}
