-- =====================================================================
-- Standardization & Demo Elimination Script for Resource Requests
-- System: RESQPERATION + TrackingAid Shared Database
-- Standard ID Format: RR-YYYY-XXXXXX
-- Active Event Reference: EVT-20260827-RTR1DM (Barangay Mambaling)
-- =====================================================================

USE resq_local;

-- 1. Standardize any existing REQ-2026-* IDs to canonical RR-2026-*
UPDATE resource_requests
SET request_id = CONCAT('RR-2026-', SUBSTRING(request_id, 10))
WHERE request_id LIKE 'REQ-2026-%';

-- 2. Populate missing request_source (eliminate NULL channels)
UPDATE resource_requests
SET request_source = CASE
    WHEN evacuation_center_id IS NOT NULL THEN 'evatrack'
    WHEN handled_by IS NOT NULL THEN 'field_team'
    ELSE 'hq_desk'
END
WHERE request_source IS NULL OR request_source = '';

-- 3. Populate missing source_reference with active disaster event
UPDATE resource_requests
SET source_reference = 'EVT-20260827-RTR1DM'
WHERE source_reference IS NULL OR source_reference = '';

-- 4. Delete old DEMO placeholders if they exist
DELETE FROM request_validations WHERE request_id LIKE 'RR-DEMO%';
DELETE FROM resource_requests WHERE request_id LIKE 'RR-DEMO%';

-- 5. Insert / Update the 3 realistic operational records based on live Mambaling centers
-- Scenario 1: EvaTrack Evacuation Center Request (Alaska Elementary School)
INSERT INTO resource_requests (
    request_id,
    request_source,
    source_reference,
    request_category,
    evacuation_center_id,
    requested_by,
    resource_type,
    item_name,
    quantity,
    unit,
    description,
    status_id,
    validation_status,
    validation_notes,
    validated_by_user_id,
    validated_at,
    released_for_tracking_at,
    tracking_reference,
    created_at,
    updated_at
) VALUES (
    'RR-2026-EVT901',
    'evatrack',
    'EVT-20260827-RTR1DM',
    'resource',
    'EC-2026-KOR6F7',
    'Camp Manager - Alaska Elementary',
    'Food & Water',
    'Family Food Packs (Assorted)',
    50,
    'packs',
    'Immediate relief supply augmentation for evacuated families at Alaska Elementary School.',
    3,
    'forwarded',
    'Verified with Evacuation Camp Manager. Authorized for immediate depot dispatch.',
    'USR-HQ-2024035500',
    NOW(),
    NOW(),
    'TA-20261028-013',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    request_source = VALUES(request_source),
    source_reference = VALUES(source_reference),
    evacuation_center_id = VALUES(evacuation_center_id),
    requested_by = VALUES(requested_by),
    resource_type = VALUES(resource_type),
    item_name = VALUES(item_name),
    quantity = VALUES(quantity),
    unit = VALUES(unit),
    description = VALUES(description),
    validation_status = VALUES(validation_status),
    tracking_reference = VALUES(tracking_reference),
    updated_at = NOW();

-- Scenario 2: Field Response Team Request (Mambaling Gymnasium Triage)
INSERT INTO resource_requests (
    request_id,
    request_source,
    source_reference,
    request_category,
    evacuation_center_id,
    requested_by,
    resource_type,
    item_name,
    quantity,
    unit,
    description,
    status_id,
    validation_status,
    validation_notes,
    validated_by_user_id,
    validated_at,
    released_for_tracking_at,
    tracking_reference,
    created_at,
    updated_at
) VALUES (
    'RR-2026-FLD902',
    'field_team',
    'EVT-20260827-RTR1DM',
    'resource',
    'EC-2026-BDAARS',
    'Alpha Response Team Lead',
    'Medical Supplies',
    'First Aid Trauma Kit',
    4,
    'kits',
    'Field medical team trauma pack resupply for onsite triage at Mambaling Gym.',
    3,
    'forwarded',
    'Emergency medical gear replenishment confirmed with team lead.',
    'USR-HQ-2024035500',
    NOW(),
    NOW(),
    'TA-20261028-014',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    request_source = VALUES(request_source),
    source_reference = VALUES(source_reference),
    evacuation_center_id = VALUES(evacuation_center_id),
    requested_by = VALUES(requested_by),
    resource_type = VALUES(resource_type),
    item_name = VALUES(item_name),
    quantity = VALUES(quantity),
    unit = VALUES(unit),
    description = VALUES(description),
    validation_status = VALUES(validation_status),
    tracking_reference = VALUES(tracking_reference),
    updated_at = NOW();

-- Scenario 3: HQ Command Desk Request (Mambaling Elementary School)
INSERT INTO resource_requests (
    request_id,
    request_source,
    source_reference,
    request_category,
    evacuation_center_id,
    requested_by,
    resource_type,
    item_name,
    quantity,
    unit,
    description,
    status_id,
    validation_status,
    validation_notes,
    validated_by_user_id,
    validated_at,
    released_for_tracking_at,
    tracking_reference,
    created_at,
    updated_at
) VALUES (
    'RR-2026-HQD903',
    'hq_desk',
    'EVT-20260827-RTR1DM',
    'resource',
    'EC-2026-9M2PAG',
    'HQ Operations Coordinator',
    'Emergency Equipment',
    'Emergency Blanket',
    30,
    'PCS',
    'Thermal emergency blanket allocation for vulnerable evacuees at Mambaling Elementary.',
    3,
    'forwarded',
    'Command desk dispatch approval for weather advisory preparedness.',
    'USR-HQ-2024035500',
    NOW(),
    NOW(),
    'TA-20261028-015',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    request_source = VALUES(request_source),
    source_reference = VALUES(source_reference),
    evacuation_center_id = VALUES(evacuation_center_id),
    requested_by = VALUES(requested_by),
    resource_type = VALUES(resource_type),
    item_name = VALUES(item_name),
    quantity = VALUES(quantity),
    unit = VALUES(unit),
    description = VALUES(description),
    validation_status = VALUES(validation_status),
    tracking_reference = VALUES(tracking_reference),
    updated_at = NOW();

-- 6. Insert Validation History Records
INSERT INTO request_validations (
    request_id,
    validation_status,
    validator_user_id,
    validation_notes,
    validated_at,
    created_at,
    updated_at
)
SELECT 'RR-2026-EVT901', 'forwarded', 'USR-HQ-2024035500', 'Verified with Evacuation Camp Manager. Authorized for immediate depot dispatch.', NOW(), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM request_validations WHERE request_id = 'RR-2026-EVT901' AND validation_status = 'forwarded');

INSERT INTO request_validations (
    request_id,
    validation_status,
    validator_user_id,
    validation_notes,
    validated_at,
    created_at,
    updated_at
)
SELECT 'RR-2026-FLD902', 'forwarded', 'USR-HQ-2024035500', 'Emergency medical gear replenishment confirmed with team lead.', NOW(), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM request_validations WHERE request_id = 'RR-2026-FLD902' AND validation_status = 'forwarded');

INSERT INTO request_validations (
    request_id,
    validation_status,
    validator_user_id,
    validation_notes,
    validated_at,
    created_at,
    updated_at
)
SELECT 'RR-2026-HQD903', 'forwarded', 'USR-HQ-2024035500', 'Command desk dispatch approval for weather advisory preparedness.', NOW(), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM request_validations WHERE request_id = 'RR-2026-HQD903' AND validation_status = 'forwarded');

