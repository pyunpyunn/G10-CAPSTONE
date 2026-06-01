-- SUPERSEDED FOR LIVE DB REVIEW:
-- Use 2026_06_01_g10_existing_db_gap_review.sql before changing the shared DB.
--
-- This file is a conceptual clean schema based on the development documents.
-- The actual shared MySQL database already has many tables and uses custom
-- primary keys such as users.user_id, roles.role_id, and disaster_events.event_id.
-- Do not run this file against the current shared database.
--
-- RESQPERATION G10 schema proposal
-- Date: 2026-06-01
-- Purpose: review-only SQL for the shared MySQL database.
--
-- DO NOT RUN THIS SCRIPT YET.
-- Review table names, columns, and relationships first with the team/instructor.
-- The current Laravel project already has default tables such as users,
-- password_reset_tokens, sessions, cache, jobs, and personal_access_tokens.
--
-- Naming used here follows the current Laravel project:
-- - snake_case plural tables
-- - singular _id foreign keys
-- - unprefixed tables such as users, roles, households
--
-- If the shared database requires a project prefix, rename every new table
-- consistently before converting this proposal into Laravel migrations.

-- ============================================================
-- 1. Roles and users
-- ============================================================

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Existing users table should be altered after approval.
-- Keep one role_id per user for beginner-friendly role checks.
-- Do not use public registration for rescuer or household accounts.

ALTER TABLE users
    ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN username VARCHAR(80) NULL UNIQUE AFTER name,
    ADD COLUMN mobile_number VARCHAR(30) NULL AFTER email,
    ADD COLUMN account_status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER mobile_number,
    ADD COLUMN last_login_at TIMESTAMP NULL AFTER account_status,
    ADD CONSTRAINT users_role_id_foreign
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE SET NULL;

INSERT INTO roles (name, display_name, created_at, updated_at) VALUES
('admin', 'Admin', NOW(), NOW()),
('hq_dispatcher', 'HQ Dispatcher', NOW(), NOW()),
('rescuer', 'Rescuer', NOW(), NOW()),
('household', 'Household', NOW(), NOW());

-- Optional advanced alternative:
-- If the instructor requires many roles per user, use role_user instead of users.role_id.
-- For version 1, role_id is simpler and easier to defend.
--
-- CREATE TABLE role_user (
--     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--     role_id BIGINT UNSIGNED NOT NULL,
--     user_id BIGINT UNSIGNED NOT NULL,
--     created_at TIMESTAMP NULL,
--     updated_at TIMESTAMP NULL,
--     UNIQUE KEY role_user_unique (role_id, user_id),
--     CONSTRAINT role_user_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
--     CONSTRAINT role_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
-- );

-- ============================================================
-- 2. Disaster events and broadcasts
-- ============================================================

CREATE TABLE disaster_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_code VARCHAR(50) NOT NULL UNIQUE,
    disaster_type VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    alert_level VARCHAR(50) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    affected_puroks JSON NULL,
    declared_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    declared_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX disaster_events_status_index (status),
    INDEX disaster_events_disaster_type_index (disaster_type),
    INDEX disaster_events_declared_at_index (declared_at),
    CONSTRAINT disaster_events_declared_by_foreign
        FOREIGN KEY (declared_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE broadcast_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_event_id BIGINT UNSIGNED NULL,
    broadcast_type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NULL,
    message TEXT NOT NULL,
    recipient_scope VARCHAR(50) NOT NULL,
    affected_puroks JSON NULL,
    priority VARCHAR(30) NOT NULL DEFAULT 'normal',
    sent_at TIMESTAMP NULL,
    sent_by BIGINT UNSIGNED NULL,
    push_status VARCHAR(30) NOT NULL DEFAULT 'not_sent',
    push_tokens_targeted INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX broadcast_logs_event_index (disaster_event_id),
    INDEX broadcast_logs_sent_at_index (sent_at),
    CONSTRAINT broadcast_logs_disaster_event_id_foreign
        FOREIGN KEY (disaster_event_id) REFERENCES disaster_events(id)
        ON DELETE SET NULL,
    CONSTRAINT broadcast_logs_sent_by_foreign
        FOREIGN KEY (sent_by) REFERENCES users(id)
        ON DELETE SET NULL
);

