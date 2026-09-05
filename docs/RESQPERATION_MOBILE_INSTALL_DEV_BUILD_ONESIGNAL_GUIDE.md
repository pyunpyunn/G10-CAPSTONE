# RESQPERATION Mobile Install, Development Build, and OneSignal Setup Guide


Target project folder:

```powershell
C:\backend\G10CAPSTONE\resqperation-system
```

## 1. What Each Mobile Run Mode Means

| Mode | Command | App Used On Phone | Good For | Limitations |
| --- | --- | --- | --- | --- |
| Expo Go | `npm run start:go` | Expo Go | Basic screen/API testing | No real OneSignal native push subscription. Native Google Maps setup may differ. |
| Development build | `npm run start:dev` | Installed RESQPERATION development APK/app | OneSignal, native permissions, native maps, real mobile QA | Requires installing the dev APK/app first. |
| Tunnel development build | `npm run start:dev:tunnel` | Installed RESQPERATION development APK/app | When LAN/Wi-Fi cannot reach Metro | Slower. Can fail if ngrok/tunnel is unavailable. |

Important:

- Expo Go is for quick testing only.
- OneSignal requires the installed development build or final production build.
- Emulator is optional. This guide uses physical phones.

## 2. Required Accounts and Tools

Install these on the laptop:

1. Node.js LTS.
2. Git.
3. VS Code.
4. A browser.
5. Optional but recommended: Android Studio only if you need local Android builds. It is not required to install an already-built APK.

Phone requirements:

1. Android phone or iPhone.
2. Same Wi-Fi/LAN as the backend laptop, unless using tunnel.
3. Expo Go app for quick testing.
4. Installed RESQPERATION development build for OneSignal/native testing.

Online accounts:

1. Expo account for EAS builds:

```text
https://expo.dev/
```

2. OneSignal account/dashboard:

```text
https://dashboard.onesignal.com/
```

3. Current RESQPERATION OneSignal subscriptions page:

```text
https://dashboard.onesignal.com/apps/bc6266ac-90a4-4bfe-b4d5-59c57939e7a5/subscriptions
```

4. Current Expo project builds page:

```text
https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds
```

## 3. Backend Must Be Running First

The mobile app does not connect directly to MySQL. It talks to Laravel, then Laravel talks to the database.

Open Terminal 1:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8000
```

Why `0.0.0.0`:

- `127.0.0.1` works only on the laptop.
- A phone must reach the backend through the laptop Wi-Fi IP.

Check the backend locally in a browser:

```text
http://127.0.0.1:8000
```

If the browser cannot open it, fix the backend first.

## 4. Check The Laptop Wi-Fi IP

Open another terminal:

```powershell
ipconfig
```

Find the Wi-Fi IPv4 address. Example:

```text
IPv4 Address . . . . . . . . . . . : 192.168.112.130
```

That IP is used by the phone.

## 5. Configure `frontend-mobile/.env`

Create or update:

```text
C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile\.env
```

Example:

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.112.130:8000/api/v1
EXPO_PUBLIC_ONESIGNAL_APP_ID=bc6266ac-90a4-4bfe-b4d5-59c57939e7a5
EXPO_PUBLIC_GOOGLE_MAPS_ANDROID_API_KEY=
EXPO_PUBLIC_ENABLE_NATIVE_ANDROID_MAPS=false
```

Replace `192.168.112.130` with the laptop Wi-Fi IP from `ipconfig`.

Rules:

- `EXPO_PUBLIC_ONESIGNAL_APP_ID` is safe in mobile because it is public.
- Do not put `ONESIGNAL_API_KEY` in `frontend-mobile/.env`.
- The OneSignal REST API key belongs only in `backend-laravel/.env`.
- Do not commit real `.env` files.

## 6. Configure `backend-laravel/.env`

In:

```text
C:\backend\G10CAPSTONE\resqperation-system\backend-laravel\.env
```

Required values:

