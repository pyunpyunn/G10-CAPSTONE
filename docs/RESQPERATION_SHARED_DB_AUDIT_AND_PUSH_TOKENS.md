# RESQPERATION Shared DB Audit and Expo Push Tokens

## Current Connection Status

The Laravel database host is configured in `backend-laravel/.env` as:

```env
DB_HOST=192.168.112.58
```

On June 25, 2026, the shared MySQL host `192.168.112.58:3306` was reachable
from this laptop and Laravel successfully opened the `klint` database.
The laptop and DB server must remain on the same routed network.

After joining the correct network:

```bash
cd backend-laravel
php artisan optimize:clear
php artisan db:show
```

Do not run `migrate:fresh`, `db:wipe`, or the sample seeders on the shared DB.

The current device registration audit found:

- 7 registered device rows
- 7 rows identified with `push_provider = expo`
- 5 rows with a real battery sample
- 0 Expo push tokens until an EAS development/production build is installed
  and notification permission is granted

## Seeder Review

| Seeder | Shared DB action |
|---|---|
| `DatabaseSeeder` | Safe. It intentionally inserts nothing. |
| `TemporaryLoginSeeder` | Do not run on the shared DB. It creates temporary accounts. |
| `SampleDisasterStatusSeeder` | Do not run on the shared DB. It creates a test disaster and test status history. |
| `RescueTeamRosterSeeder` | Manual DB-member approval only. It is idempotent by account/team key and no longer resets passwords of existing accounts. |

The application schema is owned by the shared DB. Laravel's default migrations
only cover framework tables and must not be used to rebuild the shared schema.

## Expo Push Token Flow

The mobile app now performs this flow after an authenticated household or
rescuer screen opens:

1. Ask the device for notification permission.
2. Create/read the persistent mobile device UUID.
3. Request an Expo push token.
4. Send the token to `POST /api/v1/mobile/device-token`.
5. Laravel updates an existing device row or inserts a new row.
6. Laravel prefers `device_tokens.expo_push_token`.
7. Laravel uses legacy `device_tokens.player_id` only when the Expo-specific
   column is absent.
8. The permission state, platform, app role, device name, and last-seen time
   are also stored when those columns exist.
9. The current shared schema has no `user_id` or `responder_id` in
   `device_tokens`. Until the DB member approves those columns, rescuer
   registrations use the existing nullable `member_id` field as the linked
   authenticated user ID, together with `app_role = rescuer`.

Relevant files:

- `frontend-mobile/utils/pushNotifications.ts`
- `frontend-mobile/api/device.ts`
- `frontend-mobile/app/household/index.tsx`
- `frontend-mobile/app/rescuer/index.tsx`
- `backend-laravel/routes/api.php`
- `backend-laravel/app/Http/Controllers/Api/MobileDeviceController.php`
- `backend-laravel/app/Services/MobileDeviceService.php`

An EAS project ID is required for production Expo push tokens. Run `eas init`
for the Expo project before a production build. The app safely retries token
registration on the next authenticated app open when registration fails.

Expo Go does not support remote push notifications for this SDK. Use the
development client configuration in `frontend-mobile/eas.json`:

For ordinary Expo Go testing:

```bash
cd frontend-mobile
npm start
```

This explicitly generates an Expo Go QR code. Do not use the development
client QR unless the development build has already been installed on the
phone.

```bash
cd frontend-mobile
npx eas-cli login
npx eas-cli init
npx eas-cli build --profile development --platform android
```

For an iOS physical-device development build, an Apple Developer account is
required:

```bash
npx eas-cli build --profile development --platform ios
```

After installing the development build, sign in as a household or rescuer.
The app asks for operating-system notification permission, obtains the Expo
push token, and sends it to Laravel for database storage.

Start Metro for the installed development build with:

```bash
npm run start:dev
```

Set the EAS project UUID in `frontend-mobile/.env` after `eas init`:

```env
EXPO_PUBLIC_EAS_PROJECT_ID=your-eas-project-uuid
```

## Why Some Device Columns Can Be NULL

| Column | Expected value |
|---|---|
| `push_provider` | `expo` for RESQPERATION mobile registrations. Existing app rows can be safely backfilled to `expo`. |
| `expo_push_token` | Present only after the installed EAS build receives notification permission and Expo returns a real token. Expo Go cannot create this remote-push token. |
| `player_id` | Remains `NULL`. It is a legacy OneSignal field and RESQPERATION does not use OneSignal. |
| `battery_level` | Updated from the physical device. Old rows with no battery sample remain `NULL`; a value must not be invented. |
| `signal_strength` | Remains nullable because Expo does not expose a reliable cross-platform numeric cellular/Wi-Fi signal percentage. Network connection type is shown in the app instead. |
| `notification_permission_status` | Updated to `granted`, `denied`, or another real result when the installed development/production build requests permission. |

Do not replace unknown telemetry or tokens with `0`, placeholder strings, or a
token copied from another device. That would make dispatch and notification
data inaccurate.

## Query: Check Push Token Columns

Run this first:

```sql
SHOW COLUMNS FROM device_tokens;
```

The preferred columns are:

```text
device_uuid
user_id
responder_id
household_id
platform
app_role
push_provider
expo_push_token
notification_permission_status
last_seen_at
is_active
```

If `expo_push_token` exists:

```sql
SELECT
    COUNT(*) AS registered_devices,
    SUM(expo_push_token IS NOT NULL AND expo_push_token <> '') AS devices_with_expo_token,
    SUM(notification_permission_status = 'granted') AS permission_granted
FROM device_tokens;
```

View registrations without exposing complete tokens:

```sql
SELECT
    id,
    household_id,
    user_id,
    responder_id,
    app_role,
    platform,
    notification_permission_status,
    CONCAT(LEFT(expo_push_token, 18), '...') AS masked_expo_token,
    last_seen_at,
    is_active
FROM device_tokens
ORDER BY last_seen_at DESC;
```

If only the old `player_id` column exists:

```sql
SELECT
    id,
    household_id,
    CONCAT(LEFT(player_id, 18), '...') AS masked_legacy_token,
    updated_at
FROM device_tokens
WHERE player_id IS NOT NULL AND player_id <> ''
ORDER BY updated_at DESC;
```

## Duplicate Review

Never delete a duplicate lookup row before checking which row is referenced by
foreign keys.

Gender duplicates by key:

```sql
SELECT
    LOWER(TRIM(gender_key)) AS normalized_key,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(gender_id ORDER BY gender_id) AS gender_ids
FROM genders
GROUP BY LOWER(TRIM(gender_key))
HAVING COUNT(*) > 1;
```

Gender duplicates by label:

```sql
SELECT
    LOWER(TRIM(gender_label)) AS normalized_label,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(gender_id ORDER BY gender_id) AS gender_ids
FROM genders
GROUP BY LOWER(TRIM(gender_label))
HAVING COUNT(*) > 1;
```

Check tables that reference `genders` before merging duplicate rows:

```sql
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME = 'genders';
```

Run the full read-only audit in:

`docs/sql_proposals/2026_06_25_shared_db_read_only_audit.sql`

## Required Reference Data

Confirm these lookup tables are not empty before testing all modules:

- `roles`
- `genders`
- `relationships`
- `household_statuses`
- `disaster_types`
- `severity_levels`
- `urgency_levels`
- `field_report_categories`
- `notification_channels`
- `notification_statuses`
- `resource_request_status`
- `rescue_teams`

Missing reference rows should be added only through a DB-member-reviewed,
idempotent SQL script. Do not generate a second row when the normalized key or
label already exists.
