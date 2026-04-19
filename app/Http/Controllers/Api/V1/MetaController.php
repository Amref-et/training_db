<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'name' => config('app.name', 'Amref Training Database'),
                'version' => 'v1',
                'auth' => [
                    'guard' => 'sanctum',
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->roleNames()->values()->all(),
                        'permissions' => $user->permissions()->pluck('slug')->values()->all(),
                        'token_abilities' => $user->currentAccessToken()?->abilities ?? ['session'],
                    ] : null,
                ],
                'endpoints' => [
                    'regions',
                    'zones',
                    'woredas',
                    'organizations',
                    'participants',
                    'training-organizers',
                    'trainings',
                    'projects',
                    'training-events',
                    'training-rounds',
                    'integrations/dhis2/training-events',
                ],
                'features' => [
                    'paginated_resource_endpoints' => true,
                    'search_query_parameter' => 'q',
                    'filter_query_parameters' => true,
                    'sanctum_token_authentication' => true,
                    'dhis2_export_endpoints' => true,
                ],
            ],
        ]);
    }
}