```env
DB_CONNECTION=mysql
DB_HOST=<shared-db-ip>
DB_PORT=3306
DB_DATABASE=<shared-db-name>
DB_USERNAME=<shared-db-username>
DB_PASSWORD=<shared-db-password>

ONESIGNAL_APP_ID=bc6266ac-90a4-4bfe-b4d5-59c57939e7a5
ONESIGNAL_API_KEY=<server-side-onesignal-rest-api-key>
```

After editing backend `.env`:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan config:clear
php artisan db:show
```

If `php artisan db:show` fails, mobile login will also fail.

## 7. Install Mobile Dependencies

Open Terminal 2:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm install
```

Optional checks:

```powershell
npm run lint
npx tsc --noEmit
```

If `npx tsc --noEmit` fails because the script cannot find TypeScript, run:

```powershell
npm install
```

again and retry.

## 8. Option A - Quick Test With Expo Go

Use this for fast UI/API testing only.

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm run start:go
```

Then:

1. Open Expo Go on the phone.
2. Scan the QR code from the terminal.
3. Log in using a DB-backed mobile account.

Expected:

- Screens should load.
- API calls should work if backend and DB are reachable.
- Location permission can appear.

Not expected:

- Real OneSignal subscription records.
- Real OneSignal Player ID.

If OneSignal subscriptions do not appear while using Expo Go, that is expected.

## 9. Option B - Install The Android Development APK

Use this for real OneSignal and native-device testing.

### 9.1 Download Existing Build From Expo

Open this in a browser:

```text
https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds
```

Current known Android development APK link:

```text
https://expo.dev/artifacts/eas/22yUVpAXJ9QmuOdlZNS5sPJuqfSqk2QgsYr9tu4FX80.apk
```

Use the direct APK link if it still opens. If it is expired, unavailable, or not the latest build, use the Expo builds page above and download the newest successful Android development build.

Steps:

1. Log in to the Expo account that has access to the project.
2. Open the latest successful Android development build.
3. Copy or open the APK artifact link.
4. Open the APK link on the Android phone.
5. Download and install the APK.
6. If Android blocks installation, allow install from the browser/file manager.

The installed app is the RESQPERATION development build. It is different from Expo Go.

### 9.2 Create A New Android Development Build

Use this only when a new native build is required, such as after changing:

- OneSignal plugin setup.
- Android package config.
- Google Maps Android API key.
- Native permissions.
- Native dependencies.

Commands:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npx eas-cli login
npx eas-cli whoami
npm run build:dev:android
```

When EAS finishes:

1. Open the generated build URL.
2. Download the APK to the phone.
3. Install the APK.

If EAS says build credits are used up:

- Do not keep retrying.
- Use the latest successful APK from the Expo builds page.
- Ask the Expo project owner to share the artifact link.

## 10. Run The Installed Development Build

After the APK is installed on the phone:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm run start:dev
```

Then:

1. Open the installed RESQPERATION development app.
2. Scan/open the QR from the terminal using that installed app.
3. Do not use Expo Go for this QR.
4. Log in.
5. Accept notification permission when prompted.

If the normal camera says the QR has no usable data:

1. Open the installed RESQPERATION development app.
2. Use "Enter URL manually" or the app's development server selector.
3. Enter the Metro URL printed in the terminal.

Example terminal line:

```text
Metro waiting on exp+frontend-mobile://expo-development-client/?url=http%3A%2F%2F192.168.112.130%3A8081
```

Manual URL to enter:

```text
http://192.168.112.130:8081
```

If port `8081` is busy and Expo uses `8082`, use the URL with `8082`.

## 11. Tunnel Mode When LAN Fails

Use tunnel mode when the phone cannot reach the laptop IP.

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm run start:dev:tunnel
```

Use the tunnel QR or URL inside the installed RESQPERATION development app.

If tunnel fails with `remote gone away`:

1. Check internet connection.
2. Retry after a few minutes.
3. Check:

```text
https://status.ngrok.com/
```

4. Use LAN mode if tunnel is down:

```powershell
npm run start:dev
```

