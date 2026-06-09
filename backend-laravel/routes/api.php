<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArchiveController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisasterBroadcastController;
use App\Http\Controllers\Api\HouseholdStatusController;
use App\Http\Controllers\Api\HouseholdMobileController;
use App\Http\Controllers\Api\MappingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResourceRequestController;
use App\Http\Controllers\Api\RescuerAccountController;
use App\Http\Controllers\Api\RescuerMobileController;
use App\Http\Controllers\Api\RescueDispatchController;
use App\Http\Controllers\Api\SituationReportController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/mark-read', [NotificationController::class, 'markRead']);
            Route::post('/notifications/delete-selected', [NotificationController::class, 'deleteSelected']);
            Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll']);
            Route::get('/profile', [ProfileController::class, 'show']);
            Route::patch('/profile', [ProfileController::class, 'update']);
            Route::patch('/profile/barangay', [ProfileController::class, 'updateBarangay']);
            Route::patch('/profile/password', [ProfileController::class, 'changePassword']);
            Route::post('/dashboard/active-event/close', [DashboardController::class, 'closeActiveEvent']);
            Route::get('/disaster-events', [DisasterBroadcastController::class, 'index']);
            Route::post('/disaster-events', [DisasterBroadcastController::class, 'storeEvent']);
            Route::get('/disaster-events/{eventId}/broadcasts', [DisasterBroadcastController::class, 'broadcasts']);
            Route::post('/disaster-events/{eventId}/broadcasts', [DisasterBroadcastController::class, 'storeBroadcast']);
            Route::get('/weather', [WeatherController::class, 'workspace']);
            Route::post('/weather/refresh', [WeatherController::class, 'refreshWorkspace']);
            Route::get('/disaster-events/{eventId}/weather-logs', [WeatherController::class, 'index']);
            Route::post('/disaster-events/{eventId}/weather-logs/refresh', [WeatherController::class, 'refreshEvent']);
            Route::get('/map/overview', [MappingController::class, 'overview']);
            Route::get('/map/household-geotags', [MappingController::class, 'householdGeotags']);
            Route::get('/map/evacuation-sites', [MappingController::class, 'evacuationSites']);
            Route::get('/map/dispatch-routes', [MappingController::class, 'dispatchRoutes']);
            Route::get('/households', [HouseholdStatusController::class, 'index']);
            Route::get('/households/{householdId}', [HouseholdStatusController::class, 'show']);
            Route::get('/households/{householdId}/status-logs', [HouseholdStatusController::class, 'statusLogs']);
            Route::get('/rescue-teams', [RescueDispatchController::class, 'teams']);
            Route::get('/rescuers', [RescuerAccountController::class, 'index']);
            Route::post('/rescuers', [RescuerAccountController::class, 'store']);
            Route::get('/rescuers/{responderId}', [RescuerAccountController::class, 'show']);
            Route::patch('/rescuers/{responderId}', [RescuerAccountController::class, 'update']);
            Route::post('/rescuers/{responderId}/deactivate', [RescuerAccountController::class, 'deactivate']);
            Route::get('/resource-requests', [ResourceRequestController::class, 'index']);
            Route::post('/resource-requests', [ResourceRequestController::class, 'store']);
            Route::get('/resource-requests/{requestId}', [ResourceRequestController::class, 'show']);
            Route::post('/resource-requests/{requestId}/validate', [ResourceRequestController::class, 'validateResource']);
            Route::post('/resource-requests/{requestId}/forward', [ResourceRequestController::class, 'forward']);
            Route::post('/resource-requests/{requestId}/return', [ResourceRequestController::class, 'returnRequest']);
            Route::get('/situation-reports', [SituationReportController::class, 'index']);
            Route::post('/situation-reports', [SituationReportController::class, 'store']);
            Route::get('/situation-reports/{sitRepId}', [SituationReportController::class, 'show']);
            Route::get('/situation-reports/{sitRepId}/pdf', [SituationReportController::class, 'pdf']);
            Route::get('/disaster-events/{eventId}/situation-summary', [SituationReportController::class, 'eventSummary']);
            Route::get('/archive/disaster-events', [ArchiveController::class, 'disasterEvents']);
            Route::get('/archive/household-status-logs', [ArchiveController::class, 'householdStatusLogs']);
            Route::get('/archive/dispatch-logs', [ArchiveController::class, 'dispatchLogs']);
            Route::get('/archive/resource-requests', [ArchiveController::class, 'resourceRequests']);
            Route::get('/archive/situation-reports', [ArchiveController::class, 'situationReports']);
            Route::get('/archive/export', [ArchiveController::class, 'export']);
            Route::get('/dispatches', [RescueDispatchController::class, 'index']);
            Route::post('/dispatches', [RescueDispatchController::class, 'store']);
            Route::get('/dispatches/{assignmentId}', [RescueDispatchController::class, 'show']);
            Route::post('/dispatches/{assignmentId}/complete', [RescueDispatchController::class, 'complete']);
        });

        Route::middleware('role:household_resident,rescuer')->group(function () {
            Route::post('/households/{householdId}/status-logs', [HouseholdStatusController::class, 'storeStatusLog']);
        });

        Route::middleware('role:household_resident')->group(function () {
            Route::get('/household/overview', [HouseholdMobileController::class, 'overview']);
            Route::post('/household/setup', [HouseholdMobileController::class, 'completeSetup']);
            Route::post('/household/device-location', [HouseholdMobileController::class, 'updateDeviceLocation']);
            Route::patch('/household/members/{memberId}', [HouseholdMobileController::class, 'updateMember']);
            Route::post('/household/members/{memberId}/status', [HouseholdMobileController::class, 'storeMemberStatus']);
            Route::post('/household/status', [HouseholdMobileController::class, 'storeStatus']);
            Route::get('/household/status-history', [HouseholdMobileController::class, 'statusHistory']);
            Route::get('/household/qr', [HouseholdMobileController::class, 'qr']);
            Route::get('/household/trusted-households', [HouseholdMobileController::class, 'trustedHouseholds']);
            Route::get('/household/trusted-households/lookup/{householdId}', [HouseholdMobileController::class, 'lookupTrustedHousehold']);
            Route::post('/household/trusted-households', [HouseholdMobileController::class, 'storeTrustedHousehold']);
        });

        Route::middleware('role:super_admin,admin,rescuer')->group(function () {
            Route::patch('/dispatches/{assignmentId}', [RescueDispatchController::class, 'update']);
        });

        Route::middleware('role:rescuer')->group(function () {
            Route::patch('/dispatches/{assignmentId}/location', [RescueDispatchController::class, 'updateLocation']);

            Route::get('/rescuer/profile', [RescuerMobileController::class, 'profile']);
            Route::patch('/rescuer/profile', [RescuerMobileController::class, 'updateProfile']);
            Route::get('/rescuer/overview', [RescuerMobileController::class, 'overview']);
            Route::get('/rescuer/assignments', [RescuerMobileController::class, 'assignments']);
            Route::get('/rescuer/assignments/{assignmentId}', [RescuerMobileController::class, 'assignment']);
            Route::patch('/rescuer/assignments/{assignmentId}/status', [RescuerMobileController::class, 'updateAssignmentStatus']);
            Route::post('/rescuer/assignments/{assignmentId}/location', [RescuerMobileController::class, 'storeLocation']);
            Route::get('/rescuer/field-reports', [RescuerMobileController::class, 'fieldReports']);
            Route::post('/rescuer/field-reports', [RescuerMobileController::class, 'storeFieldReport']);
            Route::get('/rescuer/resource-requests', [RescuerMobileController::class, 'resourceRequests']);
            Route::post('/rescuer/resource-requests', [RescuerMobileController::class, 'storeResourceRequest']);
            Route::patch('/rescuer/resource-requests/{requestId}/cancel', [RescuerMobileController::class, 'cancelResourceRequest']);
        });
    });
});
