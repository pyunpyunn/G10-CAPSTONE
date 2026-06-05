-- RESQPERATION G10 - Barangay Profile / Deployment Settings review
-- Date prepared: 2026-06-03
-- Prepared for review only. Do not run this script until the DB member approves it.
--
-- Why this is needed:
-- RESQPERATION clients are barangays. The system must know which barangay
-- the HQ/Admin deployment is currently serving so dashboard labels, weather,
-- mapping, household filtering, broadcast scope, and reports all focus on the
-- same official barangay.
--
-- Existing DB tables already found:
-- barangays
-- addresses
-- puroks
--
-- Recommended approach:
-- Reuse barangays.barangay_id. Do not duplicate the barangays master list.
-- Add one active profile table that stores RESQPERATION deployment details
-- such as display name, map center, weather coordinates, and official contact.

CREATE TABLE IF NOT EXISTS barangay_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_id INT NULL,
    display_name VARCHAR(150) NOT NULL,
    city_name VARCHAR(120) NULL,
    province_name VARCHAR(120) NULL,
    office_address VARCHAR(255) NULL,
    contact_number VARCHAR(50) NULL,
    email VARCHAR(120) NULL,
    center_latitude DECIMAL(10, 7) NOT NULL,
    center_longitude DECIMAL(10, 7) NOT NULL,
    map_zoom TINYINT UNSIGNED NOT NULL DEFAULT 16,
    map_bounds_json JSON NULL,
    weather_location_name VARCHAR(150) NULL,
    weather_latitude DECIMAL(10, 7) NULL,
    weather_longitude DECIMAL(10, 7) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    configured_by_user_id VARCHAR(50) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_barangay_profiles_barangay (barangay_id),
    INDEX idx_barangay_profiles_active (is_active),
    CONSTRAINT fk_barangay_profiles_barangay
        FOREIGN KEY (barangay_id)
        REFERENCES barangays(barangay_id)
        ON DELETE SET NULL
);

-- Suggested rule for the backend:
-- Only one row should have is_active = 1.
-- When HQ/Admin registers or changes the active barangay, set all other rows
-- to is_active = 0 inside a database transaction.

-- Suggested starter row if the client is Barangay Guadalupe:
-- INSERT INTO barangay_profiles (
--     barangay_id,
--     display_name,
--     city_name,
--     province_name,
--     center_latitude,
--     center_longitude,
--     map_zoom,
--     weather_location_name,
--     weather_latitude,
--     weather_longitude,
--     is_active
-- ) VALUES (
--     NULL,
--     'Barangay Guadalupe',
--     'Cebu City',
--     'Cebu',
--     10.3157000,
--     123.8854000,
--     16,
--     'Barangay Guadalupe',
--     10.3157000,
--     123.8854000,
--     1
-- );

