-- RESQPERATION G10 additive database update proposal based on all.sql
-- Date: 2026-06-01
-- Source reviewed: C:\Users\Kathleen Barro\Downloads\all.sql
--
-- IMPORTANT:
-- 1. This is a REVIEW SCRIPT. Do not run it until the database member approves it.
-- 2. Do not run all.sql directly on the shared DB because it contains DROP TABLE IF EXISTS.
-- 3. This script does not delete, drop, or rename existing tables.
-- 4. Existing same-purpose tables are reused through ALTER TABLE.
-- 5. New tables are only proposed where no suitable existing table exists.
--
-- Existing DB naming style observed in all.sql:
-- - household_id, user_id, event_id, request_id are varchar(255)
-- - responder_id, team_id, status_id are int
-- - custom tables commonly use datetime columns

-- ============================================================
-- A. Final table mapping decisions
-- ============================================================
--
-- Planned/lacking name           Use this existing table instead
-- ------------------------------------------------------------
-- broadcast_logs                 disaster_broadcasts + notification_logs
-- household_devices              device_tokens + device_tracking_logs
-- household_geotags              geotagged_locations
-- evacuation_sites               evacuation_centers
-- rescuer_profiles               responders
-- rescue_dispatches              responder_assignments
-- dispatch_routes                responder_routes + route_coordinates
-- resource_items                 resource_requests only for v1
--
-- Real gaps that still need new tables:
-- weather_logs
-- household_status_logs
-- request_validations
-- audit_logs
-- integration_logs

-- ============================================================
-- B. Household mobile devices
-- Existing tables: device_tokens, device_tracking_logs
-- Purpose covered: household_devices
-- ============================================================

ALTER TABLE device_tokens
    ADD COLUMN device_uuid VARCHAR(150) NULL AFTER id,
    ADD COLUMN member_id VARCHAR(255) NULL AFTER household_id,
    ADD COLUMN device_name VARCHAR(100) NULL AFTER member_id,
    ADD COLUMN platform VARCHAR(30) NULL AFTER device_name,
    ADD COLUMN app_role VARCHAR(30) NULL AFTER platform,
    ADD COLUMN push_provider VARCHAR(50) NULL AFTER app_role,
    ADD COLUMN expo_push_token VARCHAR(255) NULL AFTER player_id,
    ADD COLUMN location_permission_status VARCHAR(30) NOT NULL DEFAULT 'unknown' AFTER signal_strength,
    ADD COLUMN notification_permission_status VARCHAR(30) NOT NULL DEFAULT 'unknown' AFTER location_permission_status,
    ADD COLUMN last_latitude DECIMAL(10,7) NULL AFTER notification_permission_status,
    ADD COLUMN last_longitude DECIMAL(10,7) NULL AFTER last_latitude,
    ADD COLUMN last_location_label VARCHAR(255) NULL AFTER last_longitude,
    ADD COLUMN last_location_accuracy_m DECIMAL(8,2) NULL AFTER last_location_label,
    ADD COLUMN last_location_at DATETIME NULL AFTER last_location_accuracy_m,
    ADD COLUMN last_seen_at DATETIME NULL AFTER last_location_at,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_seen_at;

ALTER TABLE device_tracking_logs
    ADD COLUMN device_token_id BIGINT NULL AFTER tracking_id,
    ADD COLUMN member_id VARCHAR(255) NULL AFTER household_id,
    ADD COLUMN location_label VARCHAR(255) NULL AFTER longitude,
    ADD COLUMN accuracy_m DECIMAL(8,2) NULL AFTER location_label,
    ADD COLUMN location_source VARCHAR(50) NULL AFTER accuracy_m,
    ADD COLUMN is_allowed_location TINYINT(1) NOT NULL DEFAULT 1 AFTER location_source;

-- Suggested indexes after approval:
-- CREATE INDEX idx_device_tokens_household ON device_tokens (household_id);
-- CREATE INDEX idx_device_tokens_member ON device_tokens (member_id);
-- CREATE INDEX idx_device_tokens_last_seen ON device_tokens (last_seen_at);
-- CREATE INDEX idx_device_tracking_household_logged ON device_tracking_logs (household_id, logged_at);