-- ============================================================
-- 3. Weather
-- ============================================================

CREATE TABLE weather_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_event_id BIGINT UNSIGNED NULL,
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
    INDEX weather_logs_event_index (disaster_event_id),
    INDEX weather_logs_observed_at_index (observed_at),
    CONSTRAINT weather_logs_disaster_event_id_foreign
        FOREIGN KEY (disaster_event_id) REFERENCES disaster_events(id)
        ON DELETE SET NULL
);

-- ============================================================
-- 4. Households, members, devices, geotags, and status logs
-- ============================================================

CREATE TABLE households (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_code VARCHAR(50) NOT NULL UNIQUE,
    account_user_id BIGINT UNSIGNED NULL,
    household_name VARCHAR(150) NOT NULL,
    head_name VARCHAR(150) NULL,
    purok VARCHAR(80) NULL,
    address VARCHAR(255) NULL,
    member_count INT UNSIGNED NOT NULL DEFAULT 0,
    status_latest VARCHAR(30) NOT NULL DEFAULT 'unchecked',
    risk_level VARCHAR(30) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    last_status_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX households_account_user_index (account_user_id),
    INDEX households_purok_index (purok),
    INDEX households_status_latest_index (status_latest),
    INDEX households_last_status_at_index (last_status_at),
    CONSTRAINT households_account_user_id_foreign
        FOREIGN KEY (account_user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE household_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(80) NULL,
    age INT UNSIGNED NULL,
    sex VARCHAR(20) NULL,
    is_vulnerable TINYINT(1) NOT NULL DEFAULT 0,
    vulnerability_note VARCHAR(255) NULL,
    mobile_number VARCHAR(30) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX household_members_household_index (household_id),
    CONSTRAINT household_members_household_id_foreign
        FOREIGN KEY (household_id) REFERENCES households(id)
        ON DELETE CASCADE
);

CREATE TABLE household_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    household_member_id BIGINT UNSIGNED NULL,
    account_user_id BIGINT UNSIGNED NULL,
    device_label VARCHAR(100) NULL,
    device_identifier VARCHAR(150) NULL,
    platform VARCHAR(30) NULL,
    expo_push_token VARCHAR(255) NULL,
    battery_level TINYINT UNSIGNED NULL,
    location_permission_status VARCHAR(30) NOT NULL DEFAULT 'unknown',
    last_latitude DECIMAL(10,7) NULL,
    last_longitude DECIMAL(10,7) NULL,
    last_location_at TIMESTAMP NULL,
    last_seen_at TIMESTAMP NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX household_devices_household_index (household_id),
    INDEX household_devices_member_index (household_member_id),
    INDEX household_devices_user_index (account_user_id),
    INDEX household_devices_battery_index (battery_level),
    INDEX household_devices_last_seen_index (last_seen_at),
    CONSTRAINT household_devices_household_id_foreign
        FOREIGN KEY (household_id) REFERENCES households(id)
        ON DELETE CASCADE,
    CONSTRAINT household_devices_household_member_id_foreign
        FOREIGN KEY (household_member_id) REFERENCES household_members(id)
        ON DELETE SET NULL,
    CONSTRAINT household_devices_account_user_id_foreign
        FOREIGN KEY (account_user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE household_geotags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id BIGINT UNSIGNED NOT NULL,
    household_device_id BIGINT UNSIGNED NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy_meters DECIMAL(8,2) NULL,
    captured_by BIGINT UNSIGNED NULL,
    captured_at TIMESTAMP NULL,
    source_type VARCHAR(40) NOT NULL DEFAULT 'household_mobile',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX household_geotags_household_index (household_id),
    INDEX household_geotags_captured_at_index (captured_at),
    CONSTRAINT household_geotags_household_id_foreign
        FOREIGN KEY (household_id) REFERENCES households(id)
        ON DELETE CASCADE,
    CONSTRAINT household_geotags_household_device_id_foreign
        FOREIGN KEY (household_device_id) REFERENCES household_devices(id)
        ON DELETE SET NULL,
    CONSTRAINT household_geotags_captured_by_foreign
        FOREIGN KEY (captured_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE household_status_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_event_id BIGINT UNSIGNED NULL,
    household_id BIGINT UNSIGNED NOT NULL,
    household_device_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    submitted_by BIGINT UNSIGNED NULL,
    submitted_at TIMESTAMP NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    battery_level TINYINT UNSIGNED NULL,
    note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX household_status_logs_event_index (disaster_event_id),
    INDEX household_status_logs_household_index (household_id),
    INDEX household_status_logs_status_index (status),
    INDEX household_status_logs_submitted_at_index (submitted_at),
    CONSTRAINT household_status_logs_disaster_event_id_foreign
        FOREIGN KEY (disaster_event_id) REFERENCES disaster_events(id)
        ON DELETE SET NULL,
    CONSTRAINT household_status_logs_household_id_foreign
        FOREIGN KEY (household_id) REFERENCES households(id)
        ON DELETE CASCADE,
    CONSTRAINT household_status_logs_household_device_id_foreign
        FOREIGN KEY (household_device_id) REFERENCES household_devices(id)
        ON DELETE SET NULL,
    CONSTRAINT household_status_logs_submitted_by_foreign
        FOREIGN KEY (submitted_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT household_status_logs_reviewed_by_foreign
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL
);

-- Allowed source_type values should be enforced in Laravel validation:
-- household_mobile, rescuer_field_report, external_import.
-- Do not allow hq_manual for household status.

-- ============================================================
-- 5. Evacuation sites, rescue teams, rescuers, and dispatch
-- ============================================================

CREATE TABLE evacuation_sites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    purok VARCHAR(80) NULL,
    address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    capacity INT UNSIGNED NULL,
    current_occupancy INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    contact_person VARCHAR(150) NULL,
    contact_number VARCHAR(30) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX evacuation_sites_purok_index (purok),
    INDEX evacuation_sites_status_index (status)
);

CREATE TABLE rescue_teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    team_type VARCHAR(80) NULL,
    assigned_purok VARCHAR(80) NULL,
    leader_user_id BIGINT UNSIGNED NULL,
    member_count INT UNSIGNED NOT NULL DEFAULT 0,
    duty_status VARCHAR(30) NOT NULL DEFAULT 'standby',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX rescue_teams_duty_status_index (duty_status),
    INDEX rescue_teams_assigned_purok_index (assigned_purok),
    CONSTRAINT rescue_teams_leader_user_id_foreign
        FOREIGN KEY (leader_user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE rescuer_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    rescue_team_id BIGINT UNSIGNED NULL,
    position_title VARCHAR(100) NULL,
    skills TEXT NULL,
    training_notes TEXT NULL,
    certification_reference VARCHAR(150) NULL,
    equipment_notes TEXT NULL,
    emergency_contact_name VARCHAR(150) NULL,
    emergency_contact_number VARCHAR(30) NULL,
    duty_status VARCHAR(30) NOT NULL DEFAULT 'off_duty',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY rescuer_profiles_user_unique (user_id),
    INDEX rescuer_profiles_team_index (rescue_team_id),
    INDEX rescuer_profiles_duty_status_index (duty_status),
    CONSTRAINT rescuer_profiles_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT rescuer_profiles_rescue_team_id_foreign
        FOREIGN KEY (rescue_team_id) REFERENCES rescue_teams(id)
        ON DELETE SET NULL
);

CREATE TABLE rescue_dispatches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_event_id BIGINT UNSIGNED NULL,
    rescue_team_id BIGINT UNSIGNED NULL,
    assigned_area VARCHAR(150) NULL,
    assigned_households_count INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'standby',
    dispatched_at TIMESTAMP NULL,
    arrived_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    safe_count INT UNSIGNED NOT NULL DEFAULT 0,
    evacuated_count INT UNSIGNED NOT NULL DEFAULT 0,
    unsafe_count INT UNSIGNED NOT NULL DEFAULT 0,
    injured_count INT UNSIGNED NOT NULL DEFAULT 0,
    missing_count INT UNSIGNED NOT NULL DEFAULT 0,
    current_latitude DECIMAL(10,7) NULL,
    current_longitude DECIMAL(10,7) NULL,
    location_updated_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX rescue_dispatches_event_index (disaster_event_id),
    INDEX rescue_dispatches_team_index (rescue_team_id),
    INDEX rescue_dispatches_status_index (status),
    INDEX rescue_dispatches_location_updated_index (location_updated_at),
    CONSTRAINT rescue_dispatches_disaster_event_id_foreign
        FOREIGN KEY (disaster_event_id) REFERENCES disaster_events(id)
        ON DELETE SET NULL,
    CONSTRAINT rescue_dispatches_rescue_team_id_foreign
        FOREIGN KEY (rescue_team_id) REFERENCES rescue_teams(id)
        ON DELETE SET NULL,
    CONSTRAINT rescue_dispatches_created_by_foreign
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE dispatch_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rescue_dispatch_id BIGINT UNSIGNED NOT NULL,
    evacuation_site_id BIGINT UNSIGNED NULL,
    route_source VARCHAR(50) NULL,
    start_latitude DECIMAL(10,7) NULL,
    start_longitude DECIMAL(10,7) NULL,
    end_latitude DECIMAL(10,7) NULL,
    end_longitude DECIMAL(10,7) NULL,
    distance_km DECIMAL(8,2) NULL,
    duration_minutes INT UNSIGNED NULL,
    route_geojson JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX dispatch_routes_dispatch_index (rescue_dispatch_id),
    CONSTRAINT dispatch_routes_rescue_dispatch_id_foreign
        FOREIGN KEY (rescue_dispatch_id) REFERENCES rescue_dispatches(id)
        ON DELETE CASCADE,
    CONSTRAINT dispatch_routes_evacuation_site_id_foreign
        FOREIGN KEY (evacuation_site_id) REFERENCES evacuation_sites(id)
        ON DELETE SET NULL
);

-- ============================================================
-- 6. Resources, requests, validation, and integrations
-- ============================================================

CREATE TABLE resource_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NOT NULL,
    unit VARCHAR(50) NULL,
    available_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    source_system VARCHAR(80) NULL,
    last_synced_at TIMESTAMP NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX resource_items_category_index (category),
    INDEX resource_items_status_index (status)
);