## 12. OneSignal Dashboard Setup

Open:

```text
https://dashboard.onesignal.com/
```

Use the OneSignal account/org that owns this app:

```text
App ID: bc6266ac-90a4-4bfe-b4d5-59c57939e7a5
```

Where to check subscriptions:

```text
Audience > Subscriptions
```

Direct link:

```text
https://dashboard.onesignal.com/apps/bc6266ac-90a4-4bfe-b4d5-59c57939e7a5/subscriptions
```

Required OneSignal platform setup:

1. Android must be configured with Firebase Cloud Messaging credentials.
2. iOS must be configured with APNs credentials.
3. iOS real push builds require Apple Developer access.

For Android notification testing, the app must be installed as a development build or production build. Expo Go cannot create the native OneSignal subscription.

## 13. Expected OneSignal Flow

```text
Mobile user logs in
-> App initializes OneSignal using EXPO_PUBLIC_ONESIGNAL_APP_ID
-> App asks Android/iOS notification permission
-> User taps Allow
-> OneSignal creates a push subscription
-> App reads OneSignal player/subscription ID
-> App sends device data to Laravel
-> Laravel saves/updates device_tokens
-> OneSignal dashboard shows the subscription
```

The permission prompt appears after login or when the role screen opens and registration is retried.

If only location permission appears:

- You are probably using Expo Go.
- Or the installed APK was built before OneSignal was configured.
- Install a fresh development build.

## 14. Verify OneSignal Data In MySQL

After logging in on the installed development build and allowing notifications, run this in MySQL Workbench:

```sql
SELECT
    id,
    device_uuid,
    user_id,
    household_id,
    member_id,
    platform,
    app_role,
    push_provider,
    player_id,
    push_token,
    one_signal_user_id,
    notification_permission_status,
    battery_level,
    signal_strength,
    last_seen_at,
    is_active
FROM device_tokens
ORDER BY last_seen_at DESC;
```

Expected values:

| Column | Expected |
| --- | --- |
| `push_provider` | `onesignal` |
| `player_id` | Not null after successful OneSignal subscription |
| `notification_permission_status` | `granted` if user allowed notifications |
| `last_seen_at` | Current timestamp |
| `is_active` | `1` |
| `app_role` | `household` or `rescuer` |

If `player_id` is null:

1. Confirm the app is not Expo Go.
2. Confirm notification permission was allowed.
3. Confirm OneSignal App ID matches the dashboard app.
4. Restart Metro with:

```powershell
npm run start:dev:clear
```

5. Log out and log in again.

## 15. Verify Backend API From The Phone Network

From the laptop browser:

```text
http://127.0.0.1:8000
```

From the phone browser, use laptop IP:

```text
http://192.168.112.130:8000
```

Replace the IP with the actual laptop IP.

If the phone cannot open it:

1. Laravel is not running with `--host=0.0.0.0`.
2. Phone and laptop are not on the same network.
3. Windows Firewall is blocking port `8000`.
4. Wi-Fi has client isolation enabled.

Windows Firewall quick fix for testing:

1. Open Windows Security.
2. Firewall & network protection.
3. Allow an app through firewall.
4. Allow PHP or the terminal app on private networks.

Or temporarily test on a trusted private network with firewall rules adjusted.

## 16. Google Maps / Mobile Map Notes

OneSignal is unrelated to maps. OneSignal only handles notifications.

The native Android map uses Google Maps through `react-native-maps`. For real Android native map display:

1. Get a Google Maps Android API key.
2. Enable **Maps SDK for Android** in Google Cloud.
3. Add this to `frontend-mobile/.env`:

```env
EXPO_PUBLIC_GOOGLE_MAPS_ANDROID_API_KEY=<google-maps-android-api-key>
EXPO_PUBLIC_ENABLE_NATIVE_ANDROID_MAPS=true
```

4. Rebuild the development APK:

```powershell
npm run build:dev:android
```

5. Install the new APK.

Metro reload is not enough because the Google Maps API key is part of native Android configuration.