-- ============================================================
-- C. Household geotags
-- Existing table: geotagged_locations
-- Purpose covered: household_geotags
-- ============================================================

ALTER TABLE geotagged_locations
    ADD COLUMN location_label VARCHAR(255) NULL AFTER longitude,
    ADD COLUMN accuracy_m DECIMAL(8,2) NULL AFTER location_label,
    ADD COLUMN geotag_source VARCHAR(50) NOT NULL DEFAULT 'household_mobile' AFTER accuracy_m,
    ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER geotag_source,
    ADD COLUMN verified_by_user_id VARCHAR(255) NULL AFTER is_verified,
    ADD COLUMN verified_at DATETIME NULL AFTER verified_by_user_id,
    ADD COLUMN created_at DATETIME NULL AFTER verified_at;

-- ============================================================
-- D. Household latest status per disaster
-- Existing table: household_disasters
-- Purpose: latest row/status per household per disaster event
-- ============================================================

ALTER TABLE household_disasters
    ADD COLUMN current_status_id INT NULL AFTER initial_status_id,
    ADD COLUMN last_status_source VARCHAR(50) NULL AFTER current_status_id,
    ADD COLUMN last_status_notes TEXT NULL AFTER last_status_source,
    ADD COLUMN last_reported_by_user_id VARCHAR(255) NULL AFTER last_status_notes,
    ADD COLUMN last_responder_id INT NULL AFTER last_reported_by_user_id,
    ADD COLUMN last_device_token_id BIGINT NULL AFTER last_responder_id,
    ADD COLUMN last_latitude DECIMAL(10,7) NULL AFTER last_device_token_id,
    ADD COLUMN last_longitude DECIMAL(10,7) NULL AFTER last_latitude,
    ADD COLUMN last_battery_level INT NULL AFTER last_longitude,
    ADD COLUMN last_reported_at DATETIME NULL AFTER last_battery_level,
    ADD COLUMN priority_level VARCHAR(30) NULL AFTER last_reported_at,
    ADD COLUMN needs_dispatch TINYINT(1) NOT NULL DEFAULT 0 AFTER priority_level,
    ADD COLUMN updated_at DATETIME NULL AFTER needs_dispatch;

-- ============================================================
-- E. Household status history/source logs
-- New table because no existing table clearly stores every status change
-- HQ/Admin reviews these records only.
-- HQ/Admin should not manually create household status reports.
-- ============================================================

