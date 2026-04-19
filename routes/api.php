<?php

use App\Http\Controllers\Api\Dhis2ExportController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OrganizationsController;
use App\Http\Controllers\Api\V1\ParticipantsController;
use App\Http\Controllers\Api\V1\ProjectsController;
use App\Http\Controllers\Api\V1\RegionsController;
use App\Http\Controllers\Api\V1\TrainingEventsController;
use App\Http\Controllers\Api\V1\TrainingOrganizersController;
use App\Http\Controllers\Api\V1\TrainingRoundsController;
use App\Http\Controllers\Api\V1\TrainingsController;
use App\Http\Controllers\Api\V1\WoredasController;
use App\Http\Controllers\Api\V1\ZonesController;
use App\Http\Controllers\Api\ApiDashboardSummaryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$registerResourceRoutes = function (): void {
    Route::get('meta', [MetaController::class, 'index']);
    Route::apiResource('regions', RegionsController::class)->middleware('api_scope:reference-data');
    Route::apiResource('zones', ZonesController::class)->middleware('api_scope:reference-data');
    Route::apiResource('woredas', WoredasController::class)->middleware('api_scope:reference-data');
    Route::get('woredas/region/{regionId}', [WoredasController::class, 'byRegion'])->middleware('api_scope:reference-data');
    Route::apiResource('organizations', OrganizationsController::class)->middleware('api_scope:reference-data');
    Route::apiResource('participants', ParticipantsController::class)->middleware('api_scope:participants');
    Route::apiResource('training-organizers', TrainingOrganizersController::class)->middleware('api_scope:reference-data');
    Route::apiResource('trainings', TrainingsController::class)->middleware('api_scope:reference-data');
    Route::apiResource('projects', ProjectsController::class)->middleware('api_scope:reference-data');
    Route::apiResource('training-events', TrainingEventsController::class)->middleware('api_scope:training-events');
    Route::apiResource('training-rounds', TrainingRoundsController::class)->middleware('api_scope:reference-data');
    Route::get('dashboard', [ApiDashboardSummaryController::class, 'index'])->middleware('api_scope:reference-data');
};

Route::middleware(['auth:sanctum'])->group(function () use ($registerResourceRoutes) {
    $registerResourceRoutes();
});

Route::prefix('v1')->as('api.v1.')->middleware(['auth:sanctum'])->group(function () use ($registerResourceRoutes) {
    $registerResourceRoutes();

    Route::get('integrations/dhis2/training-events', [Dhis2ExportController::class, 'index'])
        ->middleware('api_ability:dhis2:read');

    Route::get('integrations/dhis2/training-events/{trainingEvent}', [Dhis2ExportController::class, 'show'])
        ->middleware('api_ability:dhis2:read');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