If the map screen crashes with `com.google.android.gms.maps`:

- The installed APK likely has no valid Google Maps Android API key.
- Add the key, rebuild, and reinstall.

If the map fallback shows an OpenStreetMap `403` image:

- Do not depend on hotlinking public OSM tiles from mobile image previews.
- Use native Google Maps with an API key for the actual build.

## 17. Common Errors And Fixes

### Error: `Cannot connect to the server right now`

Fix:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan serve --host=0.0.0.0 --port=8000
```

Then verify `EXPO_PUBLIC_API_BASE_URL` uses the laptop IP.

### Error: `The database is not reachable`

Fix:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan config:clear
php artisan db:show
```

If this fails, the DB host/credentials/network must be fixed before login works.

### Error: `No development build installed`

Meaning:

- `npm run start:dev` is running, but the RESQPERATION development APK/app is not installed.

Fix:

- Install the latest Android dev APK from Expo builds.
- Then rerun:

```powershell
npm run start:dev
```

### Error: Phone camera says QR has `no usable data`

Meaning:

- The QR is a development-client link, not a normal browser/Expo Go QR.

Fix:

- Open it with the installed RESQPERATION development app.
- Or manually enter the Metro URL shown in terminal.

### Error: `failed to connect to /192.168... port 8081/8082`

Fix:

1. Check phone and laptop are on same Wi-Fi.
2. Check the laptop IP did not change.
3. Allow Node/Expo through Windows Firewall.
4. Restart Metro:

```powershell
npm run start:dev:clear
```

5. Use tunnel if LAN still fails:

```powershell
npm run start:dev:tunnel
```

### Error: `Port 8081 is being used`

Fix:

- Allow Expo to use another port, then use that printed URL.
- Or close the old Metro terminal.

### Error: `EAS build credits are used up`

Fix:

- Use the latest finished APK from:

```text
https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds
```

- Do not keep running new builds.

### Error: iOS build says no Apple Developer team

Meaning:

- iOS device builds require Apple Developer access.

Fix:

- Use Android for current capstone testing.
- Or use an Apple account enrolled in an Apple Developer team.

### OneSignal dashboard still has no subscriptions

Check:

1. You are using the installed development APK, not Expo Go.
2. You accepted notification permission.
3. OneSignal App ID in `frontend-mobile/.env` is correct.
4. Android platform is configured in OneSignal with FCM.
5. The phone has internet.
6. Reinstall a fresh APK if the installed build is old.
7. Log out and log in again.

## 18. Recommended Full Setup Order For A New Groupmate

1. Pull latest code:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system
git pull origin main
```

2. Install backend dependencies if needed:

```powershell
cd backend-laravel
composer install
php artisan config:clear
```

3. Start backend:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

4. Get laptop IP:

```powershell
ipconfig
```

5. Update `frontend-mobile/.env`.

6. Install mobile dependencies:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm install
```

7. For quick UI test:

```powershell
npm run start:go
```

8. For OneSignal/native test:

```powershell
npm run start:dev
```

9. Install/open the RESQPERATION development APK if `start:dev` asks for it.

10. Log in, accept notification permission, and verify OneSignal dashboard plus `device_tokens`.

## 19. Quick Command Summary

Backend:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan config:clear
php artisan db:show
php artisan serve --host=0.0.0.0 --port=8000
```

Mobile quick test:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm install
npm run start:go
```

Mobile dev build:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm install
npm run start:dev
```

Mobile dev build with clean cache:

```powershell
npm run start:dev:clear
```

Tunnel fallback:

```powershell
npm run start:dev:tunnel
```

Create new Android development APK:

```powershell
npx eas-cli login
npm run build:dev:android
```

## 20. Do Not Commit These

Never commit:

```text
backend-laravel/.env
frontend-mobile/.env
OneSignal REST API key
Database passwords
Google Maps API key
```

Safe to commit:

```text
backend-laravel/.env.example
frontend-mobile/.env.example
docs/*.md
```
