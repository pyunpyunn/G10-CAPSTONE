-- RESQPERATION performance index proposal
-- Date: 2026-06-28
-- Purpose: Reduce loading time on dashboard, broadcast, household status,
-- mapping, dispatch, resources, notifications, and archive screens.
--
-- IMPORTANT:
-- 1. Do not run blindly on the shared DB.
-- 2. DB member should first check existing indexes:
--      SHOW INDEX FROM table_name;
-- 3. If an index with the same columns already exists, skip that statement.
-- 4. These are additive indexes only. They do not delete or rewrite data.

-- Household monitoring and dashboard counts.
ALTER TABLE households
  ADD INDEX idx_households_deleted_household (deleted_at, household_id);

ALTER TABLE household_disasters
  ADD INDEX idx_household_disasters_event_household (disaster_id, household_id),
  ADD INDEX idx_household_disasters_event_status (disaster_id, current_status_id);

ALTER TABLE household_status_logs
  ADD INDEX idx_household_status_logs_event_household_time (disaster_id, household_id, submitted_at),
  ADD INDEX idx_household_status_logs_household_time (household_id, submitted_at);

-- Household GPS, device syncing, and map display.
ALTER TABLE geotagged_locations
  ADD INDEX idx_geotagged_locations_household (household_id),
  ADD INDEX idx_geotagged_locations_household_time (household_id, updated_at);

ALTER TABLE device_tokens
  ADD INDEX idx_device_tokens_household_active (household_id, is_active),
  ADD INDEX idx_device_tokens_user_role_seen (user_id, app_role, last_seen_at),
  ADD INDEX idx_device_tokens_uuid (device_uuid);

-- Rescue dispatch and responder availability.
ALTER TABLE responder_assignments
  ADD INDEX idx_responder_assignments_event_status (disaster_id, status),
  ADD INDEX idx_responder_assignments_responder_status (responder_id, status),
  ADD INDEX idx_responder_assignments_team_status (team_id, status),
  ADD INDEX idx_responder_assignments_household_status (household_id, status);

ALTER TABLE responder_location_logs
  ADD INDEX idx_responder_location_logs_responder_time (responder_id, logged_at),
  ADD INDEX idx_responder_location_logs_time (logged_at);

ALTER TABLE responder_routes
  ADD INDEX idx_responder_routes_assignment_status (assignment_id, route_status);

ALTER TABLE route_coordinates
  ADD INDEX idx_route_coordinates_route_order (route_id, sequence_order),
  ADD INDEX idx_route_coordinates_route_time (route_id, recorded_at);

ALTER TABLE responder_communication_logs
  ADD INDEX idx_responder_comm_team_event_time (team_id, disaster_id, timestamp, communication_id),
  ADD INDEX idx_responder_comm_responder_event_time (responder_id, disaster_id, timestamp, communication_id),
  ADD INDEX idx_responder_comm_event_time (disaster_id, timestamp, communication_id);

ALTER TABLE responders
  ADD INDEX idx_responders_team_duty_deployed (team_id, duty_status, is_deployed),
  ADD INDEX idx_responders_user (user_id);

ALTER TABLE rescue_teams
  ADD INDEX idx_rescue_teams_code_name (team_code, team_name);

-- Resource request validation queue.
ALTER TABLE resource_requests
  ADD INDEX idx_resource_requests_status_created (status_id, created_at),
  ADD INDEX idx_resource_requests_event_created (disaster_id, created_at),
  ADD INDEX idx_resource_requests_source_created (source_system, created_at);

-- Broadcast, weather, situation report, and archive screens.
ALTER TABLE disaster_events
  ADD INDEX idx_disaster_events_status_dates (status, declared_at, ended_at);

ALTER TABLE disaster_broadcasts
  ADD INDEX idx_disaster_broadcasts_event_created (disaster_id, created_at);

ALTER TABLE weather_logs
  ADD INDEX idx_weather_logs_event_created (disaster_id, created_at);

ALTER TABLE situation_reports
  ADD INDEX idx_situation_reports_event_created (disaster_id, created_at);

ALTER TABLE incident_archives
  ADD INDEX idx_incident_archives_type_created (archive_type, created_at);

-- Notifications page and topbar badge.
ALTER TABLE notifications
  ADD INDEX idx_notifications_user_read_created (user_id, read_at, created_at),
  ADD INDEX idx_notifications_role_read_created (target_role, read_at, created_at);
