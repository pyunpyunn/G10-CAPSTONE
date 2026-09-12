-- TrackingAid database schema changes only.
-- Run this while connected to the actual TrackingAid main database.
-- Do not run this from a server-level connection.
-- This script does not create a database, drop tables, or insert demo requests.

CREATE TABLE IF NOT EXISTS resqperation_forwarded_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_reference VARCHAR(120) NOT NULL,
    resqperation_request_id VARCHAR(120) NOT NULL,
    source_reference VARCHAR(120) NULL,
    request_source VARCHAR(80) NULL,
    source_system VARCHAR(80) NOT NULL DEFAULT 'ResQperation',
    request_category VARCHAR(80) NULL,
    resource_type VARCHAR(150) NULL,
    item_name VARCHAR(150) NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit VARCHAR(50) NULL,
    urgency VARCHAR(80) NULL,
    area_label VARCHAR(255) NULL,
    area_note TEXT NULL,
    requested_by VARCHAR(255) NULL,
    description TEXT NULL,
    validation_notes TEXT NULL,
    validated_by_user_id VARCHAR(120) NULL,
    forwarded_by_user_id VARCHAR(120) NULL,
    forwarded_by_name VARCHAR(255) NULL,
    forwarded_by_role VARCHAR(80) NULL,
    resqperation_status VARCHAR(80) NOT NULL DEFAULT 'forwarded',
    payload_json JSON NULL,
    forwarded_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tracking_reference (tracking_reference),
    KEY idx_resqperation_request_id (resqperation_request_id),
    KEY idx_source_reference (source_reference),
    KEY idx_resqperation_status (resqperation_status),
    KEY idx_forwarded_at (forwarded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compatibility alterations for an older handoff table.
SET @table_name = 'resqperation_forwarded_requests';

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'request_source'),
    'SELECT 1',
    'ALTER TABLE resqperation_forwarded_requests ADD COLUMN request_source VARCHAR(80) NULL AFTER source_reference'
);
PREPARE add_request_source FROM @sql;
EXECUTE add_request_source;
DEALLOCATE PREPARE add_request_source;

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'forwarded_by_user_id'),
    'SELECT 1',
    'ALTER TABLE resqperation_forwarded_requests ADD COLUMN forwarded_by_user_id VARCHAR(120) NULL AFTER validated_by_user_id'
);
PREPARE add_forwarded_by_user_id FROM @sql;
EXECUTE add_forwarded_by_user_id;
DEALLOCATE PREPARE add_forwarded_by_user_id;

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'forwarded_by_name'),
    'SELECT 1',
    'ALTER TABLE resqperation_forwarded_requests ADD COLUMN forwarded_by_name VARCHAR(255) NULL AFTER forwarded_by_user_id'
);
PREPARE add_forwarded_by_name FROM @sql;
EXECUTE add_forwarded_by_name;
DEALLOCATE PREPARE add_forwarded_by_name;

SET @sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'forwarded_by_role'),
    'SELECT 1',
    'ALTER TABLE resqperation_forwarded_requests ADD COLUMN forwarded_by_role VARCHAR(80) NULL AFTER forwarded_by_name'
);
PREPARE add_forwarded_by_role FROM @sql;
EXECUTE add_forwarded_by_role;
DEALLOCATE PREPARE add_forwarded_by_role;
