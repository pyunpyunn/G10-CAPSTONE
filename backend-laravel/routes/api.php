<?php

use App\Http\Controllers\Api\ArchiveController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisasterBroadcastController;
use App\Http\Controllers\Api\EvacuationCheckInController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HouseholdDirectoryController;
use App\Http\Controllers\Api\HouseholdMobileController;
use App\Http\Controllers\Api\HouseholdStatusController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\MappingController;
use App\Http\Controllers\Api\MobileNavigationController;
use App\Http\Controllers\Api\MobileDeviceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RescueDispatchController;
use App\Http\Controllers\Api\RescuerAccountController;
use App\Http\Controllers\Api\RescuerMobileController;
use App\Http\Controllers\Api\ResourceRequestController;
use App\Http\Controllers\Api\SituationReportController;
use App\Http\Controllers\Api\SmsInboundController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/external/reports/options', [\App\Http\Controllers\Api\ReportExchangeController::class, 'options'])->middleware([\App\Http\Middleware\ValidateExternalApiKey::class, 'throttle:30,1']);
    Route::post('/external/report-requests', [\App\Http\Controllers\Api\ReportExchangeController::class, 'store'])->middleware([\App\Http\Middleware\ValidateExternalApiKey::class, 'throttle:30,1']);
    Route::get('/external/report-requests/{id}', [\App\Http\Controllers\Api\ReportExchangeController::class, 'externalStatus'])->middleware(['signed', 'throttle:60,1'])->name('external.report-requests.status');
    Route::post('/external/reports/handshake', [\App\Http\Controllers\Api\ExternalReportController::class, 'handshake'])->middleware([\App\Http\Middleware\ValidateExternalApiKey::class, 'throttle:30,1']);
    Route::post('/external/reports/generate', [\App\Http\Controllers\Api\ExternalReportController::class, 'generate'])->middleware([\App\Http\Middleware\ValidateExternalApiKey::class, 'throttle:30,1']);
    Route::get('/external/reports/status/{token}', [\App\Http\Controllers\Api\ExternalReportController::class, 'status'])->middleware('signed')->name('external.reports.status');
    Route::get('/external/reports/download/{filename}', [\App\Http\Controllers\Api\ExternalReportController::class, 'download'])->middleware('signed')->name('external.reports.download');
    Route::get('/', function () {
        return response()->json([
            'message' => 'ResQperation API v1 is running.',
            'documentation' => 'Use /dashboard, /auth/login, /auth/me, or other documented endpoints under /api/v1.',
        ]);
    });

    Route::get('/health/live', [HealthController::class, 'live'])->name('api.health.live');
    Route::get('/health/ready', [HealthController::class, 'ready'])->name('api.health.ready');

    Route::post('/inquiries', [InquiryController::class, 'store'])
        ->middleware(['throttle:10,1', 'realtime.atomic']);

    Route::post('/external/resource-requests/handshake', [ResourceRequestController::class, 'externalHandshake'])
        ->middleware('throttle:60,1');
    Route::post('/external/resource-requests', [ResourceRequestController::class, 'externalStore'])
        ->middleware(['throttle:60,1', 'realtime.atomic']);
    Route::post('/sms/inbound', [SmsInboundController::class, 'handle'])
        ->middleware(['throttle:60,1', 'realtime.atomic']);

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/mobile/login', [MobileNavigationController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::get('/mobile/packs/{version}/download', [MobileNavigationController::class, 'downloadPack'])
        ->middleware(['signed', 'throttle:20,1'])
        ->name('mobile.packs.download');

    // Route reads use the authenticated rescuer's own assignment scope; trip writes remain device-bound.
    Route::get('/mobile/bootstrap', [MobileNavigationController::class, 'bootstrap'])
        ->middleware(['auth:sanctum', 'role:rescuer,tl,tm', 'throttle:120,1']);
    Route::middleware(['auth:sanctum', 'mobile.device', 'role:rescuer,tl,tm', 'throttle:120,1'])->prefix('mobile')->group(function (): void {
        Route::post('/sync', [MobileNavigationController::class, 'sync']);
        Route::get('/packs/{version}', [MobileNavigationController::class, 'packManifest']);
    });
    Route::post('/rescuer/assignments/{assignmentId}/route', [MobileNavigationController::class, 'assignedRoadRoute'])
        ->middleware(['auth:sanctum', 'role:rescuer,tl,tm', 'throttle:12,1']);
    Route::post('/rescuer/evacuation-centers/{centerId}/route', [MobileNavigationController::class, 'evacuationCenterRoadRoute'])
        ->middleware(['auth:sanctum', 'role:rescuer,tl,tm', 'throttle:12,1']);
    Route::post('/dispatches/{id}/route', [MobileNavigationController::class, 'freshRoute'])
        ->middleware(['auth:sanctum', 'mobile.device', 'role:rescuer,tl,tm', 'throttle:6,1']);
    Route::get('/auth/password-recovery/questions', [AuthController::class, 'recoveryQuestions'])
        ->middleware('throttle:10,1');
    Route::post('/auth/password-recovery/verify', [AuthController::class, 'verifyPasswordChange'])
        ->middleware('throttle:10,1,password-verify');
    Route::post('/auth/password-recovery/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1,password-change');
    Route::post('/auth/password-recovery/household/lookup', [AuthController::class, 'lookupHouseholdRecoveryContact'])
        ->middleware('throttle:10,1');
    Route::post('/auth/password-recovery/household/validate-contact', [AuthController::class, 'validateHouseholdRecoveryContact'])
        ->middleware('throttle:10,1');
    Route::post('/auth/password-recovery/household/send-code', [AuthController::class, 'sendHouseholdRecoveryCode'])
        ->middleware('throttle:3,1');
    Route::post('/auth/password-recovery/household/reset', [AuthController::class, 'resetHouseholdPassword'])
        ->middleware('throttle:5,1,password-change');

    Route::middleware(['auth:sanctum', 'household.owner', 'throttle:300,1', 'realtime.atomic'])->group(function () {
        Route::get('/realtime/health', \App\Http\Controllers\Api\RealtimeHealthController::class)->middleware('role:operations_admin');
        Route::patch('/profile/password', [ProfileController::class, 'changePassword'])->middleware('throttle:5,1,password-change');
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/security-questions', [AuthController::class, 'recoveryQuestions']);
        Route::put('/auth/security-questions', [AuthController::class, 'saveRecoveryQuestions']);

        Route::middleware('role:operations_admin')->group(function () {
            Route::get('/inquiries', [InquiryController::class, 'index']);
            Route::patch('/inquiries/{inquiryId}', [InquiryController::class, 'updateStatus']);
            Route::get('/archive/inquiry-logs', [ArchiveController::class, 'inquiryLogs']);
        });

        Route::middleware('web.access')->group(function () {
            Route::get('/global-search', [GlobalSearchController::class, 'index']);
            Route::get('/personnel-profiles/{responderId}', [\App\Http\Controllers\Api\PersonnelProfileController::class, 'show'])->whereNumber('responderId');
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
            Route::get('/dashboard/dispatch', [DashboardController::class, 'dispatch']);
            Route::get('/dashboard/weather', [DashboardController::class, 'weather']);
            Route::get('/dashboard/requests', [DashboardController::class, 'requests']);
            Route::get('/dashboard/activity', [DashboardController::class, 'activity']);
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::get('/notifications/push/config', [\App\Http\Controllers\Api\BrowserPushController::class, 'configuration']);
            Route::post('/notifications/push/subscription', [\App\Http\Controllers\Api\BrowserPushController::class, 'store'])->middleware('throttle:15,1');
            Route::delete('/notifications/push/subscription', [\App\Http\Controllers\Api\BrowserPushController::class, 'destroy']);
            Route::post('/notifications/mark-read', [NotificationController::class, 'markRead']);
            Route::post('/notifications/delete-selected', [NotificationController::class, 'deleteSelected']);
            Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll']);
            Route::get('/profile', [ProfileController::class, 'show']);
            Route::patch('/profile', [ProfileController::class, 'update']);
            Route::patch('/profile/barangay', [ProfileController::class, 'updateBarangay']);

            Route::post('/dashboard/active-event/close', [DashboardController::class, 'closeActiveEvent']);
            Route::get('/disaster-events', [DisasterBroadcastController::class, 'index']);
            Route::post('/disaster-events', [DisasterBroadcastController::class, 'storeEvent']);
            Route::get('/disaster-events/{eventId}', [DisasterBroadcastController::class, 'show']);
            Route::patch('/disaster-events/{eventId}', [DisasterBroadcastController::class, 'updateEvent']);
            Route::get('/disaster-events/{eventId}/broadcasts', [DisasterBroadcastController::class, 'broadcasts']);
            Route::post('/disaster-events/{eventId}/broadcasts', [DisasterBroadcastController::class, 'storeBroadcast']);
            Route::post('/status-types', [DisasterBroadcastController::class, 'storeStatusType']);
            Route::delete('/status-types/{statusKey}', [DisasterBroadcastController::class, 'destroyStatusType']);
            Route::post('/disaster-types', [DisasterBroadcastController::class, 'storeDisasterType']);
            Route::delete('/disaster-types/{typeId}', [DisasterBroadcastController::class, 'destroyDisasterType']);
            Route::get('/weather/official-advisories', [WeatherController::class, 'officialAdvisories']);
            Route::get('/weather', [WeatherController::class, 'workspace']);
            Route::post('/weather/refresh', [WeatherController::class, 'refreshWorkspace']);
            Route::get('/disaster-events/{eventId}/weather-logs', [WeatherController::class, 'index']);
            Route::post('/disaster-events/{eventId}/weather-logs/refresh', [WeatherController::class, 'refreshEvent']);
            Route::post('/map/hazard-layers', [\App\Http\Controllers\Api\HazardLayerController::class, 'store'])->middleware('web.access:geospatial_analytics,manage_hazards');
            Route::delete('/map/hazard-layers/{id}', [\App\Http\Controllers\Api\HazardLayerController::class, 'destroy'])->middleware('web.access:geospatial_analytics,manage_hazards');
            Route::get('/map/overview', [MappingController::class, 'overview']);
            Route::get('/map/workspace', [MappingController::class, 'workspace']);
            Route::get('/map/household-geotags', [MappingController::class, 'householdGeotags']);
            Route::get('/map/evacuation-sites/{siteId}/route', [MappingController::class, 'evacuationRoute']);
            Route::get('/map/evacuation-sites', [MappingController::class, 'evacuationSites']);
            Route::get('/map/dispatch-routes', [MappingController::class, 'dispatchRoutes']);
            Route::get('/map/households/{householdId}/route', [MappingController::class, 'householdRoute']);
            Route::get('/households/directory', [HouseholdDirectoryController::class, 'index']);
            Route::get('/households', [HouseholdStatusController::class, 'index']);
            Route::get('/households/{householdId}', [HouseholdStatusController::class, 'show']);
            Route::get('/households/{householdId}/status-logs', [HouseholdStatusController::class, 'statusLogs']);
            Route::post('/households/{householdId}/confirm', [HouseholdStatusController::class, 'confirmStatus']);
            Route::post('/evacuation/check-in', [EvacuationCheckInController::class, 'store']);
            Route::post('/evacuation/verify-qr', [EvacuationCheckInController::class, 'verifyQr']);
            Route::get('/field-reports', [RescuerMobileController::class, 'fieldReportsAdmin']);
            Route::get('/rescue-teams', [RescueDispatchController::class, 'teams']);
            Route::get('/resource-request-types', [ResourceRequestController::class, 'types']);
            Route::post('/resource-request-types', [ResourceRequestController::class, 'storeType']);
            Route::get('/resource-requests', [ResourceRequestController::class, 'index']);
            Route::post('/resource-requests', [ResourceRequestController::class, 'store']);
            Route::get('/resource-requests/{requestId}', [ResourceRequestController::class, 'show']);
            Route::patch('/resource-requests/{requestId}', [ResourceRequestController::class, 'update']);
            Route::post('/resource-requests/{requestId}/validate', [ResourceRequestController::class, 'validateResource']);
            Route::post('/resource-requests/{requestId}/forward', [ResourceRequestController::class, 'forward']);
            Route::post('/resource-requests/{requestId}/return', [ResourceRequestController::class, 'returnRequest']);
            Route::post('/resource-requests/{requestId}/complete', [ResourceRequestController::class, 'complete']);
            Route::get('/situation-reports', [SituationReportController::class, 'index']);
            Route::post('/situation-reports', [SituationReportController::class, 'store']);
            Route::post('/situation-reports/sync', [SituationReportController::class, 'sync']);
            Route::post('/situation-reports/{sitRepId}/submissions', [SituationReportController::class, 'submission']);
            Route::get('/situation-reports/{sitRepId}', [SituationReportController::class, 'show']);
            Route::get('/situation-reports/{sitRepId}/pdf', [SituationReportController::class, 'pdf']);
            Route::get('/disaster-events/{eventId}/situation-summary', [SituationReportController::class, 'eventSummary']);
            Route::get('/archive/disaster-events', [ArchiveController::class, 'disasterEvents']);
            Route::get('/archive/household-status-logs', [ArchiveController::class, 'householdStatusLogs']);
            Route::get('/archive/dispatch-logs', [ArchiveController::class, 'dispatchLogs']);
            Route::get('/archive/radio-communication-logs', [ArchiveController::class, 'radioCommunicationLogs']);
            Route::get('/archive/resource-requests', [ArchiveController::class, 'resourceRequests']);
            Route::get('/archive/situation-reports', [ArchiveController::class, 'situationReports']);
            Route::post('/reports/generate', [\App\Http\Controllers\Api\ExternalReportController::class, 'generate'])->middleware('throttle:30,1');
            Route::get('/reports/external/providers', [\App\Http\Controllers\Api\EvaTrackReportController::class, 'providers']);
            Route::get('/reports/situation/sitios', [SituationReportController::class, 'sitios']);
            Route::get('/reports/external/requests', [\App\Http\Controllers\Api\ReportExchangeController::class, 'index']);
            Route::post('/reports/external/requests/{id}/decision', [\App\Http\Controllers\Api\ReportExchangeController::class, 'decide'])->middleware('throttle:10,1');
            Route::post('/reports/external/requests/{id}/file', [\App\Http\Controllers\Api\ReportExchangeController::class, 'refreshFile'])->middleware('throttle:10,1');
            Route::get('/reports/evatrack/configuration', [\App\Http\Controllers\Api\EvaTrackReportController::class, 'configuration']);
            Route::post('/reports/evatrack/generate', [\App\Http\Controllers\Api\EvaTrackReportController::class, 'generate'])->middleware('throttle:30,1');
            Route::post('/reports/safetrack/generate', [\App\Http\Controllers\Api\EvaTrackReportController::class, 'generateSafeTrack'])->middleware('throttle:10,1');
            Route::post('/reports/trackingaid/generate', [\App\Http\Controllers\Api\EvaTrackReportController::class, 'generateTrackingAid'])->middleware('throttle:30,1');
            Route::get('/archive/export', [ArchiveController::class, 'export']);
            Route::get('/archive/saved-groups', [ArchiveController::class, 'savedGroups']);
            Route::post('/archive/saved-groups', [ArchiveController::class, 'storeSavedGroup']);
            Route::delete('/archive/saved-groups/{groupId}/records/{recordId}', [ArchiveController::class, 'deleteSavedGroupRecord']);
            Route::delete('/archive/saved-groups/{groupId}', [ArchiveController::class, 'deleteSavedGroup']);
            Route::post('/archive/delete-selected', [ArchiveController::class, 'deleteSelected']);
            Route::get('/dispatches', [RescueDispatchController::class, 'index']);
            Route::get('/dispatches/communications', [RescueDispatchController::class, 'communications']);
            Route::get('/dispatches/welfare-checks', [RescueDispatchController::class, 'welfareChecks']);
            Route::get('/dispatches/member-check-queue', [RescueDispatchController::class, 'memberCheckQueue']);
            Route::get('/dispatches/priorities', [RescueDispatchController::class, 'priorities']);
            Route::get('/dispatches/purok-priorities', [RescueDispatchController::class, 'purokPriorities']);
            Route::get('/dispatches/sitio-priorities', [RescueDispatchController::class, 'sitioPriorities']);
            Route::get('/dispatches/criteria-timeline', [RescueDispatchController::class, 'criteriaTimeline']);
            Route::get('/dispatches/priority-settings', [RescueDispatchController::class, 'prioritySettings']);
            Route::patch('/dispatches/priority-settings', [RescueDispatchController::class, 'updatePrioritySettings']);
            Route::post('/dispatches', [RescueDispatchController::class, 'store']);
            Route::get('/dispatches/{assignmentId}', [RescueDispatchController::class, 'show'])->whereNumber('assignmentId');
            Route::post('/dispatches/{assignmentId}/complete', [RescueDispatchController::class, 'complete']);
        });

        Route::middleware('personnel.access')->group(function () {
            Route::get('/rescuers/roles', [\App\Http\Controllers\Api\PersonnelRoleController::class, 'index']);
            Route::post('/rescuers/roles', [\App\Http\Controllers\Api\PersonnelRoleController::class, 'store']);
            Route::patch('/rescuers/roles/{roleId}', [\App\Http\Controllers\Api\PersonnelRoleController::class, 'update']);
            Route::delete('/rescuers/roles/{roleId}', [\App\Http\Controllers\Api\PersonnelRoleController::class, 'destroy']);
            Route::get('/rescuers', [RescuerAccountController::class, 'index']);
            Route::get('/headquarters-accounts', [RescuerAccountController::class, 'headquartersAccounts']);
            Route::post('/headquarters-accounts', [RescuerAccountController::class, 'storeHeadquartersAccount']);
            Route::get('/rescuers/team-config', [RescuerAccountController::class, 'teamConfig']);
            Route::post('/rescuers/team-config', [RescuerAccountController::class, 'storeTeam']);
            Route::patch('/rescuers/team-config/{teamId}', [RescuerAccountController::class, 'updateTeam']);
            Route::delete('/rescuers/team-config/{teamId}', [RescuerAccountController::class, 'deleteTeam']);
            Route::post('/rescuers', [RescuerAccountController::class, 'store']);
            Route::get('/rescuers/{responderId}', [RescuerAccountController::class, 'show']);
            Route::patch('/rescuers/{responderId}', [RescuerAccountController::class, 'update']);
            Route::delete('/rescuers/{responderId}', [RescuerAccountController::class, 'destroy']);
            Route::post('/rescuers/{responderId}/deactivate', [RescuerAccountController::class, 'deactivate']);
        });

        Route::middleware('role:household_resident,rescuer')->group(function () {
            Route::post('/mobile/device-token', [MobileDeviceController::class, 'storePushToken']);
            Route::post('/households/{householdId}/status-logs', [HouseholdStatusController::class, 'storeStatusLog']);
        });

        Route::middleware('role:household_resident')->group(function () {
            Route::get('/household/device-owner/options', [HouseholdMobileController::class, 'ownerOptions']);
            Route::post('/household/device-owner/select', [HouseholdMobileController::class, 'selectOwner']);
            Route::post('/household/device-owner/reset', [HouseholdMobileController::class, 'resetOwner']);
            Route::get('/household/overview', [HouseholdMobileController::class, 'overview']);
            Route::post('/household/setup', [HouseholdMobileController::class, 'completeSetup']);
            Route::post('/household/setup/location', [HouseholdMobileController::class, 'previewSetupLocation']);
            Route::put('/household/geotag', [HouseholdMobileController::class, 'updateGeotag']);
            Route::post('/household/device-location', [HouseholdMobileController::class, 'updateDeviceLocation']);
            Route::patch('/household/members/{memberId}', [HouseholdMobileController::class, 'updateMember']);
            Route::post('/household/members/{memberId}/status', [HouseholdMobileController::class, 'storeMemberStatus']);
            Route::post('/household/trusted-households/{connectionId}/members/{memberId}/status', [HouseholdMobileController::class, 'storeTrustedMemberStatus']);
            Route::post('/household/status', [HouseholdMobileController::class, 'storeStatus']);
            Route::get('/household/status-history', [HouseholdMobileController::class, 'statusHistory']);
            Route::get('/household/qr', [HouseholdMobileController::class, 'qr']);
            Route::get('/household/trusted-households', [HouseholdMobileController::class, 'trustedHouseholds']);
            Route::put('/household/trusted-pin', [HouseholdMobileController::class, 'saveTrustedPin']);
            Route::post('/household/trusted-pin/verify', [HouseholdMobileController::class, 'verifyTrustedPin']);
            Route::get('/household/trusted-households/lookup/{householdId}', [HouseholdMobileController::class, 'lookupTrustedHousehold']);
            Route::post('/household/trusted-households', [HouseholdMobileController::class, 'storeTrustedHousehold']);
            Route::delete('/household/trusted-households/{connectionId}', [HouseholdMobileController::class, 'deleteTrustedHousehold']);
            Route::patch('/household/trusted-households/{connectionId}', [HouseholdMobileController::class, 'respondToTrustedHousehold']);
        });

        Route::middleware('role:operations_admin,chair,mitigation_prevention,preparedness,response,recovery_rehabilitation,rescuer')->group(function () {
            Route::patch('/dispatches/{assignmentId}', [RescueDispatchController::class, 'update']);
            Route::post('/evacuation/check-in', [EvacuationCheckInController::class, 'store']);
            Route::post('/evacuation/verify-qr', [EvacuationCheckInController::class, 'verifyQr']);
        });

        Route::middleware('role:rescuer')->group(function () {
            Route::patch('/dispatches/{assignmentId}/location', [RescueDispatchController::class, 'updateLocation']);

            Route::get('/rescuer/assignments/history', [\App\Http\Controllers\Api\RescuerDispatchHistoryController::class, 'index']);
            Route::get('/rescuer/profile', [RescuerMobileController::class, 'profile']);
            Route::patch('/rescuer/profile', [RescuerMobileController::class, 'updateProfile']);
            Route::get('/rescuer/overview', [RescuerMobileController::class, 'overview']);
            Route::get('/rescuer/assignments', [RescuerMobileController::class, 'assignments']);
            Route::get('/rescuer/assignments/{assignmentId}', [RescuerMobileController::class, 'assignment']);
            Route::get('/rescuer/assignments/{assignmentId}/household', [RescuerMobileController::class, 'assignmentHousehold']);
            Route::patch('/rescuer/assignments/{assignmentId}/status', [RescuerMobileController::class, 'updateAssignmentStatus']);
            Route::post('/rescuer/assignments/{assignmentId}/location', [RescuerMobileController::class, 'storeLocation']);
            Route::get('/rescuer/field-reports', [RescuerMobileController::class, 'fieldReports']);
            Route::get('/rescuer/check-ins', [RescuerMobileController::class, 'checkIns']);
            Route::post('/rescuer/check-ins', [RescuerMobileController::class, 'storeCheckIn']);
            Route::get('/rescuer/resource-requests', [RescuerMobileController::class, 'resourceRequests']);
            Route::patch('/rescuer/resource-requests/{requestId}/cancel', [RescuerMobileController::class, 'cancelResourceRequest']);
            Route::get('/rescuer/radio', [RescuerMobileController::class, 'radioFeed']);
            Route::post('/rescuer/radio/start', [RescuerMobileController::class, 'startRadioTransmission']);
            Route::post('/rescuer/radio/heartbeat', [RescuerMobileController::class, 'heartbeatRadioTransmission']);
            Route::post('/rescuer/radio/stop', [RescuerMobileController::class, 'stopRadioTransmission']);
            Route::post('/rescuer/radio/clip', [RescuerMobileController::class, 'storeRadioClip']);
            Route::post('/rescuer/radio/signal', [RescuerMobileController::class, 'storeRadioSignal']);
        });
        Route::post('/rescuer/field-reports', [RescuerMobileController::class, 'storeFieldReport'])->middleware('role:rescuer,tl,tm');
        Route::post('/rescuer/resource-requests', [RescuerMobileController::class, 'storeResourceRequest'])->middleware('role:tl');
    });
});
