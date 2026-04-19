<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

abstract class ApiController extends Controller
{
    protected function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        return max(1, min($max, (int) $request->integer('per_page', $default)));
    }

    protected function applySearch(Builder $query, Request $request, array $fields): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search === '' || $fields === []) {
            return;
        }

        $query->where(function (Builder $inner) use ($fields, $search) {
            foreach ($fields as $field) {
                $inner->orWhere($field, 'like', '%'.$search.'%');
            }
        });
    }

    protected function paginatedResponse(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        return response()->json([
            'data' => $resourceClass::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    protected function itemResponse(Model $model, string $resourceClass, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => new $resourceClass($model),
        ], $status);
    }

    protected function messageResponse(string $message, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $extra), $status);
    }

    protected function ensurePermission(Request $request, string $resource): void
    {
        $action = match (strtoupper((string) $request->method())) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'view',
        };

        $permission = $resource.'.'.$action;
        $user = $request->user();

        abort_unless($user && $user->hasPermission($permission), 403, 'Insufficient permission: '.$permission);
    }

    protected function normalizeBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    protected function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    protected function routeBaseName(string $resource): string
    {
        return 'api.v1.'.Str::kebab($resource);
    }
}