CREATE TABLE resource_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_event_id BIGINT UNSIGNED NULL,
    request_code VARCHAR(50) NOT NULL UNIQUE,
    source_type VARCHAR(40) NOT NULL DEFAULT 'manual',
    source_system VARCHAR(80) NULL,
    external_request_id VARCHAR(100) NULL,
    request_type VARCHAR(40) NOT NULL,
    category VARCHAR(80) NOT NULL,
    item_name VARCHAR(150) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit VARCHAR(50) NULL,
    requester_name VARCHAR(150) NOT NULL,
    requester_contact VARCHAR(30) NULL,
    area VARCHAR(150) NULL,
    urgency VARCHAR(30) NOT NULL DEFAULT 'normal',
    status VARCHAR(30) NOT NULL DEFAULT 'needs_validation',
    validation_note TEXT NULL,
    forwarded_to_system VARCHAR(80) NULL,
    forwarded_at TIMESTAMP NULL,
    trackingaid_reference VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX resource_requests_event_index (disaster_event_id),
    INDEX resource_requests_status_index (status),
    INDEX resource_requests_source_system_index (source_system),
    INDEX resource_requests_urgency_index (urgency),
    INDEX resource_requests_created_at_index (created_at),
    CONSTRAINT resource_requests_disaster_event_id_foreign
        FOREIGN KEY (disaster_event_id) REFERENCES disaster_events(id)
        ON DELETE SET NULL,
    CONSTRAINT resource_requests_created_by_foreign
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE request_validations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_request_id BIGINT UNSIGNED NOT NULL,
    validation_status VARCHAR(30) NOT NULL,
    validator_id BIGINT UNSIGNED NULL,
    validation_notes TEXT NULL,
    missing_information TEXT NULL,
    duplicate_request_id BIGINT UNSIGNED NULL,
    validated_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX request_validations_request_index (resource_request_id),
    INDEX request_validations_status_index (validation_status),
    CONSTRAINT request_validations_resource_request_id_foreign
        FOREIGN KEY (resource_request_id) REFERENCES resource_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT request_validations_validator_id_foreign
        FOREIGN KEY (validator_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT request_validations_duplicate_request_id_foreign
        FOREIGN KEY (duplicate_request_id) REFERENCES resource_requests(id)
        ON DELETE SET NULL
);

