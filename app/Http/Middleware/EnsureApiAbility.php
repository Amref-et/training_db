<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $token = $user->currentAccessToken();
        if ($token === null) {
            return $next($request);
        }

        abort_unless($token->can($ability) || $token->can('*'), 403);

        return $next($request);
    }
}
