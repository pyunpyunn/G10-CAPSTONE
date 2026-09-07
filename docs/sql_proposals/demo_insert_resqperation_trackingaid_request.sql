-- Local/demo request insert for RESQPERATION + TrackingAid.
-- Change only the two schema names when using the shared demo database.

SET @resq_schema = 'resq_local';
SET @tracking_schema = 'trackingaid';
SET @request_id = 'RR-DEMO-20260907-001';
SET @tracking_reference = 'TA-DEMO-20260907-001';
SET @now = NOW();

START TRANSACTION;

SET @urgency_id = (
    SELECT urgency_id
    FROM resq_local.urgency_levels
    WHERE urgency_key = 'high'
    LIMIT 1
);

SET @status_id = (
    SELECT status_id
    FROM resq_local.resource_request_status
    WHERE status_key = 'acknowledged'
    LIMIT 1
);

INSERT INTO resq_local.resource_requests (
    request_id,
    request_source,
    source_reference,
    request_category,
    requested_by,
    resource_type,
    item_name,
    quantity,
    unit,
    description,
    urgency_id,
    status_id,
    validation_status,
    validation_notes,
    validated_at,
    released_for_tracking_at,
    tracking_reference,
    created_at,
    updated_at
) VALUES (
    @request_id,
    'hq_desk',
    'DEMO-EVENT-20260907',
    'resource',
    'Demo HQ Coordinator',
    'Emergency food packs',
    'Rice and canned food pack',
    50,
    'packs',
    'Demo request for validating the RESQPERATION to TrackingAid handoff.',
    @urgency_id,
    @status_id,
    'forwarded',
    'Demo request approved and forwarded to TrackingAid.',
    @now,
    @now,
    @tracking_reference,
    @now,
    @now
)
ON DUPLICATE KEY UPDATE
    quantity = VALUES(quantity),
    unit = VALUES(unit),
    status_id = VALUES(status_id),
    validation_status = VALUES(validation_status),
    validation_notes = VALUES(validation_notes),
    validated_at = VALUES(validated_at),
    released_for_tracking_at = VALUES(released_for_tracking_at),
    tracking_reference = VALUES(tracking_reference),
    updated_at = VALUES(updated_at);

INSERT INTO resq_local.request_validations (
    request_id,
    validation_status,
    validation_notes,
    validated_at,
    created_at,
    updated_at
)
SELECT
    @request_id,
    'forwarded',
    'Demo request approved and forwarded to TrackingAid.',
    @now,
    @now,
    @now
WHERE NOT EXISTS (
    SELECT 1
    FROM resq_local.request_validations
    WHERE request_id = @request_id
      AND validation_status = 'forwarded'
);

INSERT INTO trackingaid.resqperation_forwarded_requests (
    tracking_reference,
    resqperation_request_id,
    source_reference,
    request_source,
    source_system,
    request_category,
    resource_type,
    item_name,
    quantity,
    unit,
    urgency,
    area_label,
    area_note,
    requested_by,
    description,
    validation_notes,
    resqperation_status,
    payload_json,
    forwarded_at,
    created_at,
    updated_at
) VALUES (
    @tracking_reference,
    @request_id,
    'DEMO-EVENT-20260907',
    'hq_desk',
    'ResQperation',
    'resource',
    'Emergency food packs',
    'Rice and canned food pack',
    50,
    'packs',
    'High',
    'Demo evacuation area',
    'Demo handoff record',
    'Demo HQ Coordinator',
    'Demo request for validating the RESQPERATION to TrackingAid handoff.',
    'Demo request approved and forwarded to TrackingAid.',
    'forwarded',
    JSON_OBJECT(
        'request_id', @request_id,
        'tracking_reference', @tracking_reference,
        'quantity', 50,
        'unit', 'packs',
        'status', 'forwarded'
    ),
    @now,
    @now,
    @now
)
ON DUPLICATE KEY UPDATE
    quantity = VALUES(quantity),
    unit = VALUES(unit),
    resqperation_status = VALUES(resqperation_status),
    validation_notes = VALUES(validation_notes),
    payload_json = VALUES(payload_json),
    forwarded_at = VALUES(forwarded_at),
    updated_at = VALUES(updated_at);

COMMIT;

SELECT request_id, validation_status, status_id, tracking_reference
FROM resq_local.resource_requests
WHERE request_id = @request_id;

SELECT tracking_reference, resqperation_request_id, resqperation_status, quantity, unit
FROM trackingaid.resqperation_forwarded_requests
WHERE tracking_reference = @tracking_reference;