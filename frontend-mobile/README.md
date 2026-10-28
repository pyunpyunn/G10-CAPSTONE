# RESQPERATION Mobile App

One standalone Android APK contains the household and rescuer systems. Sign in
with either account role to open its console. The release APK includes its code
and UI assets; it does not need Expo Go, a QR scan, or a running Metro server.

See [ANDROID_RELEASE.md](./ANDROID_RELEASE.md) for installation, hosting,
OneSignal configuration, device requirements, and measured build sizes.

[**Download Android APK — 24.3 MB (version 1.0.0, build 6)**](https://github.com/pyunpyunn/G10-CAPSTONE/releases/download/v1.0.0-build6/RESQPERATION.apk)

For 64-bit Android 7.0+. Follow [FIRST_RUN_SETUP.md](./FIRST_RUN_SETUP.md).
Build 6 uses one manually saved API address. New builds can fail over among
configured HTTPS API routes; only ngrok is configured currently. Tailscale/LAN
routes need working HTTPS endpoints before they can be added. See
[TAILSCALE_LAN.md](./TAILSCALE_LAN.md).

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

New builds automatically try each configured route. The current production
configuration contains ngrok only; the Tailscale Serve route is not active and
there is no LAN HTTPS endpoint yet. The ngrok tunnel still must be started on
the backend computer. All routes must reach the same Laravel backend; Laravel's
environment configuration determines the database. The mobile app never
connects directly to the database. See [route setup](./TAILSCALE_LAN.md).

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
