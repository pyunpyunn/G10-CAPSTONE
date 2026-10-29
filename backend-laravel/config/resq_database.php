<?php

// Resqperation-owned operational tables verified against Klint and the API workflows.
// Shared master tables are intentionally not owned by this manifest.
return [
    'modules' => [
        'dispatch' => ['RescueTeam', 'Responder', 'ResponderAssignment', 'DispatchStatus', 'DispatchTrip', 'TripPoint', 'RescuePrioritySetting', 'RescueCriteriaSnapshot', 'RescueCriteriaLineSnapshot'],
        'field_reports' => ['ResponderFieldReport', 'ResponderFieldReportMember', 'ResponderFieldReportDetail', 'FieldReportCategory', 'PropertyDamageLevel', 'ResponderCheckIn', 'HqFieldReport'],
        'navigation' => ['ResponderRoute', 'RouteCoordinate', 'ResponderLocationLog', 'CachedRoute', 'BarangayBoundary', 'MappingHazardLayer', 'MapTileMetadata', 'OfflinePack', 'TeamBase', 'NavigationDevice'],
        'household_safety' => ['MemberDisasterStatus', 'MemberStatus', 'MemberStatusLog', 'HouseholdDisaster', 'HouseholdStatusLog', 'StatusReminder', 'TrustedHousehold', 'HouseholdTrustedPin', 'HouseholdGeographicClassification', 'PurokBoundary', 'HouseholdMobileNotification'],
        'communications' => ['ResponderCommunicationLog', 'DisasterBroadcast', 'WeatherLog', 'UserNotification', 'WebPushSubscription', 'WebPushDelivery', 'OfficialAdvisoryNotification'],
        'reports_and_requests' => ['SituationReport', 'SituationReportSubmission', 'IncidentArchive', 'RequestValidation', 'ExternalReportRequest', 'UrgencyLevel'],
        'authentication' => ['PasswordResetRequest', 'RememberedDevice', 'HouseholdLoginAccount', 'HouseholdDeviceOwner', 'HouseholdPasswordRecoveryOtp'],
        'infrastructure' => ['DeviceTrackingLog', 'RealtimeOutbox', 'SyncEvent', 'ReportIdempotency', 'OperationalCounter', 'ResqOrphanReference'],
    ],
];
