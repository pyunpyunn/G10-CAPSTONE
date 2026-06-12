-- RESQPERATION G10 - TrackingAid / MappingAid integration draft
-- Status: REVIEW ONLY. Do not run this on the shared database yet.
-- Purpose:
--   Keep a draft structure for forwarding validated resource requests from
--   RESQPERATION to the pending TrackingAid / MappingAid external system.
--
-- Notes:
--   1. The external group has not provided final DB/API details yet.
--   2. If they provide an API, this draft may not be needed.
--   3. If they provide a separate shared DB, use a separate Laravel DB
--      connection instead of mixing credentials with the SafeTrack/EvaTrack DB.

-- Draft table: validated request outbox for forwarding
CREATE TABLE IF NOT EXISTS trackingaid_resource_request_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    resqperation_request_id VARCHAR(80) NOT NULL,
    external_source_system VARCHAR(50) NULL,
    external_source_request_id VARCHAR(80) NULL,
    disaster_event_id VARCHAR(80) NULL,
    request_type VARCHAR(80) NOT NULL,
    requested_item VARCHAR(160) NOT NULL,
    quantity DECIMAL(10,2) NULL,
    unit VARCHAR(40) NULL,
    priority VARCHAR(40) NOT NULL DEFAULT 'normal',
    location_name VARCHAR(180) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    validation_status VARCHAR(40) NOT NULL DEFAULT 'ready_for_forwarding',
    forwarding_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    validated_by_user_id VARCHAR(80) NULL,
    validated_at DATETIME NULL,
    forwarded_at DATETIME NULL,
    trackingaid_reference_id VARCHAR(100) NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trackingaid_outbox_request (resqperation_request_id),
    KEY idx_trackingaid_outbox_status (forwarding_status),
    KEY idx_trackingaid_outbox_event (disaster_event_id)
);

-- Draft table: forwarding attempt log
CREATE TABLE IF NOT EXISTS trackingaid_resource_request_forwarding_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    outbox_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(40) NOT NULL,
    request_payload JSON NULL,
    response_payload JSON NULL,
    error_message TEXT NULL,
    attempted_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trackingaid_forward_log_outbox (outbox_id),
    KEY idx_trackingaid_forward_log_status (status)
);

-- Draft table: optional delivery status mirror if TrackingAid / MappingAid
-- returns delivery or tracking state later.
CREATE TABLE IF NOT EXISTS trackingaid_delivery_status_mirror (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trackingaid_reference_id VARCHAR(100) NOT NULL,
    resqperation_request_id VARCHAR(80) NOT NULL,
    delivery_status VARCHAR(60) NOT NULL,
    current_location_name VARCHAR(180) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    last_external_update_at DATETIME NULL,
    raw_status_payload JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trackingaid_delivery_reference (trackingaid_reference_id),
    KEY idx_trackingaid_delivery_request (resqperation_request_id),
    KEY idx_trackingaid_delivery_status (delivery_status)
);