CREATE TABLE integration_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    system_name VARCHAR(80) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    module VARCHAR(80) NOT NULL,
    reference_type VARCHAR(80) NULL,
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
    INDEX integration_logs_reference_index (reference_type, reference_id)
);

-- ============================================================
-- 7. Situation reports and audit logs
-- ============================================================

CREATE TABLE situation_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disaster_event_id BIGINT UNSIGNED NULL,
    report_code VARCHAR(50) NOT NULL UNIQUE,
    generated_by BIGINT UNSIGNED NULL,
    generated_at TIMESTAMP NULL,
    summary TEXT NULL,
    household_summary_json JSON NULL,
    dispatch_summary_json JSON NULL,
    resources_summary_json JSON NULL,
    weather_summary_json JSON NULL,
    casualties_count INT UNSIGNED NOT NULL DEFAULT 0,
    injured_count INT UNSIGNED NOT NULL DEFAULT 0,
    missing_count INT UNSIGNED NOT NULL DEFAULT 0,
    property_damage TEXT NULL,
    pdf_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX situation_reports_event_index (disaster_event_id),
    INDEX situation_reports_generated_at_index (generated_at),
    CONSTRAINT situation_reports_disaster_event_id_foreign
        FOREIGN KEY (disaster_event_id) REFERENCES disaster_events(id)
        ON DELETE SET NULL,
    CONSTRAINT situation_reports_generated_by_foreign
        FOREIGN KEY (generated_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    module VARCHAR(80) NOT NULL,
    action VARCHAR(80) NOT NULL,
    record_type VARCHAR(80) NULL,
    record_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,
    INDEX audit_logs_user_index (user_id),
    INDEX audit_logs_module_action_index (module, action),
    INDEX audit_logs_record_index (record_type, record_id),
    INDEX audit_logs_created_at_index (created_at),
    CONSTRAINT audit_logs_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
);

-- ============================================================
-- 8. Validation values for Laravel Form Requests
-- ============================================================

-- Suggested status values to enforce in Laravel validation:
--
-- users.account_status:
-- active, inactive
--
-- disaster_events.status:
-- draft, active, closed, archived
--
-- households.status_latest and household_status_logs.status:
-- unchecked, safe, evacuated, unsafe, injured, missing
--
-- household_status_logs.source_type:
-- household_mobile, rescuer_field_report, external_import
--
-- household_devices.location_permission_status:
-- unknown, granted, denied, disabled
--
-- rescue_dispatches.status:
-- standby, dispatched, on_scene, completed, cancelled
--
-- resource_requests.status:
-- needs_validation, verified, returned, forwarded, fulfilled, cancelled
--
-- integration_logs.system_name examples:
-- EvaTrack, TrackingAid, ExpoPush, PAGASA, OpenMeteo
