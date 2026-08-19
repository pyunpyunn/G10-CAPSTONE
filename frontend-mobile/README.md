# RESQPERATION Mobile App

Expo React Native app for the household and rescuer mobile roles.

The mobile app does not connect directly to MySQL. It connects to Laravel through the API, and Laravel connects to the shared MySQL database.

For the complete groupmate setup guide, including APK installation, development build setup, OneSignal dashboard verification, and troubleshooting, see:

```text
../docs/RESQPERATION_MOBILE_INSTALL_DEV_BUILD_ONESIGNAL_GUIDE.md
```

## Current API Connection

`frontend-mobile/.env`

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.112.109:8000/api/v1
```

This IP is the laptop Wi-Fi IP. If the laptop IP changes, update this value.

## Required Backend Command For Phone Testing

Run Laravel with `0.0.0.0`, not only `127.0.0.1`.

```bash
cd backend-laravel
php artisan serve --host=0.0.0.0 --port=8000
```

Reason: Expo Go on a phone must reach the backend through the laptop network IP.

## Start Mobile App

```bash
cd frontend-mobile
npm install
npm start
```

Open the QR code in Expo Go.

No emulator is required. Keep the phone and laptop on the same Wi-Fi, then scan the QR code from the terminal or Expo browser page.

## OneSignal Push Testing

Expo Go can run the app for normal QR testing, but it cannot load the native OneSignal SDK. To test real OneSignal Player IDs and create OneSignal subscription records, install a development build on the actual phone first.

### Option A: Install The Latest Finished Android Development APK

If EAS says the monthly build credits are already used, do not keep running
`npm run build:dev:android`. Install the latest already-finished APK instead.

Latest checked Android development APK:

```text
https://expo.dev/artifacts/eas/22yUVpAXJ9QmuOdlZNS5sPJuqfSqk2QgsYr9tu4FX80.apk
```

Open that link on the Android phone, download the APK, allow install from the
browser if Android asks, then install RESQPERATION.

After the app is installed, run Metro in development-client mode:

```bash
npm run start:dev
```

Open the project using the installed RESQPERATION app, not Expo Go.

If the phone shows `failed to connect to /192.168... port 8082`, the phone
cannot reach the laptop over LAN. Use tunnel mode instead:

```bash
npm run start:dev:tunnel
```

Tunnel mode is slower, but it avoids Wi-Fi isolation and Windows Firewall issues.

### Option B: Create A New EAS Android Development Build

```bash
npm run build:dev:android
```

Download and install the generated APK/AAB from EAS on the phone. After it is installed, run:

```bash
npm run start:dev
```

Scan/open the QR with the installed RESQPERATION development app, not Expo Go.

If the phone camera says the QR has no usable data, open the installed RESQPERATION development app and enter the Metro URL manually. The terminal prints it in this format:

```text
Metro waiting on exp+frontend-mobile://expo-development-client/?url=http%3A%2F%2FYOUR-LAPTOP-IP%3APORT
```

Use the decoded Metro URL part:

```text
http://YOUR-LAPTOP-IP:PORT
```

Example:

```text
http://192.168.112.130:8082
```

Use this only in the installed development build. Expo Go cannot create OneSignal subscription records.

If the app still opens stale code, restart Metro with:

```bash
npm run start:dev:clear
```

Do not use `expo run:android` for this project workflow. It can open an
emulator/device selector. Install the APK on the physical phone instead.

## Android Real Map Setup

OneSignal is only for notifications. It does not provide maps.

The final Android app uses `react-native-maps`, which uses Google Maps on
Android. The installed APK must include a Google Maps Android API key before the
real native map can display.

Add this to `frontend-mobile/.env`, then build and install a new development APK:

```env
EXPO_PUBLIC_GOOGLE_MAPS_ANDROID_API_KEY=your_google_maps_android_key
EXPO_PUBLIC_ENABLE_NATIVE_ANDROID_MAPS=true
```

Google Cloud setup:

1. Open Google Cloud Console.
2. Enable **Maps SDK for Android**.
3. Create an API key.
4. Restrict the key to Android apps.
5. Use package name `com.kathlnbarro.resqperationmobile`.
6. Add the app signing SHA-1 fingerprint if Google Cloud asks for it.

After changing `.env`, rebuild and install the APK. Metro reload alone is not
enough because the Google Maps key is native Android configuration.

## Temporary Mobile Accounts

| Role | Account ID | Password |
| --- | --- | --- |
| Household Resident | `2024035501` | `password` |
| Household Resident Setup Test | `2024035503` | `password` |
| Rescuer Temp SAR Test | `BDRRM-SAR-001` | `password` |
| Rescuer Medical Test | `BDRRM-MED-001` | `password` |
| Rescuer SAR Test | `BDRRM-SAR-002` | `password` |

Rescuer mobile login uses the BDRRM account ID format. The sequence is counted per team code:

```text
BDRRM-SAR-001
BDRRM-MED-001
BDRRM-SAR-002
```

The rescuer profile also has a separate editable username, such as `vinzon.arellano` or `vince.pacillan`.

Actual mobile login is DB-driven. Household accounts from SafeTrack work in RESQPERATION when they are already saved in the shared `users` table with a `household_resident` role, a valid password hash, and a linked `household_id`. The login field can accept the household `username`, `email`, `user_id`, or `household_id`.

## Verified API Flow

- Household login uses `/api/v1/auth/login`
- Household app loads `/api/v1/household/overview`
- Rescuer login uses `/api/v1/auth/login`
- Rescuer app loads `/api/v1/rescuer/overview`

If login fails on the phone but works on the browser/laptop, check:

- phone and laptop are on the same Wi-Fi
- Laravel is running with `--host=0.0.0.0`
- Windows Firewall allows port `8000`
- `EXPO_PUBLIC_API_BASE_URL` uses the current laptop Wi-Fi IP
