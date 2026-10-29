# Resqperation database / Eloquent map

Verified against the shared Klint schema. This export contains schema metadata only, with no passwords, tokens or business row data. `resqperation-erd.json` records column types, actual primary/index keys, and enforced foreign keys for reverse engineering the ERD. Shared parent tables are referenced without claiming ownership.

`TeamBase` uses aliases over `rescue_teams`; `name`, `lat`, and `lng` map to `base_name`, `base_latitude`, and `base_longitude`. The compatibility `team_bases` table is not the canonical source. The composite-key contact baseline uses scoped query-builder writes because Eloquent has no native composite primary-key support.

| Table | Mapping | Primary key | Enforced outbound foreign keys |
|---|---|---|---:|
| `affected_areas` | AffectedArea | `affected_area_id` | 5 |
| `barangay_boundaries` | BarangayBoundary | `psgc_code` | 0 |
| `cached_routes` | CachedRoute | `id` | 3 |
| `device_tracking_logs` | DeviceTrackingLog | `tracking_id` | 0 |
| `devices` | NavigationDevice | `id` | 2 |
| `disaster_broadcasts` | DisasterBroadcast | `broadcast_id` | 4 |
| `dispatch_statuses` | DispatchStatus | `status_key` | 0 |
| `dispatch_trips` | DispatchTrip | `id` | 3 |
| `external_report_requests` | ExternalReportRequest | `id` | 1 |
| `field_report_categories` | FieldReportCategory | `category_id` | 0 |
| `household_device_owners` | HouseholdDeviceOwner | `id` | 2 |
| `household_disasters` | HouseholdDisaster | `household_disaster_id` | 4 |
| `household_geographic_classifications` | HouseholdGeographicClassification | `household_id` | 3 |
| `household_login_accounts` | HouseholdLoginAccount | `household_id` | 2 |
| `household_mobile_notifications` | HouseholdMobileNotification | `notification_id` | 1 |
| `household_password_recovery_otps` | HouseholdPasswordRecoveryOtp | `user_id` | 2 |
| `household_status_logs` | HouseholdStatusLog | `status_log_id` | 1 |
| `household_trusted_pins` | HouseholdTrustedPin | `household_id` | 1 |
| `hq_field_reports` | HqFieldReport | `hq_report_id` | 1 |
| `incident_archives` | IncidentArchive | `archive_id` | 0 |
| `landing_inquiries` | LandingInquiry | `inquiry_id` | 0 |
| `map_tile_metadata` | MapTileMetadata | `tile_id` | 0 |
| `mapping_hazard_layers` | MappingHazardLayer | `id` | 0 |
| `member_disaster_statuses` | MemberDisasterStatus | `member_status_id` | 5 |
| `member_status_logs` | MemberStatusLog | `member_status_log_id` | 0 |
| `member_statuses` | MemberStatus | `status_id` | 0 |
| `official_advisory_notifications` | OfficialAdvisoryNotification | `id` | 0 |
| `offline_packs` | OfflinePack | `version` | 0 |
| `operational_sequences` | OperationalCounter | `sequence_key` | 0 |
| `password_reset_requests` | PasswordResetRequest | `request_id` | 1 |
| `property_damage_levels` | PropertyDamageLevel | `damage_key` | 0 |
| `purok_boundaries` | PurokBoundary | `purok_id` | 1 |
| `realtime_outbox` | RealtimeOutbox | `id` | 0 |
| `remembered_devices` | RememberedDevice | `remembered_device_id` | 1 |
| `report_idempotency` | ReportIdempotency | `scope` | 0 |
| `request_validations` | RequestValidation | `validation_id` | 1 |
| `rescue_criteria_contact_baselines` | Query builder: composite primary key | `event_id, household_id` | 0 |
| `rescue_criteria_line_snapshots` | RescueCriteriaLineSnapshot | `id` | 0 |
| `rescue_criteria_snapshots` | RescueCriteriaSnapshot | `id` | 0 |
| `rescue_priority_settings` | RescuePrioritySetting | `version` | 0 |
| `rescue_teams` | RescueTeam, TeamBase | `team_id` | 2 |
| `responder_assignments` | ResponderAssignment | `assignment_id` | 5 |
| `responder_check_ins` | ResponderCheckIn | `check_in_id` | 0 |
| `responder_communication_logs` | ResponderCommunicationLog | `communication_id` | 0 |
| `responder_field_report_details` | ResponderFieldReportDetail | `detail_id` | 2 |
| `responder_field_report_members` | ResponderFieldReportMember | `detail_id` | 5 |
| `responder_field_reports` | ResponderFieldReport | `report_id` | 5 |
| `responder_location_logs` | ResponderLocationLog | `log_id` | 0 |
| `responder_routes` | ResponderRoute | `route_id` | 2 |
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
| `weather_logs` | WeatherLog | `weather_log_id` | 0 |
| `web_push_deliveries` | WebPushDelivery | `id` | 0 |
| `web_push_subscriptions` | WebPushSubscription | `id` | 0 |

History, outbox and archive tables intentionally retain historical identities; do not infer missing foreign keys from names or add cascades that erase history. See `../../DATABASE_REMEDIATION_REPORT.md` for scope, verification and unresolved shared data.
