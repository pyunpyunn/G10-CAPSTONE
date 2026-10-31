# Resqperation database / Eloquent map

Verified October 31, 2026 against shared_db. This schema-only export includes no credentials or business rows. Existing shared keys and constraints were retained; only missing compatible Resqperation structures were added.

NavigationDevice maps device_tokens; TeamBase maps rescue_teams using base-field aliases. Household login/device ownership/OTP/advisory functionality uses the consolidated shared tables, not retired duplicate tables. The query-builder baseline has a composite primary key. Shared dependencies remain shared, even when a Resqperation model references them.

| Table | Models | Primary key | Outgoing FK column mappings |
|---|---|---|---:|
| `affected_areas` | AffectedArea | `affected_area_id` | 5 |
| `barangay_boundaries` | BarangayBoundary | `psgc_code` | 1 |
| `cached_routes` | CachedRoute | `id` | 3 |
| `device_tokens` | NavigationDevice | `id` | 6 |
| `device_tracking_logs` | DeviceTrackingLog | `tracking_id` | 3 |
| `disaster_broadcasts` | DisasterBroadcast | `broadcast_id` | 4 |
| `dispatch_statuses` | DispatchStatus | `status_key` | 0 |
| `dispatch_trips` | DispatchTrip | `id` | 3 |
| `external_report_requests` | ExternalReportRequest | `id` | 1 |
| `field_report_categories` | FieldReportCategory | `category_id` | 0 |
| `household_disasters` | HouseholdDisaster | `household_disaster_id` | 4 |
| `household_geographic_classifications` | HouseholdGeographicClassification | `household_id` | 3 |
| `household_mobile_notifications` | HouseholdMobileNotification | `notification_id` | 1 |
| `household_status_logs` | HouseholdStatusLog | `status_log_id` | 3 |
| `household_trusted_pins` | HouseholdTrustedPin | `household_id` | 1 |
| `hq_field_reports` | HqFieldReport | `hq_report_id` | 3 |
| `incident_archives` | IncidentArchive | `archive_id` | 2 |
| `landing_inquiries` | LandingInquiry | `inquiry_id` | 1 |
| `map_tile_metadata` | MapTileMetadata | `tile_id` | 0 |
| `mapping_hazard_layers` | MappingHazardLayer | `id` | 1 |
| `member_disaster_statuses` | MemberDisasterStatus | `member_status_id` | 5 |
| `member_status_logs` | MemberStatusLog | `member_status_log_id` | 9 |
| `member_statuses` | MemberStatus | `status_id` | 0 |
| `offline_packs` | OfflinePack | `version` | 0 |
| `operational_sequences` | OperationalCounter | `sequence_key` | 0 |
| `password_reset_requests` | PasswordResetRequest | `request_id` | 2 |
| `property_damage_levels` | PropertyDamageLevel | `damage_key` | 0 |
| `purok_boundaries` | PurokBoundary | `purok_id` | 1 |
| `realtime_outbox` | RealtimeOutbox | `id` | 0 |
| `remembered_devices` | RememberedDevice | `remembered_device_id` | 1 |
| `report_idempotency` | ReportIdempotency | `scope` | 0 |
| `request_validations` | RequestValidation | `validation_id` | 2 |
| `rescue_criteria_contact_baselines` |  | `event_id, household_id` | 2 |
| `rescue_criteria_line_snapshots` | RescueCriteriaLineSnapshot | `id` | 2 |
| `rescue_criteria_snapshots` | RescueCriteriaSnapshot | `id` | 2 |
| `rescue_priority_settings` | RescuePrioritySetting | `version` | 1 |
| `rescue_teams` | RescueTeam, TeamBase | `team_id` | 2 |
| `responder_assignments` | ResponderAssignment | `assignment_id` | 6 |
| `responder_check_ins` | ResponderCheckIn | `check_in_id` | 6 |
| `responder_communication_logs` | ResponderCommunicationLog | `communication_id` | 3 |
| `responder_field_report_details` | ResponderFieldReportDetail | `detail_id` | 2 |
| `responder_field_report_members` | ResponderFieldReportMember | `detail_id` | 5 |
| `responder_field_reports` | ResponderFieldReport | `report_id` | 5 |
| `responder_location_logs` | ResponderLocationLog | `log_id` | 1 |
| `responder_routes` | ResponderRoute | `route_id` | 3 |
| `responders` | Responder | `responder_id` | 2 |
| `resq_orphan_references` | ResqOrphanReference | `id` | 0 |
| `route_coordinates` | RouteCoordinate | `coordinate_id` | 1 |
| `situation_report_submissions` | SituationReportSubmission | `id` | 1 |
| `situation_reports` | SituationReport | `sit_rep_id` | 2 |
| `status_reminders` | StatusReminder | `reminder_id` | 3 |
| `sync_events` | SyncEvent | `id` | 1 |
| `trip_points` | TripPoint | `id` | 1 |
| `trusted_households` | TrustedHousehold | `connection_id` | 2 |
| `urgency_levels` | UrgencyLevel | `urgency_id` | 0 |
| `user_notifications` | UserNotification | `id` | 1 |
| `weather_logs` | WeatherLog | `weather_log_id` | 1 |
| `web_push_deliveries` | WebPushDelivery | `id` | 1 |
| `web_push_subscriptions` | WebPushSubscription | `id` | 2 |

See [current shared database audit](../../SHARED_DB_RESQPERATION_AUDIT.md) for the schema comparison, preservation results and data-readiness limitations. The older Klint relationship audit is historical evidence, not the current target schema. Independent caches/queues and polymorphic journals may legitimately have no physical parent.
