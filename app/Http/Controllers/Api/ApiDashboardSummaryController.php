<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiDashboardSummaryController extends Controller
{
    public function __construct(private DashboardMetricsService $metrics)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('dashboard.view'), 403);

        $filters = $this->metrics->resolveFilters($request->all());

        return response()->json([
            'filters' => $filters,
            'summary' => $this->metrics->summary($filters),
        ]);
    }
}
