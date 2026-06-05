-- RESQPERATION G10 - Disaster Broadcasting metadata review
-- Date prepared: 2026-06-03
-- Applied to the shared DB on 2026-06-03 using guarded column checks.
-- This file is kept as the review record for the approved additive change.
--
-- Why this is needed:
-- The existing disaster_broadcasts table can already save the broadcast title,
-- message, event, severity, scope, channel, and sent time.
-- However, the prototype and requirements also need:
--   1. multiple directly affected puroks with priority levels,
--   2. selected household mobile status buttons,
--   3. a clear target area label for the recipient explanation,
--   4. optional push-notification delivery status for the future ExpoPush step.
--
-- Current workaround in v1:
-- The backend saves compact metadata in disaster_broadcasts.allowed_statuses.
-- That works for a small test broadcast but is not ideal because the column is VARCHAR(255).
--
-- Recommended additive update:
-- Keep the existing table and add nullable columns only.
-- This does not delete or rename any existing table or column.

ALTER TABLE disaster_broadcasts
  ADD COLUMN target_area_label VARCHAR(150) NULL AFTER target_area_id,
  ADD COLUMN direct_impact_puroks_json JSON NULL AFTER target_area_label,
  ADD COLUMN allowed_statuses_json JSON NULL AFTER allowed_statuses,
  ADD COLUMN recipient_count INT NULL AFTER channel,
  ADD COLUMN push_status VARCHAR(50) NULL AFTER recipient_count;

-- Suggested meanings:
-- target_area_label: example "Barangay-wide" or "Purok 3, Purok 4"
-- direct_impact_puroks_json: example [{"name":"Purok 3","priority":"critical"}]
-- allowed_statuses_json: example ["safe","evacuated","need_help","unsafe"]
-- recipient_count: estimated recipients saved when the broadcast is created
-- push_status: pending_push, sent, failed, cancelled
