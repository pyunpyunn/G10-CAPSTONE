# RESQPERATION Shared DB Audit and OneSignal Push Tokens

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

The device registration target is now OneSignal:

- `push_provider` should be `onesignal`
- `device_tokens.player_id` stores the OneSignal Player ID / push subscription ID
- `notification_permission_status`, `last_seen_at`, and `is_active` must be updated by the mobile login flow
- `expo_push_token` is no longer used by RESQPERATION mobile notifications

## Seeder Review

| Seeder | Shared DB action |
|---|---|
| `DatabaseSeeder` | Safe. It intentionally inserts nothing. |
| `TemporaryLoginSeeder` | Do not run on the shared DB. It creates temporary accounts. |
| `SampleDisasterStatusSeeder` | Do not run on the shared DB. It creates a test disaster and test status history. |
| `RescueTeamRosterSeeder` | Manual DB-member approval only. It is idempotent by account/team key and no longer resets passwords of existing accounts. |

The application schema is owned by the shared DB. Laravel's default migrations
only cover framework tables and must not be used to rebuild the shared schema.

## OneSignal Push Token Flow

The mobile app now performs this flow immediately after a successful household
or rescuer login. The authenticated role screens also retry registration when
opened:

1. Ask the device for notification permission.
2. Create/read the persistent mobile device UUID.
3. Initialize OneSignal using `EXPO_PUBLIC_ONESIGNAL_APP_ID`.
4. Read the OneSignal Player ID / push subscription ID.
5. Send the token to `POST /api/v1/mobile/device-token`.
5. Laravel updates an existing device row or inserts a new row.
6. Laravel stores the token in `device_tokens.player_id`.
7. Laravel stores `push_provider = onesignal`.
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

OneSignal requires a development or production build. Expo Go is not enough for
the OneSignal native SDK. The app safely retries token registration on the next
authenticated app open when registration fails.

Use the development client configuration in `frontend-mobile/eas.json` for
real push testing:

For ordinary Expo Go testing:

```bash
cd frontend-mobile
npm start
```

This is only for ordinary UI testing. Use the development build for OneSignal.

```bash
cd frontend-mobile
npx eas-cli login
npx eas-cli build --profile development --platform android
```

For an iOS physical-device development build, an Apple Developer account is
required:

```bash
npx eas-cli build --profile development --platform ios
```

After installing the development build, sign in as a household or rescuer.
The app asks for operating-system notification permission, obtains the OneSignal
Player ID, and sends it to Laravel for database storage.

Start Metro for the installed development build with:

```bash
npm run start:dev
```

Set the OneSignal App ID in `frontend-mobile/.env`:

```env
EXPO_PUBLIC_ONESIGNAL_APP_ID=your-onesignal-app-id
```

## Device Token Storage Rules

| Column | Expected value |
|---|---|
| `push_provider` | `onesignal` for RESQPERATION mobile registrations. |
| `player_id` | OneSignal Player ID / push subscription ID. If permission is denied or unsupported, Laravel stores an explicit `unavailable:{status}:{device_uuid}` marker instead of leaving the field blank. |
| `expo_push_token` | Not used. RESQPERATION no longer writes Expo push tokens. If this old column exists in the shared DB, ignore it for current notification checks. |
| `push_token` | Native OneSignal push token when available. If not available yet, Laravel stores the OneSignal Player ID marker instead of leaving the field blank. |
| `one_signal_user_id` | OneSignal user ID when available. If not available yet, Laravel stores the OneSignal Player ID marker instead of leaving the field blank. |
| `battery_level` | Updated from the physical device when available. New registration rows default to `0` until a real reading is received. |
| `signal_strength` | New registration rows default to `0` until a real network signal value is available. |
| `notification_permission_status` | Updated to `granted`, `denied`, or another real result when the installed development/production build requests permission. |

Do not copy a token from another device. Placeholder tokens are created only by
the app/backend to mark unsupported or denied-permission devices. Notification
sending must target real OneSignal player IDs only.

Laravel sends mobile pushes through `OneSignalNotificationService` for disaster
broadcasts and new rescue dispatch assignments. The sender reads active,
permission-granted rows from `device_tokens` and ignores `unavailable:*`
placeholder Player IDs.

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
player_id
push_token
one_signal_user_id
notification_permission_status
last_seen_at
is_active
```

Count OneSignal registration readiness:

```sql
SELECT
    COUNT(*) AS registered_devices,
    SUM(player_id IS NOT NULL AND player_id <> '') AS devices_with_player_id,
    SUM(player_id IS NOT NULL
        AND player_id <> ''
        AND player_id NOT LIKE 'unavailable:%') AS devices_with_sendable_onesignal_id,
    SUM(notification_permission_status = 'granted') AS permission_granted
FROM device_tokens;
```

Check the required fields that should not be blank after a mobile login:

```sql
SELECT
    SUM(device_uuid IS NULL OR device_uuid = '') AS missing_device_uuid,
    SUM(platform IS NULL OR platform = '') AS missing_platform,
    SUM(app_role IS NULL OR app_role = '') AS missing_app_role,
    SUM(push_provider IS NULL OR push_provider = '') AS missing_push_provider,
    SUM(player_id IS NULL OR player_id = '') AS missing_player_id,
    SUM(push_token IS NULL OR push_token = '') AS missing_push_token,
    SUM(one_signal_user_id IS NULL OR one_signal_user_id = '') AS missing_one_signal_user_id,
    SUM(notification_permission_status IS NULL OR notification_permission_status = '') AS missing_permission_status,
    SUM(last_seen_at IS NULL) AS missing_last_seen_at,
    SUM(is_active IS NULL) AS missing_is_active
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
    push_provider,
    CONCAT(LEFT(player_id, 18), '...') AS masked_player_id,
    last_seen_at,
    is_active
FROM device_tokens
ORDER BY last_seen_at DESC;
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
