<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingEvent;
use App\Services\Dhis2IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Dhis2ExportController extends Controller
{
    public function __construct(private Dhis2IntegrationService $dhis2)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('training_events.view'), 403);

        $events = TrainingEvent::query()
            ->with(['training', 'trainingOrganizer', 'projectSubawardee', 'trainingRegion'])
            ->orderByDesc('start_date')
            ->limit(min(200, max(1, (int) $request->integer('limit', 50))))
            ->get();

        return response()->json([
            'integration' => $this->dhis2->integration()->only(['code', 'name', 'provider', 'base_url', 'program_id', 'event_endpoint']),
            'count' => $events->count(),
            'data' => $events->map(fn (TrainingEvent $event) => $this->dhis2->buildTrainingEventPayload($event))->values(),
        ]);
    }

    public function show(TrainingEvent $trainingEvent): JsonResponse
    {
        abort_unless(request()->user()?->hasPermission('training_events.view'), 403);

        return response()->json(
            $this->dhis2->buildTrainingEventPayload($trainingEvent),
            200,
            [],
            JSON_UNESCAPED_SLASHES
        );
    }
}
