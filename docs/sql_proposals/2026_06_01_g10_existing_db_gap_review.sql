-- RESQPERATION G10 existing database gap review
-- Date: 2026-06-01
-- Purpose: review-only SQL based on the actual shared MySQL structure.
--
-- SUPERSEDED NOTE:
-- A newer DB export was provided at:
-- C:\Users\Kathleen Barro\Downloads\all.sql
--
-- Use this newer proposal instead:
-- docs/sql_proposals/2026_06_01_g10_all_sql_additive_update.sql
--
-- This older file is kept only for audit/history because some column types
-- used BIGINT while the latest all.sql uses varchar(255) for important IDs
-- such as user_id, household_id, event_id, and request_id.
--
-- DO NOT RUN THIS SCRIPT YET.
-- The shared DB already has 77 tables and uses custom primary keys such as:
-- users.user_id, roles.role_id, disaster_events.event_id, households.household_id.
--
-- This file follows the existing database style instead of creating a second
-- Laravel-default schema with plain `id` keys.

-- ============================================================
-- 0. Local Laravel .env fix needed before backend development
-- ============================================================
--
-- This is NOT SQL. Update backend-laravel/.env manually:
--
-- DB_CONNECTION=mysql
-- DB_DATABASE=klint
--
-- Then run:
-- php artisan config:clear
-- php artisan migrate:status
--
-- Do not run migrations yet.

-- ============================================================
-- 1. Household device gaps
-- Existing table: device_tokens
-- Current columns:
-- id, household_id, player_id, battery_level, signal_strength,
-- logged_at, created_at, updated_at
-- ============================================================

-- Review whether player_id is meant for OneSignal, Expo, or a generic push token.
-- If using Expo Push Service, keep the old field for compatibility and add expo_push_token.

ALTER TABLE device_tokens
    ADD COLUMN member_id BIGINT UNSIGNED NULL AFTER household_id,
    ADD COLUMN device_label VARCHAR(100) NULL AFTER member_id,
    ADD COLUMN platform VARCHAR(30) NULL AFTER device_label,
    ADD COLUMN expo_push_token VARCHAR(255) NULL AFTER player_id,
    ADD COLUMN location_permission_status VARCHAR(30) NOT NULL DEFAULT 'unknown' AFTER signal_strength,
    ADD COLUMN last_latitude DECIMAL(10,7) NULL AFTER location_permission_status,
    ADD COLUMN last_longitude DECIMAL(10,7) NULL AFTER last_latitude,
    ADD COLUMN last_location_at TIMESTAMP NULL AFTER last_longitude,
    ADD COLUMN last_seen_at TIMESTAMP NULL AFTER last_location_at,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_seen_at;

-- Suggested indexes after approval:
-- CREATE INDEX device_tokens_household_index ON device_tokens (household_id);
-- CREATE INDEX device_tokens_member_index ON device_tokens (member_id);
-- CREATE INDEX device_tokens_last_seen_index ON device_tokens (last_seen_at);

-- ============================================================
-- 2. Household status history
-- Existing related tables:
-- household_statuses, household_disasters, responder_field_reports, hq_field_reports
-- ============================================================

-- This table keeps the latest and historical status reports simple.
-- HQ can review this table, but HQ should not manually create status reports.
-- Allowed source values should be validated in Laravel:
-- household_mobile, responder_field_report, external_import

CREATE TABLE household_status_logs (
    status_log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_id BIGINT UNSIGNED NULL,
    household_id BIGINT UNSIGNED NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(50) NOT NULL,
    submitted_by_user_id BIGINT UNSIGNED NULL,
    responder_id BIGINT UNSIGNED NULL,
    device_token_id BIGINT UNSIGNED NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    battery_level TINYINT UNSIGNED NULL,
    notes TEXT NULL,
    submitted_at TIMESTAMP NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX household_status_logs_disaster_index (disaster_id),
    INDEX household_status_logs_household_index (household_id),
    INDEX household_status_logs_status_index (status_id),
    INDEX household_status_logs_submitted_at_index (submitted_at)
);

