<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // Regions
    Route::apiResource('regions', \App\Http\Controllers\RegionController::class);
    
    // Woredas
    Route::apiResource('woredas', \App\Http\Controllers\WoredaController::class);
    Route::get('woredas/region/{regionId}', [\App\Http\Controllers\WoredaController::class, 'byRegion']);
    
    // Organizations
    Route::apiResource('organizations', \App\Http\Controllers\OrganizationController::class);
    
    // Participants
    Route::apiResource('participants', \App\Http\Controllers\ParticipantController::class);
    
    // Training Organizers
    Route::apiResource('training-organizers', \App\Http\Controllers\TrainingOrganizerController::class);
    
    // Trainings
    Route::apiResource('trainings', \App\Http\Controllers\TrainingController::class);
    
    // Projects
    Route::apiResource('projects', \App\Http\Controllers\ProjectController::class);
    
    // Training Events
    Route::apiResource('training-events', \App\Http\Controllers\TrainingEventController::class);

    // Training Rounds
    Route::apiResource('training-rounds', \App\Http\Controllers\TrainingRoundController::class);
    
    // Dashboard
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
