-- RESQPERATION shared database read-only audit
-- Date: 2026-06-25
-- This script contains SELECT/SHOW statements only. It does not change data.

SELECT DATABASE() AS active_database, NOW() AS database_time;

SHOW COLUMNS FROM device_tokens;
SHOW COLUMNS FROM genders;

-- Required reference-table row counts.
SELECT 'roles' AS table_name, COUNT(*) AS row_count FROM roles
UNION ALL SELECT 'genders', COUNT(*) FROM genders
UNION ALL SELECT 'relationships', COUNT(*) FROM relationships
UNION ALL SELECT 'household_statuses', COUNT(*) FROM household_statuses
UNION ALL SELECT 'disaster_types', COUNT(*) FROM disaster_types
UNION ALL SELECT 'severity_levels', COUNT(*) FROM severity_levels
UNION ALL SELECT 'urgency_levels', COUNT(*) FROM urgency_levels
UNION ALL SELECT 'field_report_categories', COUNT(*) FROM field_report_categories
UNION ALL SELECT 'notification_channels', COUNT(*) FROM notification_channels
UNION ALL SELECT 'notification_statuses', COUNT(*) FROM notification_statuses
UNION ALL SELECT 'resource_request_status', COUNT(*) FROM resource_request_status
UNION ALL SELECT 'rescue_teams', COUNT(*) FROM rescue_teams;

-- Duplicate gender keys and labels.
SELECT
    LOWER(TRIM(gender_key)) AS normalized_key,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(gender_id ORDER BY gender_id) AS gender_ids
FROM genders
GROUP BY LOWER(TRIM(gender_key))
HAVING COUNT(*) > 1;

SELECT
    LOWER(TRIM(gender_label)) AS normalized_label,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(gender_id ORDER BY gender_id) AS gender_ids
FROM genders
GROUP BY LOWER(TRIM(gender_label))
HAVING COUNT(*) > 1;

-- Other important duplicate business keys.
SELECT LOWER(TRIM(role_key)) AS normalized_value, COUNT(*) AS duplicate_count
FROM roles GROUP BY LOWER(TRIM(role_key)) HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(status_key)) AS normalized_value, COUNT(*) AS duplicate_count
FROM household_statuses GROUP BY LOWER(TRIM(status_key)) HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(type_code)) AS normalized_value, COUNT(*) AS duplicate_count
FROM disaster_types GROUP BY LOWER(TRIM(type_code)) HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(severity_key)) AS normalized_value, COUNT(*) AS duplicate_count
FROM severity_levels GROUP BY LOWER(TRIM(severity_key)) HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(team_code)) AS normalized_value, COUNT(*) AS duplicate_count
FROM rescue_teams GROUP BY LOWER(TRIM(team_code)) HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(username)) AS normalized_value, COUNT(*) AS duplicate_count
FROM users
WHERE username IS NOT NULL AND TRIM(username) <> ''
GROUP BY LOWER(TRIM(username))
HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(responder_code)) AS normalized_value, COUNT(*) AS duplicate_count
FROM responders
WHERE responder_code IS NOT NULL AND TRIM(responder_code) <> ''
GROUP BY LOWER(TRIM(responder_code))
HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(household_code)) AS normalized_value, COUNT(*) AS duplicate_count
FROM households
WHERE household_code IS NOT NULL AND TRIM(household_code) <> ''
GROUP BY LOWER(TRIM(household_code))
HAVING COUNT(*) > 1;

-- Find foreign keys that reference the gender lookup before any cleanup.
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME = 'genders';

-- General device registration count. Run the detailed token query from the
-- audit document after confirming the exact columns with SHOW COLUMNS.
SELECT COUNT(*) AS device_registration_rows FROM device_tokens;