-- Add foreign keys only after confirming all referenced data is clean:
-- ALTER TABLE household_status_logs
--   ADD CONSTRAINT household_status_logs_disaster_id_foreign
--     FOREIGN KEY (disaster_id) REFERENCES disaster_events(event_id) ON DELETE SET NULL,
--   ADD CONSTRAINT household_status_logs_household_id_foreign
--     FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE CASCADE,
--   ADD CONSTRAINT household_status_logs_status_id_foreign
--     FOREIGN KEY (status_id) REFERENCES household_statuses(status_id),
--   ADD CONSTRAINT household_status_logs_submitted_by_user_id_foreign
--     FOREIGN KEY (submitted_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
--   ADD CONSTRAINT household_status_logs_responder_id_foreign
--     FOREIGN KEY (responder_id) REFERENCES responders(responder_id) ON DELETE SET NULL,
--   ADD CONSTRAINT household_status_logs_device_token_id_foreign
--     FOREIGN KEY (device_token_id) REFERENCES device_tokens(id) ON DELETE SET NULL;

-- ============================================================
-- 3. Responder / rescuer account gaps
-- Existing tables: responders, rescue_teams
-- Current responders columns:
-- responder_id, created_by_admin_id, team_id, username, password_hash,
-- full_name, title, contact_number, date_of_birth, gender, address,
-- is_validated, is_deployed, created_at
-- ============================================================

-- Recommended beginner approach:
-- Use users for login/auth tokens, then link responders.user_id to users.user_id.
-- This avoids maintaining two separate authentication systems.

ALTER TABLE responders
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER responder_id,
    ADD COLUMN emergency_contact_name VARCHAR(150) NULL AFTER contact_number,
    ADD COLUMN emergency_contact_number VARCHAR(30) NULL AFTER emergency_contact_name,
    ADD COLUMN skills TEXT NULL AFTER address,
    ADD COLUMN training_notes TEXT NULL AFTER skills,
    ADD COLUMN certification_reference VARCHAR(150) NULL AFTER training_notes,
    ADD COLUMN equipment_notes TEXT NULL AFTER certification_reference,
    ADD COLUMN duty_status VARCHAR(30) NOT NULL DEFAULT 'off_duty' AFTER is_deployed;

-- Suggested indexes after approval:
-- CREATE INDEX responders_user_index ON responders (user_id);
-- CREATE INDEX responders_team_index ON responders (team_id);
-- CREATE INDEX responders_duty_status_index ON responders (duty_status);

-- Optional FK after confirming users.user_id values:
-- ALTER TABLE responders
--   ADD CONSTRAINT responders_user_id_foreign
--     FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- ============================================================
-- 4. Rescue team gaps
-- Existing table: rescue_teams
-- Current columns: team_id, team_name
-- ============================================================

ALTER TABLE rescue_teams
    ADD COLUMN team_code VARCHAR(50) NULL AFTER team_id,
    ADD COLUMN team_type VARCHAR(80) NULL AFTER team_name,
    ADD COLUMN assigned_purok_id BIGINT UNSIGNED NULL AFTER team_type,
    ADD COLUMN leader_responder_id BIGINT UNSIGNED NULL AFTER assigned_purok_id,
    ADD COLUMN duty_status VARCHAR(30) NOT NULL DEFAULT 'standby' AFTER leader_responder_id,
    ADD COLUMN created_at TIMESTAMP NULL AFTER duty_status,
    ADD COLUMN updated_at TIMESTAMP NULL AFTER created_at;

-- Suggested indexes after approval:
-- CREATE UNIQUE INDEX rescue_teams_team_code_unique ON rescue_teams (team_code);
-- CREATE INDEX rescue_teams_assigned_purok_index ON rescue_teams (assigned_purok_id);
-- CREATE INDEX rescue_teams_duty_status_index ON rescue_teams (duty_status);

-- ============================================================
-- 5. Resource request validation and external systems
-- Existing table: resource_requests
-- Current columns:
-- request_id, evacuation_center_id, requested_by, handled_by,
-- resource_type, quantity, description, urgency_id, status_id,
-- created_at, updated_at
-- ============================================================

ALTER TABLE resource_requests
    ADD COLUMN source_system VARCHAR(80) NULL AFTER request_id,
    ADD COLUMN external_request_id VARCHAR(100) NULL AFTER source_system,
    ADD COLUMN validation_status VARCHAR(30) NOT NULL DEFAULT 'needs_validation' AFTER status_id,
    ADD COLUMN validation_notes TEXT NULL AFTER validation_status,
    ADD COLUMN validated_by_user_id BIGINT UNSIGNED NULL AFTER validation_notes,
    ADD COLUMN validated_at TIMESTAMP NULL AFTER validated_by_user_id,
    ADD COLUMN forwarded_to_system VARCHAR(80) NULL AFTER validated_at,
    ADD COLUMN forwarded_at TIMESTAMP NULL AFTER forwarded_to_system,
    ADD COLUMN trackingaid_reference VARCHAR(100) NULL AFTER forwarded_at;

CREATE TABLE request_validations (
    validation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    validation_status VARCHAR(30) NOT NULL,
    validator_user_id BIGINT UNSIGNED NULL,
    validation_notes TEXT NULL,
    missing_information TEXT NULL,
    duplicate_request_id BIGINT UNSIGNED NULL,
    validated_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX request_validations_request_index (request_id),
    INDEX request_validations_status_index (validation_status)
);

-- Foreign keys after approval:
-- ALTER TABLE request_validations
--   ADD CONSTRAINT request_validations_request_id_foreign
--     FOREIGN KEY (request_id) REFERENCES resource_requests(request_id) ON DELETE CASCADE,
--   ADD CONSTRAINT request_validations_validator_user_id_foreign
--     FOREIGN KEY (validator_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
--   ADD CONSTRAINT request_validations_duplicate_request_id_foreign
--     FOREIGN KEY (duplicate_request_id) REFERENCES resource_requests(request_id) ON DELETE SET NULL;

-- ============================================================
-- 6. Integration logs for EvaTrack and TrackingAid
-- ============================================================

CREATE TABLE integration_logs (
    integration_log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    system_name VARCHAR(80) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    module VARCHAR(80) NOT NULL,
    reference_table VARCHAR(80) NULL,
    reference_id BIGINT UNSIGNED NULL,
    external_reference VARCHAR(120) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    request_payload JSON NULL,
    response_payload JSON NULL,
    error_message TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX integration_logs_system_index (system_name),
    INDEX integration_logs_direction_index (direction),
    INDEX integration_logs_status_index (status),
    INDEX integration_logs_reference_index (reference_table, reference_id)
);

-- system_name examples:
-- EvaTrack, TrackingAid, ExpoPush, PAGASA, OpenMeteo
--
-- direction values:
-- inbound, outbound

-- ============================================================
-- 7. Weather logs
-- Existing DB did not show a weather_logs table.
-- Weather should be fetched by Laravel, saved, then displayed by React.
-- ============================================================

CREATE TABLE weather_logs (
    weather_log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_id BIGINT UNSIGNED NULL,
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
    observed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX weather_logs_disaster_index (disaster_id),
    INDEX weather_logs_observed_at_index (observed_at)
);

-- Optional FK after approval:
-- ALTER TABLE weather_logs
--   ADD CONSTRAINT weather_logs_disaster_id_foreign
--     FOREIGN KEY (disaster_id) REFERENCES disaster_events(event_id) ON DELETE SET NULL;
