<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

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

        $this->auditLog->logRequest($request, $response);

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
}
