# RESQPERATION Mobile App

One standalone Android APK contains the household and rescuer systems. Sign in
with either account role to open its console. The release APK includes its code
and UI assets; it does not need Expo Go, a QR scan, or a running Metro server.

See [ANDROID_RELEASE.md](./ANDROID_RELEASE.md) for installation, hosting,
OneSignal configuration, device requirements, and measured build sizes.

[**Download Android APK — 24.3 MB (version 1.0.0, build 6)**](https://github.com/pyunpyunn/G10-CAPSTONE/releases/download/v1.0.0-build6/RESQPERATION.apk)

For 64-bit Android 7.0+. Follow [FIRST_RUN_SETUP.md](./FIRST_RUN_SETUP.md) and
save your Tailscale/LAN address in **Server settings** before signing in. The
original default in this APK uses the retired ngrok tunnel.

## Build the downloadable APK

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm ci
npm run check
npm run build:android
```

EAS returns the signed APK download link. The default production profile builds
an APK; `npm run build:android:store` builds an AAB for Google Play.

## Server connection

**Current setup:** use [Tailscale or LAN](./TAILSCALE_LAN.md). The ngrok tunnel
below is historical; it has been stopped. Future builds default to Tailscale.

The current HTTPS API is
`https://sturdily-morse-sitter.ngrok-free.dev/api/v1`. It uses an ngrok tunnel to
the development computer, so Laravel, MySQL, ngrok and the computer must stay
online. This is not permanent cloud hosting.

Open **Server settings** on sign-in to save another HTTPS API address without
rebuilding. The mobile app connects to Laravel, which connects to MySQL. It
never connects directly to the database.

## Rescuer field reports and requests

- Assigned household details load from `/api/v1/rescuer/assignments/{assignmentId}/household`.
- Submit field reports to `/api/v1/rescuer/field-reports`.
- Assignment completion is rejected until Laravel confirms a field report was saved.
- Resource-request location suggestions load from `/api/v1/rescuer/resource-request-locations`.

To test the field-report workflow locally, run the latest Laravel migrations and
the focused API test from the backend directory:

```powershell
cd backend-laravel
php artisan migrate
php artisan test --filter=RescuerFieldReportApiTest
```

## Push notifications

The APK includes native OneSignal. Allow Android notifications after login;
Google Play Services and valid FCM credentials in OneSignal are required.
Keep the backend queues/scheduler running for queued pushes and reminders.

## Developer screen previews

`npm start` runs Metro for code development. Expo Go can preview compatible
screens, but native OneSignal and MapLibre features require the installed native
application. These development tools are not required by release APK users.

## Reference screens

- [Household reference](./ResQperation-Household-Mobile-Reference.md)
- [Rescuer reference](./ResQperation-Rescuer-Mobile-Reference.md)
