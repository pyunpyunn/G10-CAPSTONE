# RESQPERATION Mobile App

One standalone Android APK contains the household and rescuer systems. Sign in
with either account role to open its console. The release APK includes its code
and UI assets; it does not need Expo Go, a QR scan, or a running Metro server.

See [ANDROID_RELEASE.md](./ANDROID_RELEASE.md) for installation, hosting,
OneSignal configuration, device requirements, and measured build sizes.

[**Download Android APK — 24.3 MB (version 1.0.0, build 6)**](https://github.com/pyunpyunn/G10-CAPSTONE/releases/download/v1.0.0-build6/RESQPERATION.apk)

For 64-bit Android 7.0+. Follow [FIRST_RUN_SETUP.md](./FIRST_RUN_SETUP.md).
Build 6 uses one manually saved API address. Current Android releases try
Ngrok HTTPS first, then the local Wi-Fi IP and Tailscale IP, selecting the
first ready route. The one-time elevated backend installer binds the API and
configures restricted LAN/Tailscale firewall access. See
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

New builds automatically select the first ready configured route: Ngrok HTTPS,
local Wi-Fi HTTP, then Tailscale HTTP. The one-time elevated backend installer
binds Laravel to the private interfaces and scopes firewall access. The Ngrok
tunnel starts when its configured Windows user signs in. All routes reach the
same Laravel backend; the mobile app never connects directly to the database. See
[route setup](./TAILSCALE_LAN.md).

## Push notifications

The APK includes native OneSignal. Allow Android notifications after login;
Google Play Services and valid FCM credentials in OneSignal are required.
Keep the backend queues/scheduler running for queued pushes and reminders.

## Developer screen previews

`npm start` runs Metro for code development. Expo Go can preview compatible
screens, but native OneSignal and MapLibre features require the installed native
application. These development tools are not required by release APK users.

## Web Browser Access (Desktop, Tablet, Laptop)

The mobile application is accessible on the web as a responsive single-page Progressive Web App (PWA) sharing the same Laravel backend API and user accounts.

```powershell
# Development server (Expo Web)
npm run web

# Production PWA build and preview
npm run export:web
npm run preview:web
```

The web version runs locally at `http://127.0.0.1:8099` (or port `8081` in dev mode). On desktop and tablet screens, the interface is centered with responsive container boundaries and desktop-optimized controls. See [WEB_RELEASE.md](./WEB_RELEASE.md) for web hosting and deployment instructions.

## Reference screens

- [Household reference](./ResQperation-Household-Mobile-Reference.md)
- [Rescuer reference](./ResQperation-Rescuer-Mobile-Reference.md)
- [Web release reference](./WEB_RELEASE.md)