CREATE TABLE IF NOT EXISTS household_status_logs (
    status_log_id INT NOT NULL AUTO_INCREMENT,
    disaster_id VARCHAR(255) NULL,
    household_id VARCHAR(255) NOT NULL,
    status_id INT NOT NULL,
    source VARCHAR(50) NOT NULL,
    submitted_by_user_id VARCHAR(255) NULL,
    responder_id INT NULL,
    device_token_id BIGINT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    location_label VARCHAR(255) NULL,
    location_accuracy_m DECIMAL(8,2) NULL,
    battery_level INT NULL,
    signal_strength INT NULL,
    notes TEXT NULL,
    submitted_at DATETIME NULL,
    reviewed_by_user_id VARCHAR(255) NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (status_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Suggested indexes after approval:
-- CREATE INDEX idx_hsl_disaster_household ON household_status_logs (disaster_id, household_id);
-- CREATE INDEX idx_hsl_status ON household_status_logs (status_id);
-- CREATE INDEX idx_hsl_submitted_at ON household_status_logs (submitted_at);

-- ============================================================
-- F. Evacuation sites
-- Existing table: evacuation_centers
-- Purpose covered: evacuation_sites
-- ============================================================

ALTER TABLE evacuation_centers
    ADD COLUMN center_type VARCHAR(80) NULL AFTER name,
    ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER capacity,
    ADD COLUMN current_occupancy INT NULL AFTER status,
    ADD COLUMN contact_person VARCHAR(150) NULL AFTER current_occupancy,
    ADD COLUMN contact_number VARCHAR(30) NULL AFTER contact_person,
    ADD COLUMN notes TEXT NULL AFTER osm_address,
    ADD COLUMN updated_at DATETIME NULL AFTER notes;

-- ============================================================
-- G. Rescuer / responder accounts
-- Existing table: responders
-- Purpose covered: rescuer_profiles
-- ============================================================

ALTER TABLE responders
    ADD COLUMN user_id VARCHAR(255) NULL AFTER responder_id,
    ADD COLUMN responder_code VARCHAR(80) NULL AFTER user_id,
    ADD COLUMN emergency_contact_name VARCHAR(150) NULL AFTER contact_number,
    ADD COLUMN emergency_contact_number VARCHAR(30) NULL AFTER emergency_contact_name,
    ADD COLUMN blood_type VARCHAR(10) NULL AFTER gender,
    ADD COLUMN skills TEXT NULL AFTER address,
    ADD COLUMN training_notes TEXT NULL AFTER skills,
    ADD COLUMN certification_reference VARCHAR(150) NULL AFTER training_notes,
    ADD COLUMN equipment_notes TEXT NULL AFTER certification_reference,
    ADD COLUMN duty_status VARCHAR(30) NOT NULL DEFAULT 'off_duty' AFTER is_deployed,
    ADD COLUMN last_active_at DATETIME NULL AFTER duty_status,
    ADD COLUMN updated_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at;

-- Suggested indexes after approval:
-- CREATE INDEX idx_responders_user ON responders (user_id);
-- CREATE INDEX idx_responders_team ON responders (team_id);
-- CREATE INDEX idx_responders_duty_status ON responders (duty_status);

-- ============================================================
-- H. Rescue teams
-- Existing table: rescue_teams
-- ============================================================

ALTER TABLE rescue_teams
    ADD COLUMN team_code VARCHAR(50) NULL AFTER team_id,
    ADD COLUMN team_type VARCHAR(80) NULL AFTER team_name,
    ADD COLUMN assigned_purok_id INT NULL AFTER team_type,
    ADD COLUMN leader_responder_id INT NULL AFTER assigned_purok_id,
    ADD COLUMN duty_status VARCHAR(30) NOT NULL DEFAULT 'standby' AFTER leader_responder_id,
    ADD COLUMN created_at DATETIME NULL AFTER duty_status,
    ADD COLUMN updated_at DATETIME NULL AFTER created_at;

-- ============================================================
-- I. Rescue dispatches
-- Existing table: responder_assignments
-- Purpose covered: rescue_dispatches
-- ============================================================

ALTER TABLE responder_assignments
    ADD COLUMN assignment_code VARCHAR(80) NULL AFTER assignment_id,
    ADD COLUMN team_id INT NULL AFTER responder_id,
    ADD COLUMN household_id VARCHAR(255) NULL AFTER affected_area_id,
    ADD COLUMN status_log_id INT NULL AFTER household_id,
    ADD COLUMN priority_level VARCHAR(30) NULL AFTER route_notes,
    ADD COLUMN dispatch_notes TEXT NULL AFTER priority_level,
    ADD COLUMN created_by_admin_id VARCHAR(255) NULL AFTER dispatch_notes,
    ADD COLUMN accepted_at DATETIME NULL AFTER assigned_at,
    ADD COLUMN en_route_at DATETIME NULL AFTER accepted_at,
    ADD COLUMN arrived_at DATETIME NULL AFTER en_route_at,
    ADD COLUMN completed_at DATETIME NULL AFTER arrived_at,
    ADD COLUMN outcome_notes TEXT NULL AFTER completed_at,
    ADD COLUMN updated_at DATETIME NULL AFTER outcome_notes;

-- ============================================================
-- J. Dispatch routes
-- Existing tables: responder_routes, route_coordinates
-- Purpose covered: dispatch_routes
-- ============================================================

ALTER TABLE responder_routes
    ADD COLUMN route_status VARCHAR(30) NOT NULL DEFAULT 'planned' AFTER route_name,
    ADD COLUMN start_latitude DECIMAL(10,7) NULL AFTER route_status,
    ADD COLUMN start_longitude DECIMAL(10,7) NULL AFTER start_latitude,
    ADD COLUMN end_latitude DECIMAL(10,7) NULL AFTER start_longitude,
    ADD COLUMN end_longitude DECIMAL(10,7) NULL AFTER end_latitude,
    ADD COLUMN estimated_distance_km DECIMAL(8,2) NULL AFTER end_longitude,
    ADD COLUMN estimated_duration_min INT NULL AFTER estimated_distance_km,
    ADD COLUMN route_polyline MEDIUMTEXT NULL AFTER estimated_duration_min,
    ADD COLUMN updated_at DATETIME NULL AFTER created_at;

ALTER TABLE route_coordinates
    ADD COLUMN recorded_at DATETIME NULL AFTER sequence_order,
    ADD COLUMN accuracy_m DECIMAL(8,2) NULL AFTER recorded_at;

-- ============================================================
-- K. Disaster broadcasting
-- Existing tables: disaster_broadcasts, notifications, notification_logs
-- Purpose covered: broadcast_logs
-- ============================================================

ALTER TABLE disaster_broadcasts
    ADD COLUMN broadcast_title VARCHAR(150) NULL AFTER broadcast_id,
    ADD COLUMN severity_id INT NULL AFTER sent_by_admin_id,
    ADD COLUMN scope_type VARCHAR(50) NULL AFTER severity_id,
    ADD COLUMN target_purok_id INT NULL AFTER scope_type,
    ADD COLUMN target_area_id INT NULL AFTER target_purok_id,
    ADD COLUMN channel VARCHAR(50) NULL AFTER allowed_statuses,
    ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'sent' AFTER channel,
    ADD COLUMN notification_id INT NULL AFTER status,
    ADD COLUMN weather_log_id INT NULL AFTER notification_id,
    ADD COLUMN created_at DATETIME NULL AFTER sent_at,
    ADD COLUMN updated_at DATETIME NULL AFTER created_at;

-- ============================================================
-- L. Weather logs
-- New table because all.sql has no confirmed weather snapshot table
-- ============================================================

CREATE TABLE IF NOT EXISTS weather_logs (
    weather_log_id INT NOT NULL AUTO_INCREMENT,
    disaster_id VARCHAR(255) NULL,
    source_name VARCHAR(80) NOT NULL,
    source_url VARCHAR(255) NULL,
    condition_name VARCHAR(80) NULL,
    temperature DECIMAL(5,2) NULL,
    rainfall_mm DECIMAL(8,2) NULL,
    wind_speed DECIMAL(8,2) NULL,
    wind_direction VARCHAR(50) NULL,
    humidity INT NULL,
    advisory_title VARCHAR(255) NULL,
    advisory_text TEXT NULL,
    raw_payload JSON NULL,
    observed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (weather_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Suggested indexes after approval:
-- CREATE INDEX idx_weather_logs_disaster ON weather_logs (disaster_id);
-- CREATE INDEX idx_weather_logs_observed_at ON weather_logs (observed_at);

-- ============================================================
-- M. Resource and personnel requests
-- Existing table: resource_requests
-- Purpose: receive from EvaTrack/manual entry, validate, forward to TrackingAid
-- Delivery is not handled by RESQPERATION.
-- ============================================================

ALTER TABLE resource_requests
    ADD COLUMN source_system VARCHAR(80) NULL AFTER request_id,
    ADD COLUMN external_request_id VARCHAR(120) NULL AFTER source_system,
    ADD COLUMN request_category VARCHAR(50) NULL AFTER external_request_id,
    ADD COLUMN item_name VARCHAR(150) NULL AFTER resource_type,
    ADD COLUMN unit VARCHAR(50) NULL AFTER quantity,
    ADD COLUMN validation_status VARCHAR(30) NOT NULL DEFAULT 'needs_validation' AFTER status_id,
    ADD COLUMN validation_notes TEXT NULL AFTER validation_status,
    ADD COLUMN validated_by_user_id VARCHAR(255) NULL AFTER validation_notes,
    ADD COLUMN validated_at DATETIME NULL AFTER validated_by_user_id,
    ADD COLUMN forwarded_to_system VARCHAR(80) NULL AFTER validated_at,
    ADD COLUMN forwarded_at DATETIME NULL AFTER forwarded_to_system,
    ADD COLUMN trackingaid_reference VARCHAR(120) NULL AFTER forwarded_at;

CREATE TABLE IF NOT EXISTS request_validations (
    validation_id INT NOT NULL AUTO_INCREMENT,
    request_id VARCHAR(255) NOT NULL,
    validation_status VARCHAR(30) NOT NULL,
    validator_user_id VARCHAR(255) NULL,
    validation_notes TEXT NULL,
    missing_information TEXT NULL,
    duplicate_request_id VARCHAR(255) NULL,
    validated_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (validation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Suggested indexes after approval:
-- CREATE INDEX idx_request_validations_request ON request_validations (request_id);
-- CREATE INDEX idx_request_validations_status ON request_validations (validation_status);

-- ============================================================
-- N. Data source and integration tracking
-- Existing related tables: data_sources, import_logs, csv_uploads
-- New integration_logs is still needed because TrackingAid forwarding is outbound.
-- ============================================================

ALTER TABLE data_sources
    ADD COLUMN system_name VARCHAR(80) NULL AFTER type,
    ADD COLUMN base_url VARCHAR(255) NULL AFTER system_name,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER base_url,
    ADD COLUMN notes TEXT NULL AFTER is_active;

ALTER TABLE import_logs
    ADD COLUMN module VARCHAR(80) NULL AFTER data_source_id,
    ADD COLUMN external_reference VARCHAR(120) NULL AFTER module,
    ADD COLUMN raw_payload JSON NULL AFTER external_reference;

CREATE TABLE IF NOT EXISTS integration_logs (
    integration_log_id INT NOT NULL AUTO_INCREMENT,
    system_name VARCHAR(80) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    module VARCHAR(80) NOT NULL,
    reference_table VARCHAR(80) NULL,
    reference_id VARCHAR(255) NULL,
    external_reference VARCHAR(120) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    request_payload JSON NULL,
    response_payload JSON NULL,
    error_message TEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (integration_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- direction examples: inbound, outbound
-- system_name examples: SafeTrack, EvaTrack, TrackingAid, PAGASA, OpenMeteo, ExpoPush

-- ============================================================
-- O. Audit logs
-- New table because import_logs/analytics_job_logs are not general user action logs
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_log_id INT NOT NULL AUTO_INCREMENT,
    user_id VARCHAR(255) NULL,
    role_key VARCHAR(50) NULL,
    module VARCHAR(80) NOT NULL,
    action VARCHAR(80) NOT NULL,
    reference_table VARCHAR(80) NULL,
    reference_id VARCHAR(255) NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (audit_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Suggested indexes after approval:
-- CREATE INDEX idx_audit_logs_user_created ON audit_logs (user_id, created_at);
-- CREATE INDEX idx_audit_logs_module_action ON audit_logs (module, action);

-- ============================================================
-- P. Situation reports and archive support
-- Existing tables: situation_reports, incident_archives
-- ============================================================

ALTER TABLE situation_reports
    ADD COLUMN report_number VARCHAR(80) NULL AFTER sit_rep_id,
    ADD COLUMN report_status VARCHAR(30) NOT NULL DEFAULT 'draft' AFTER summary,
    ADD COLUMN reviewed_by_user_id VARCHAR(255) NULL AFTER report_status,
    ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by_user_id,
    ADD COLUMN archived_at DATETIME NULL AFTER generated_at,
    ADD COLUMN updated_at DATETIME NULL AFTER archived_at;

ALTER TABLE incident_archives
    ADD COLUMN archive_type VARCHAR(50) NULL AFTER archive_id,
    ADD COLUMN reference_table VARCHAR(80) NULL AFTER disaster_id,
    ADD COLUMN reference_id VARCHAR(255) NULL AFTER reference_table,
    ADD COLUMN created_at DATETIME NULL AFTER archived_at;

-- End of additive review script.
