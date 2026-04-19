<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $token = $user->currentAccessToken();
        if ($token === null) {
            return $next($request);
        }

        $requiredAbility = $this->requiredAbility($request, $scope);

        abort_unless($token->can('*') || $token->can($requiredAbility), 403);

        return $next($request);
    }

    private function requiredAbility(Request $request, string $scope): string
    {
        return match (strtoupper((string) $request->method())) {
            'GET', 'HEAD', 'OPTIONS' => $scope.':read',
            default => $scope.':write',
        };
    }
}
