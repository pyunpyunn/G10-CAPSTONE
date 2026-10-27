-- For a shared database where dispatch_statuses already exists (run once).
ALTER TABLE dispatch_statuses
    ADD COLUMN status_code VARCHAR(20) NULL,
    ADD UNIQUE KEY dispatch_statuses_status_code_unique (status_code);

UPDATE dispatch_statuses
SET status_code = CASE status_key
    WHEN 'dispatched' THEN 'DSP-001'
    WHEN 'accepted' THEN 'DSP-002'
    WHEN 'en_route' THEN 'DSP-003'
    WHEN 'on_scene' THEN 'DSP-004'
    WHEN 'onscene' THEN 'DSP-005'
    WHEN 'returning' THEN 'DSP-006'
    WHEN 'completed' THEN 'DSP-007'
    WHEN 'cancelled' THEN 'DSP-008'
    ELSE status_code
END
WHERE status_code IS NULL;

-- This requires every existing status to have a code.
ALTER TABLE dispatch_statuses MODIFY status_code VARCHAR(20) NOT NULL;

SELECT status_code, status_key, label FROM dispatch_statuses ORDER BY sort_order;
