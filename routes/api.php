<?php

use App\Http\Controllers\Api\ApiDashboardSummaryController;
use App\Http\Controllers\Api\Dhis2ExportController;
use App\Http\Controllers\Api\Mobile\AppearanceController as MobileAppearanceController;
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\EnrollmentController as MobileEnrollmentController;
use App\Http\Controllers\Api\Mobile\ParticipantRegistrationController as MobileParticipantRegistrationController;
use App\Http\Controllers\Api\Mobile\TrainingWorkflowController as MobileTrainingWorkflowController;
use App\Http\Controllers\Api\Mobile\TrainingEventJoinRequestController as MobileTrainingEventJoinRequestController;
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

Route::prefix('mobile')->as('api.mobile.')->group(function (): void {
    Route::get('appearance', [MobileAppearanceController::class, 'show'])->name('appearance.show');
    Route::post('login', [MobileAuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [MobileAuthController::class, 'me'])->name('me');
        Route::post('logout', [MobileAuthController::class, 'logout'])->name('logout');
        Route::post('training-events/{trainingEvent}/enrollments', [MobileEnrollmentController::class, 'store'])
            ->middleware('permission:training_event_participants.create')
            ->name('training-events.enrollments.store');
        Route::get('training-workflow/events', [MobileTrainingWorkflowController::class, 'index'])
            ->middleware('permission:training_events.view')
            ->name('training-workflow.events.index');
        Route::get('training-workflow/events/{trainingEvent}', [MobileTrainingWorkflowController::class, 'show'])
            ->middleware('permission:training_events.view')
            ->name('training-workflow.events.show');
        Route::post('training-workflow/events/{trainingEvent}/join-requests/{joinRequest}/approve', [MobileTrainingWorkflowController::class, 'approveJoinRequest'])
            ->middleware('permission:training_event_participants.create')
            ->name('training-workflow.join-requests.approve');
        Route::post('training-workflow/events/{trainingEvent}/join-requests/{joinRequest}/reject', [MobileTrainingWorkflowController::class, 'rejectJoinRequest'])
            ->middleware('permission:training_event_participants.update')
            ->name('training-workflow.join-requests.reject');
        Route::post('training-workflow/events/{trainingEvent}/workshop-count', [MobileTrainingWorkflowController::class, 'storeWorkshopCount'])
            ->middleware('permission:training_events.update')
            ->name('training-workflow.workshop-count.store');
        Route::post('training-workflow/events/{trainingEvent}/workshops', [MobileTrainingWorkflowController::class, 'saveWorkshopScores'])
            ->middleware('permission:training_event_workshop_scores.update')
            ->name('training-workflow.workshops.save');
        Route::post('training-workflow/events/{trainingEvent}/closeout', [MobileTrainingWorkflowController::class, 'updateCloseout'])
            ->middleware('permission:training_events.update')
            ->name('training-workflow.closeout.update');
    });

    Route::get('participant-registration/options', [MobileParticipantRegistrationController::class, 'options'])
        ->name('participant-registration.options');
    Route::get('participant-registration/organization-options', [MobileParticipantRegistrationController::class, 'organizationOptions'])
        ->name('participant-registration.organization-options');
    Route::post('participant-registration', [MobileParticipantRegistrationController::class, 'store'])
        ->name('participant-registration.store');

    Route::get('training-event-join-request/options', [MobileTrainingEventJoinRequestController::class, 'options'])
        ->name('training-event-join-request.options');
    Route::get('training-event-join-request/participant-options', [MobileTrainingEventJoinRequestController::class, 'participantOptions'])
        ->name('training-event-join-request.participant-options');
    Route::post('training-event-join-request', [MobileTrainingEventJoinRequestController::class, 'store'])
        ->name('training-event-join-request.store');
});

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