-- 7. Sync with TrackingAid Shared Table (if trackingaid_db exists locally)
INSERT INTO trackingaid_db.resqperation_forwarded_requests (
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
    validated_by_user_id,
    forwarded_by_user_id,
    forwarded_by_name,
    forwarded_by_role,
    resqperation_status,
    payload_json,
    forwarded_at,
    created_at,
    updated_at
) VALUES 
(
    'TA-20261028-013',
    'RR-2026-EVT901',
    'EVT-20260827-RTR1DM',
    'evatrack',
    'EvaTrack',
    'resource',
    'Food & Water',
    'Family Food Packs (Assorted)',
    50,
    'packs',
    'High',
    'Alaska Elementary School',
    'Basketball Court, Tabada Street, Alumnos, Basak San Nicolas, Cebu City',
    'Camp Manager - Alaska Elementary',
    'Immediate relief supply augmentation for evacuated families at Alaska Elementary School.',
    'Verified with Evacuation Camp Manager. Authorized for immediate depot dispatch.',
    'USR-HQ-2024035500',
    'USR-HQ-2024035500',
    'Nina Kathleen',
    'admin',
    'forwarded',
    '{"unit":"packs","urgency":"High","quantity":50,"item_name":"Family Food Packs (Assorted)","area_label":"Alaska Elementary School"}',
    NOW(),
    NOW(),
    NOW()
),
(
    'TA-20261028-014',
    'RR-2026-FLD902',
    'EVT-20260827-RTR1DM',
    'field_team',
    'ResQperation',
    'resource',
    'Medical Supplies',
    'First Aid Trauma Kit',
    4,
    'kits',
    'High',
    'Mambaling Barangay Gymnasium / Covered Court',
    'Barangay Mambaling Gym, Cebu City',
    'Alpha Response Team Lead',
    'Field medical team trauma pack resupply for onsite triage at Mambaling Gym.',
    'Emergency medical gear replenishment confirmed with team lead.',
    'USR-HQ-2024035500',
    'USR-HQ-2024035500',
    'Nina Kathleen',
    'admin',
    'forwarded',
    '{"unit":"kits","urgency":"High","quantity":4,"item_name":"First Aid Trauma Kit","area_label":"Mambaling Barangay Gymnasium"}',
    NOW(),
    NOW(),
    NOW()
),
(
    'TA-20261028-015',
    'RR-2026-HQD903',
    'EVT-20260827-RTR1DM',
    'hq_desk',
    'ResQperation',
    'resource',
    'Emergency Equipment',
    'Emergency Blanket',
    30,
    'PCS',
    'Medium',
    'Mambaling Elementary School',
    'Mambaling Flyover, Maria Gochan Subdivision, Mambaling, Cebu City',
    'HQ Operations Coordinator',
    'Thermal emergency blanket allocation for vulnerable evacuees at Mambaling Elementary.',
    'Command desk dispatch approval for weather advisory preparedness.',
    'USR-HQ-2024035500',
    'USR-HQ-2024035500',
    'Nina Kathleen',
    'admin',
    'forwarded',
    '{"unit":"PCS","urgency":"Medium","quantity":30,"item_name":"Emergency Blanket","area_label":"Mambaling Elementary School"}',
    NOW(),
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    resqperation_status = VALUES(resqperation_status),
    quantity = VALUES(quantity),
    updated_at = NOW();
