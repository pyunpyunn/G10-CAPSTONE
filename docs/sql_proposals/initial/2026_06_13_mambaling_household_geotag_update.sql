-- RESQPERATION Mambaling household geotag update proposal
-- Date prepared: 2026-06-13
-- Purpose:
--   Assign test GPS points to existing household accounts within Barangay Mambaling only.
--   This is for routing/dispatch UI testing while SafeTrack final household geotags are pending.
--
-- Important:
--   1. Review first before running on the shared DB.
--   2. Do not run if SafeTrack already supplied final household coordinates.
--   3. This assumes the approved shared schema already has:
--        households.household_id
--        households.address_id
--        addresses.address_id
--        addresses.barangay_name
--        addresses.purok_sitio
--        addresses.full_address
--        geotagged_locations.household_id
--        geotagged_locations.latitude
--        geotagged_locations.longitude
--        geotagged_locations.location_label
--        geotagged_locations.accuracy_m
--        geotagged_locations.geotag_source
--        geotagged_locations.is_verified
--        geotagged_locations.created_at
--
-- Sources for locality labels:
--   Local reports reference Sitio Lawis, Alaska Mambaling and Sitio Nava, Alaska Mambaling.
--   Mambaling Barangay Hall is publicly listed on M. Gochan Street.
--
-- Rollback:
--   If this was run by mistake, restore geotagged_locations and addresses from the latest DB backup.

START TRANSACTION;

CREATE TEMPORARY TABLE temp_mambaling_points (
    point_no INT NOT NULL PRIMARY KEY,
    sitio_label VARCHAR(100) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL
);

INSERT INTO temp_mambaling_points (point_no, sitio_label, latitude, longitude) VALUES
    (1, 'Sitio Lawis, Alaska Mambaling', 10.2929000, 123.8869000),
    (2, 'Sitio Nava, Alaska Mambaling', 10.2936000, 123.8883000),
    (3, 'Sitio Alaska, Mambaling', 10.2917000, 123.8890000),
    (4, 'M. Gochan Street, Mambaling', 10.2899000, 123.8886000),
    (5, 'Lower Mambaling', 10.2879000, 123.8899000),
    (6, 'Upper Mambaling', 10.2944000, 123.8912000),
    (7, 'C. Padilla Street, Mambaling', 10.2869000, 123.8911000),
    (8, 'F. Llamas Corridor, Mambaling', 10.2908000, 123.8926000);

CREATE TEMPORARY TABLE temp_household_point_assignment AS
SELECT
    ranked.household_id,
    points.sitio_label,
    points.latitude,
    points.longitude
FROM (
    SELECT
        h.household_id,
        ROW_NUMBER() OVER (ORDER BY h.household_id) AS row_no
    FROM households h
    WHERE h.household_id IS NOT NULL
      AND (h.deleted_at IS NULL OR h.deleted_at = '')
) ranked
JOIN temp_mambaling_points points
  ON points.point_no = ((ranked.row_no - 1) MOD 8) + 1;

UPDATE addresses a
JOIN households h ON h.address_id = a.address_id
JOIN temp_household_point_assignment assigned ON assigned.household_id = h.household_id
SET
    a.barangay_name = 'Mambaling',
    a.purok_sitio = assigned.sitio_label,
    a.full_address = CONCAT(assigned.sitio_label, ', Barangay Mambaling, Cebu City');

UPDATE geotagged_locations gl
JOIN temp_household_point_assignment assigned ON assigned.household_id = gl.household_id
SET
    gl.latitude = assigned.latitude,
    gl.longitude = assigned.longitude,
    gl.location_label = assigned.sitio_label,
    gl.accuracy_m = 12.00,
    gl.geotag_source = 'resqperation_test_seed',
    gl.is_verified = 0,
    gl.created_at = COALESCE(gl.created_at, NOW());

INSERT INTO geotagged_locations (
    household_id,
    latitude,
    longitude,
    location_label,
    accuracy_m,
    geotag_source,
    is_verified,
    created_at
)
SELECT
    assigned.household_id,
    assigned.latitude,
    assigned.longitude,
    assigned.sitio_label,
    12.00,
    'resqperation_test_seed',
    0,
    NOW()
FROM temp_household_point_assignment assigned
WHERE NOT EXISTS (
    SELECT 1
    FROM geotagged_locations gl
    WHERE gl.household_id = assigned.household_id
);

SELECT
    assigned.household_id,
    assigned.sitio_label,
    assigned.latitude,
    assigned.longitude
FROM temp_household_point_assignment assigned
ORDER BY assigned.household_id;

COMMIT;
