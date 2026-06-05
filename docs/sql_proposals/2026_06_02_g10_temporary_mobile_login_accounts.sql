-- RESQPERATION G10 temporary mobile login accounts
-- Date: 2026-06-02
--
-- Purpose:
-- Add one household mobile login and one rescuer mobile login for development testing.
--
-- IMPORTANT:
-- 1. Review before running on the shared DB.
-- 2. This script does not delete, drop, or rename anything.
-- 3. Login IDs follow the existing temporary HQ/Admin login pattern:
--    2024035500 = HQ/Admin
--    2024035501 = Household
--    2024035502 = Rescuer
-- 4. Temporary password for both accounts is: password

-- BCrypt hash for password: password
SET @temporary_password_hash = '$2y$12$gFbbSJ0cYpxt4tFob.18ruLDEIs79MpIYEK7YxzjqBJRlWZU1TM8a';

-- Optional household record for the household test login.
-- This keeps users.household_id connected to a recognizable temporary household.
INSERT INTO households (
    household_id,
    household_code,
    household_name,
    address_id,
    contact_number,
    emergency_contact,
    created_at,
    updated_at,
    deleted_at
)
SELECT
    'HH-2024035501',
    'HH-2024035501',
    'Temporary Household Account',
    NULL,
    '09170000001',
    '09170000002',
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM households WHERE household_id = 'HH-2024035501'
);

-- Household mobile user.
INSERT INTO users (
    user_id,
    first_name,
    last_name,
    username,
    email,
    password,
    role_id,
    contact_number,
    assigned_center_id,
    household_id,
    is_active,
    updated_at,
    created_at,
    deleted_at
)
SELECT
    'USR-HH-2024035501',
    'Temporary Household',
    'User',
    '2024035501',
    'household.temp@resqperation.local',
    @temporary_password_hash,
    roles.role_id,
    '09170000001',
    NULL,
    'HH-2024035501',
    1,
    NOW(),
    NOW(),
    NULL
FROM roles
WHERE roles.role_key = 'household_resident'
  AND NOT EXISTS (
      SELECT 1 FROM users WHERE username = '2024035501'
  )
LIMIT 1;

-- Rescuer mobile user.
INSERT INTO users (
    user_id,
    first_name,
    last_name,
    username,
    email,
    password,
    role_id,
    contact_number,
    assigned_center_id,
    household_id,
    is_active,
    updated_at,
    created_at,
    deleted_at
)
SELECT
    'USR-RSC-2024035502',
    'Temporary Rescuer',
    'User',
    '2024035502',
    'rescuer.temp@resqperation.local',
    @temporary_password_hash,
    roles.role_id,
    '09170000003',
    NULL,
    NULL,
    1,
    NOW(),
    NOW(),
    NULL
FROM roles
WHERE roles.role_key = 'rescuer'
  AND NOT EXISTS (
      SELECT 1 FROM users WHERE username = '2024035502'
  )
LIMIT 1;

-- Optional responder profile for the rescuer login.
-- This connects the responder profile to the temporary rescuer user.
INSERT INTO responders (
    responder_id,
    user_id,
    responder_code,
    created_by_admin_id,
    team_id,
    username,
    password_hash,
    full_name,
    title,
    contact_number,
    date_of_birth,
    gender,
    address,
    is_validated,
    is_deployed,
    created_at
)
SELECT
    2024035502,
    'USR-RSC-2024035502',
    'RSC-2024035502',
    'USR-HQ-2024035500',
    NULL,
    '2024035502',
    @temporary_password_hash,
    'Temporary Rescuer User',
    'Responder',
    '09170000003',
    NULL,
    NULL,
    'Temporary testing account',
    1,
    0,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM responders WHERE username = '2024035502'
);
