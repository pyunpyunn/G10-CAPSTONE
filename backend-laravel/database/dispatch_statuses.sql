-- Run once to create the lookup; this does not insert responder assignments.
CREATE TABLE IF NOT EXISTS dispatch_statuses (
 status_key VARCHAR(50) PRIMARY KEY,
 status_code VARCHAR(20) NOT NULL UNIQUE,
 label VARCHAR(100) NOT NULL,
 is_open BOOLEAN NOT NULL DEFAULT TRUE,
 allow_create BOOLEAN NOT NULL DEFAULT FALSE,
 allow_mobile BOOLEAN NOT NULL DEFAULT FALSE,
 dashboard_group VARCHAR(30) NOT NULL DEFAULT 'other',
 is_alias BOOLEAN NOT NULL DEFAULT FALSE,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0
);
INSERT IGNORE INTO dispatch_statuses (status_key, status_code, label, is_open, allow_create, allow_mobile, dashboard_group, is_alias, is_active, sort_order) VALUES
('dispatched', 'DSP-001', 'Dispatched', 1, 1, 0, 'dispatched', 0, 1, 1),
('accepted', 'DSP-002', 'Accepted', 1, 1, 1, 'dispatched', 0, 1, 2),
('en_route', 'DSP-003', 'En route', 1, 1, 1, 'en-route', 0, 1, 3),
('on_scene', 'DSP-004', 'On-scene', 1, 1, 1, 'on-scene', 0, 1, 4),
('onscene', 'DSP-005', 'On-scene (legacy alias)', 1, 1, 0, 'on-scene', 1, 1, 5),
('returning', 'DSP-006', 'Returning', 1, 1, 0, 'other', 0, 1, 6),
('completed', 'DSP-007', 'Completed', 0, 0, 1, 'completed', 0, 1, 7),
('cancelled', 'DSP-008', 'Cancelled', 0, 0, 1, 'other', 0, 1, 8);
