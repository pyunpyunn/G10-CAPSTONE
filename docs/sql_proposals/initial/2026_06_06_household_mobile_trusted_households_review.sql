-- RESQPERATION G10 review-only SQL proposal
-- Purpose: household mobile trusted-household connections
-- Status: APPLIED to the shared DB on 2026-06-08 for household mobile trusted connections.

CREATE TABLE IF NOT EXISTS trusted_households (
    connection_id VARCHAR(80) NOT NULL,
    requesting_household_id VARCHAR(255) NOT NULL,
    trusted_household_id VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    validation_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    member_relationships JSON NULL,
    created_by_user_id VARCHAR(255) NULL,
    validated_by_user_id VARCHAR(255) NULL,
    validated_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (connection_id)
);

-- Suggested indexes after approval:
-- CREATE INDEX idx_trusted_households_requesting ON trusted_households (requesting_household_id);
-- CREATE INDEX idx_trusted_households_trusted ON trusted_households (trusted_household_id);
-- CREATE INDEX idx_trusted_households_status ON trusted_households (validation_status);

-- Notes:
-- 1. This table does not store the 4-digit trusted PIN.
-- 2. The trusted PIN is local to the household mobile device and should use Expo SecureStore.
-- 3. validation_status should normally be pending, validated, declined, or revoked.
