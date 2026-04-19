<?php

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\AdminSidebarMenuController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\ApiManagementController;
use App\Http\Controllers\CrudBuilderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvSettingsController;
use App\Http\Controllers\ManagedResourceController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TrainingEventsCalendarController;
use App\Http\Controllers\TrainingEventGroupedViewController;
use App\Http\Controllers\TrainingWorkflowController;
use App\Http\Controllers\UserActivityLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebsiteController;
use App\Http\Middleware\LogUserActivity;
use App\Support\ResourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebsiteController::class, 'home'])->name('home');
Route::get('/pages/{slug}', [WebsiteController::class, 'show'])->name('pages.show');
Route::get('/public/pages/{slug}', [WebsiteController::class, 'show']);
Route::get('/embed/training-events-calendar', [TrainingEventsCalendarController::class, 'embed'])->name('training-events-calendar.embed');
Route::get('/api/openapi.json', [OpenApiController::class, 'json'])->name('api.openapi');

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/admin')->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', LogUserActivity::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::post('dashboard/tabs', [DashboardController::class, 'storeTab'])->middleware('permission:dashboard.view')->name('dashboard.tabs.store');
    Route::put('dashboard/tabs/{tab}', [DashboardController::class, 'updateTab'])->middleware('permission:dashboard.view')->name('dashboard.tabs.update');
    Route::delete('dashboard/tabs/{tab}', [DashboardController::class, 'destroyTab'])->middleware('permission:dashboard.view')->name('dashboard.tabs.destroy');
    Route::post('dashboard/tabs/{tab}/widgets', [DashboardController::class, 'storeWidget'])->middleware('permission:dashboard.view')->name('dashboard.widgets.store');
    Route::put('dashboard/widgets/{widget}', [DashboardController::class, 'updateWidget'])->middleware('permission:dashboard.view')->name('dashboard.widgets.update');
    Route::delete('dashboard/widgets/{widget}', [DashboardController::class, 'destroyWidget'])->middleware('permission:dashboard.view')->name('dashboard.widgets.destroy');
    Route::post('dashboard/tabs/{tab}/widgets/reorder', [DashboardController::class, 'reorderWidgets'])->middleware('permission:dashboard.view')->name('dashboard.widgets.reorder');
    Route::get('dashboard/widgets/{widget}/data', [DashboardController::class, 'widgetData'])->middleware('permission:dashboard.view')->name('dashboard.widgets.data');
    Route::get('dashboard/layout/export', [DashboardController::class, 'exportLayout'])->middleware('permission:dashboard.view')->name('dashboard.layout.export');
    Route::post('dashboard/layout/import', [DashboardController::class, 'importLayout'])->middleware('permission:dashboard.view')->name('dashboard.layout.import');

    Route::get('pages', [PageController::class, 'index'])->middleware('permission:pages.view')->name('pages.index');
    Route::get('pages/create', [PageController::class, 'create'])->middleware('permission:pages.create')->name('pages.create');
    Route::post('pages', [PageController::class, 'store'])->middleware('permission:pages.create')->name('pages.store');
    Route::get('pages/{page}/edit', [PageController::class, 'edit'])->middleware('permission:pages.update')->name('pages.edit');
    Route::put('pages/{page}', [PageController::class, 'update'])->middleware('permission:pages.update')->name('pages.update');
    Route::delete('pages/{page}', [PageController::class, 'destroy'])->middleware('permission:pages.delete')->name('pages.destroy');

    Route::get('menus', [MenuController::class, 'index'])->middleware('permission:menus.view')->name('menus.index');
    Route::get('menus/create', [MenuController::class, 'create'])->middleware('permission:menus.create')->name('menus.create');
    Route::post('menus', [MenuController::class, 'store'])->middleware('permission:menus.create')->name('menus.store');
    Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->middleware('permission:menus.update')->name('menus.edit');
    Route::put('menus/{menu}', [MenuController::class, 'update'])->middleware('permission:menus.update')->name('menus.update');
    Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->middleware('permission:menus.delete')->name('menus.destroy');

    Route::get('sidebar-menus', [AdminSidebarMenuController::class, 'index'])->middleware('permission:menus.view')->name('sidebar-menus.index');
    Route::get('sidebar-menus/create', [AdminSidebarMenuController::class, 'create'])->middleware('permission:menus.create')->name('sidebar-menus.create');
    Route::post('sidebar-menus', [AdminSidebarMenuController::class, 'store'])->middleware('permission:menus.create')->name('sidebar-menus.store');
    Route::get('sidebar-menus/{sidebarMenu}/edit', [AdminSidebarMenuController::class, 'edit'])->middleware('permission:menus.update')->name('sidebar-menus.edit');
    Route::put('sidebar-menus/{sidebarMenu}', [AdminSidebarMenuController::class, 'update'])->middleware('permission:menus.update')->name('sidebar-menus.update');
    Route::delete('sidebar-menus/{sidebarMenu}', [AdminSidebarMenuController::class, 'destroy'])->middleware('permission:menus.delete')->name('sidebar-menus.destroy');
    Route::post('sidebar-menus/seed-suggested', [AdminSidebarMenuController::class, 'seedSuggested'])->middleware('permission:menus.create')->name('sidebar-menus.seed');
    Route::post('sidebar-menus/sections', [AdminSidebarMenuController::class, 'storeSection'])->middleware('permission:menus.create')->name('sidebar-menus.sections.store');
    Route::put('sidebar-menus/sections/{section}', [AdminSidebarMenuController::class, 'updateSection'])->middleware('permission:menus.update')->name('sidebar-menus.sections.update');
    Route::delete('sidebar-menus/sections/{section}', [AdminSidebarMenuController::class, 'destroySection'])->middleware('permission:menus.delete')->name('sidebar-menus.sections.destroy');

    Route::get('appearance', [AppearanceController::class, 'edit'])->middleware('permission:appearance.view')->name('appearance.edit');
    Route::get('appearance/custom-css', [AppearanceController::class, 'customCss'])->middleware('permission:appearance.view')->name('appearance.custom-css');
    Route::get('appearance/custom-js', [AppearanceController::class, 'customJs'])->middleware('permission:appearance.view')->name('appearance.custom-js');
    Route::put('appearance', [AppearanceController::class, 'update'])->middleware('permission:appearance.update')->name('appearance.update');
    Route::get('settings/env', [EnvSettingsController::class, 'edit'])->middleware('permission:appearance.view')->name('settings.env.edit');
    Route::put('settings/env', [EnvSettingsController::class, 'update'])->middleware('permission:appearance.update')->name('settings.env.update');
    Route::get('api-management', [ApiManagementController::class, 'index'])->middleware('permission:api_management.view')->name('api-management.index');
    Route::get('api-management/docs', [OpenApiController::class, 'docs'])->middleware('permission:api_management.view')->name('api-management.docs');
    Route::put('api-management/dhis2', [ApiManagementController::class, 'updateDhis2'])->middleware('permission:api_management.update')->name('api-management.dhis2.update');
    Route::post('api-management/dhis2/test', [ApiManagementController::class, 'testDhis2'])->middleware('permission:api_management.update')->name('api-management.dhis2.test');
    Route::get('api-management/dhis2/preview/{trainingEvent}', [ApiManagementController::class, 'previewTrainingEventPayload'])->middleware('permission:api_management.view')->name('api-management.dhis2.preview');
    Route::post('api-management/dhis2/sync', [ApiManagementController::class, 'syncTrainingEvent'])->middleware('permission:api_management.update')->name('api-management.dhis2.sync');
    Route::post('api-management/tokens', [ApiManagementController::class, 'createToken'])->middleware('permission:api_management.update')->name('api-management.tokens.store');
    Route::delete('api-management/tokens/{token}', [ApiManagementController::class, 'destroyToken'])->middleware('permission:api_management.update')->name('api-management.tokens.destroy');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    Route::get('user-activity-logs', [UserActivityLogController::class, 'index'])->middleware('permission:users.view')->name('user-activity-logs.index');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

    Route::get('crud-builders', [CrudBuilderController::class, 'index'])->middleware('permission:crud_builder.view')->name('crud-builders.index');
    Route::get('crud-builders/create', [CrudBuilderController::class, 'create'])->middleware('permission:crud_builder.create')->name('crud-builders.create');
    Route::post('crud-builders', [CrudBuilderController::class, 'store'])->middleware('permission:crud_builder.create')->name('crud-builders.store');
    Route::delete('crud-builders/{crud}', [CrudBuilderController::class, 'destroy'])->middleware('permission:crud_builder.view')->name('crud-builders.destroy');

    Route::get('training-events/grouped', [TrainingEventGroupedViewController::class, 'index'])
        ->middleware('permission:training_events.view')
        ->name('training-events.grouped');

    Route::get('training-events-calendar', [TrainingEventsCalendarController::class, 'index'])
        ->middleware('permission:training_events.view')
        ->name('training-events-calendar.index');

    Route::get('training-workflow', [TrainingWorkflowController::class, 'index'])
        ->middleware('permission:training_events.view')
        ->name('training-workflow.index');

    Route::post('training-workflow/events', [TrainingWorkflowController::class, 'storeEvent'])
        ->middleware('permission:training_events.create')
        ->name('training-workflow.events.store');

    Route::post('training-workflow/events/{trainingEvent}/enrollments', [TrainingWorkflowController::class, 'storeEnrollment'])
        ->middleware('permission:training_event_participants.create')
        ->name('training-workflow.enrollments.store');

    Route::delete('training-workflow/events/{trainingEvent}/enrollments/{enrollment}', [TrainingWorkflowController::class, 'destroyEnrollment'])
        ->middleware('permission:training_event_participants.delete')
        ->name('training-workflow.enrollments.destroy');

    Route::post('training-workflow/events/{trainingEvent}/workshops', [TrainingWorkflowController::class, 'saveWorkshopScores'])
        ->middleware('permission:training_event_workshop_scores.update')
        ->name('training-workflow.workshops.save');

    Route::get('training-workflow/events/{trainingEvent}/workshops/export', [TrainingWorkflowController::class, 'exportWorkshopScores'])
        ->middleware('permission:training_event_workshop_scores.view')
        ->name('training-workflow.workshops.export');

    Route::post('training-workflow/events/{trainingEvent}/workshops/import', [TrainingWorkflowController::class, 'importWorkshopScores'])
        ->middleware('permission:training_event_workshop_scores.update')
        ->name('training-workflow.workshops.import');

    Route::get('training-workflow/events/{trainingEvent}/report/export', [TrainingWorkflowController::class, 'exportReport'])
        ->middleware('permission:training_events.view')
        ->name('training-workflow.report.export');

    Route::get('organizations/export', [ManagedResourceController::class, 'exportOrganizations'])
        ->middleware('permission:organizations.view')
        ->name('organizations.export');

    Route::post('organizations/import', [ManagedResourceController::class, 'importOrganizations'])
        ->middleware('permission:organizations.create')
        ->name('organizations.import');

    Route::get('participants/export', [ManagedResourceController::class, 'exportParticipants'])
        ->middleware('permission:participants.view')
        ->name('participants.export');

    Route::post('participants/import', [ManagedResourceController::class, 'importParticipants'])
        ->middleware('permission:participants.create')
        ->name('participants.import');

    Route::get('participants/organization-options', [ManagedResourceController::class, 'participantOrganizationOptions'])
        ->name('participants.organization-options');

    Route::post('training-workflow/events/{trainingEvent}/workshop-count', [TrainingWorkflowController::class, 'storeWorkshopCount'])
        ->middleware('permission:training_events.update')
        ->name('training-workflow.workshop-count.store');

    foreach (ResourceRegistry::all() as $resource => $config) {
        Route::get($config['path'], function (Request $request) use ($resource) {
            return app(ManagedResourceController::class)->index($request, $resource);
        })
            ->middleware('permission:'.$config['permission'].'.view')
            ->name($config['path'].'.index');

        Route::get($config['path'].'/create', function () use ($resource) {
            return app(ManagedResourceController::class)->create($resource);
        })
            ->middleware('permission:'.$config['permission'].'.create')
            ->name($config['path'].'.create');

        Route::post($config['path'], function (Request $request) use ($resource) {
            return app(ManagedResourceController::class)->store($request, $resource);
        })
            ->middleware('permission:'.$config['permission'].'.create')
            ->name($config['path'].'.store');

        Route::get($config['path'].'/{record}/files/{field}', function (string $record, string $field) use ($resource) {
            return app(ManagedResourceController::class)->downloadFile($resource, $record, $field);
        })
            ->middleware('permission:'.$config['permission'].'.view')
            ->name($config['path'].'.file');

        Route::get($config['path'].'/{record}/edit', function (string $record) use ($resource) {
            return app(ManagedResourceController::class)->edit($resource, $record);
        })
            ->middleware('permission:'.$config['permission'].'.update')
            ->name($config['path'].'.edit');

        Route::put($config['path'].'/{record}', function (Request $request, string $record) use ($resource) {
            return app(ManagedResourceController::class)->update($request, $resource, $record);
        })
            ->middleware('permission:'.$config['permission'].'.update')
            ->name($config['path'].'.update');

        Route::delete($config['path'].'/{record}', function (string $record) use ($resource) {
            return app(ManagedResourceController::class)->destroy($resource, $record);
        })
            ->middleware('permission:'.$config['permission'].'.delete')
            ->name($config['path'].'.destroy');
    }
});

