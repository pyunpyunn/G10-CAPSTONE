# Standalone Android APK

## Verified release — version 1.0.0, build 6

[Download the signed APK from GitHub](https://github.com/pyunpyunn/G10-CAPSTONE/releases/download/v1.0.0-build6/RESQPERATION.apk)

Local copy: `releases/RESQPERATION.apk`.

- Download: **24,303,289 bytes (24.3 MB / 23.18 MiB)**.
- Previous development APK: 227,634,757 bytes; this release is **89.3% smaller**.
- Uncompressed APK contents: 55,542,553 bytes; installed size also includes
  Android extraction, compiled code, application data and caches.
- Embedded Hermes bundle: 3,571,080 bytes. arm64-v8a only; minimum SDK 24
  (Android 7.0), target SDK 36.
- APK Signature Scheme v2 verification passed. Android manifest is not
  debuggable and declares no Expo dev launcher. Native OneSignal receivers,
  notification permission and Firebase messaging service are present.
- SHA-256:
  `ac968ae871baf84e84066c658b8e28a2b18b2658c09918cb66f99167d3dcc91c`.
- [EAS build record](https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds/baa938bc-c3a4-48a5-9b40-62a5c5442969).

The GitHub Release hosts the APK independently of the Expo artifact's expiry.

One installed RESQPERATION app supports both household and rescuer accounts.
The account role selects the existing console after login. The production build
embeds JavaScript, Hermes bytecode, fonts and UI assets. Expo Go, a QR scan and
Metro are not required on the phone. Expo remains the build framework.

## Build and install

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm ci
npm run check
npm run build:android
```

EAS signs the release APK using this project's existing remote signing key and
provides an installation/download URL when the build finishes. Install the APK
from that URL and allow Android's install-from-this-source prompt. Subsequent
updates must use the same signing key. Do not distribute the private key.

Optional USB installation:

```powershell
adb install -r .\releases\RESQPERATION.apk
```

Google Play uses an Android App Bundle rather than this downloadable APK:

```powershell
npm run build:android:store
```

## API hosting

**Updated:** [Tailscale and LAN setup](./TAILSCALE_LAN.md) replaces the ngrok
tunnel described below. The existing APK works after saving the new server
address; a rebuild is not required. Future APK builds default to Tailscale.

The current default API is:
`https://sturdily-morse-sitter.ngrok-free.dev/api/v1`.

No existing permanent online deployment was found in the project configuration.
This HTTPS endpoint was created using the computer's existing ngrok account. It
forwards to Laravel on this computer and stays independent of the Wi-Fi LAN IP.
**The computer, Laravel, MySQL and ngrok must remain online.** This is a testing
tunnel, not a new always-on cloud server; ngrok account traffic limits apply.

The computer's default DNS resolver intermittently returned “name does not
exist” for this tunnel. Google and Cloudflare public DNS resolved it correctly,
and the public HTTPS readiness check succeeded using that resolved address.
No system DNS settings were changed. If a phone also cannot resolve the host,
check its network/DNS or switch to permanent hosting using Server settings.

Start Laravel in one PowerShell terminal:

```powershell  
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
$env:APP_DEBUG = 'false'
$env:APP_ENV = 'production'
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Start the HTTPS tunnel in another terminal (the existing ngrok login is used):

```powershell
  ngrok http http://127.0.0.1:8000 --url=https://sturdily-morse-sitter.ngrok-free.dev
```

During the build session both were started as hidden background processes. Do
not start duplicate listeners while those processes are still running.

When permanent hosting is ready, open **Server settings** on the APK sign-in
screen and save its HTTPS API address ending in `/api/v1`. No rebuild is needed.
Changing servers signs out the old account and clears its navigation cache;
active trips and unsynced events must be completed/synced first.

For later builds, change `EXPO_PUBLIC_API_BASE_URL` in `eas.json` to make the new
address the default on new installations. Never embed Laravel secrets or the
OneSignal REST API key into the app.

## OneSignal

- App ID: `bc6266ac-90a4-4bfe-b4d5-59c57939e7a5` (matches the backend).
- Native OneSignal SDK and production plugin configuration remain enabled.
- Login associates the existing household/rescuer user ID with OneSignal;
  notification permission and subscription registration use the existing flows.
- Android notification permission must be allowed. Google Play Services and
  OneSignal's configured FCM platform credentials are required.
- The OneSignal API accepted the backend credentials during validation; existing
  delivery history included a successful recipient. This is not a delivery test
  from the newly built APK. Verify foreground/background delivery on a phone
  using a test account before broad distribution.
- Subscription changes and app foreground refresh registration; the former
  unconditional 30-second subscription polling timer was removed.
- Keep the existing backend queues/scheduler running for queued dispatch push,
  reminders and broadcasts. A reachable API alone does not run queued jobs.

## Size and device support

- arm64-v8a only, Android 7.0+; **32-bit-only Android phones cannot install it**.
  Low-end hardware support still needs a real-device check. Build a separate
  armeabi-v7a variant if the device fleet includes 32-bit phones.
- Hermes, R8 code minification and resource shrinking are enabled. Native
  libraries are compressed in the APK; Android extracts them during install, so
  installed storage usage is higher than the APK download size.
- Expo dev client/launcher was removed. All existing application dependencies
  needed for maps, radio/audio, photos, QR, push and navigation were preserved.
- Direct icon imports exclude unused font families: export assets fell from
  **47 to 30**; Hermes bundle from **5.88 MB to 5.59 MB** in local export checks.
- Build upload excludes backend/web code, secrets, native build folders and
  generated resources: **690 MB → 702 KB**. This is source-upload size, not APK
  size. The generated native project is rebuilt from `app.config.js` in EAS.
- No mandatory secondary application-resource download or OTA update is added.
  API data, photos and online map tiles still need network access. Offline map
  packs remain an explicit optional Wi-Fi download and are not bundled into APK.
- APK measurements are recorded above. RAM,
  startup and battery usage require a physical low-spec device; no phone was
  attached to this build workspace.

## Validation and backend prerequisite

`npm run check` passed. The 10 existing/new map, geometry and pack-download tests
passed. Android production export passed. Backend auth/navigation tests passed
(14 tests, 72 assertions). Public API live/readiness checks returned HTTP 200.

The four navigation migrations 000030–000033 were applied to `resq_local` because
the device-bound rescuer login requires their tables. New string foreign-key
columns now inherit their parent MySQL collations. Unrelated pending migrations
were not applied. Offline route/tile deployment still has the prerequisites
documented in `../docs/OFFLINE_NAVIGATION_PROGRESS.md`.
