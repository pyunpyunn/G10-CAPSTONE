# Standalone Android APK

## Latest local APK — version 2.0.0, build 48

Installable file: [`releases/RESQPERATION-build48.apk`](./releases/RESQPERATION-build48.apk).

- 22,585,189 bytes; version code 48; package `com.kathlnbarro.resqperationmobile`; existing release signing certificate verified.
- SHA-256: `A122086CE09A0E7F8540151C9B923D1E3023844067B6065CC9256FD5C0625E7A`.
- Password recovery now reports whether its code was sent by OneSignal push or SMS. Backend delivery targets active Android/iOS subscriptions registered to the account first, then falls back to the Android SMS Gateway and Semaphore. The Laravel backend change must be deployed and OneSignal configured for push recovery to work.
- Mobile TypeScript check, mobile recovery-screen lint, web recovery-screen lint, and backend auth tests (29 tests, 179 assertions) passed.
- Android production build, APK identity and release-signature verification passed. Build 48 retains the existing signing identity so it updates earlier builds without uninstalling.
- Physical-device push/SMS delivery and password recovery remain to be verified.

## Previous local APK — version 2.0.0, build 47

Installable file: [`releases/RESQPERATION-build47.apk`](./releases/RESQPERATION-build47.apk).

- 22,584,813 bytes; version code 47; package `com.kathlnbarro.resqperationmobile`; existing release signing certificate verified.
- SHA-256: `5762BF850F1114549B0B24F7E89BCB9592EF1B5286D69812033499CB89A61ADF`.
- Mobile and web password-recovery screens prevent duplicate sends while an SMS request is in flight and show a one-minute countdown after three code-send attempts. The backend change must also be running for account-scoped cooldowns and informational responses to requests during cooldown.
- Mobile TypeScript check, focused mobile auth/transport tests (19), web/mobile lint, and backend authentication tests (28 tests, 170 assertions) passed.
- Android production build, APK identity and release-signature verification passed. Build 47 retains the existing signing identity so it updates Build 21/45/46 without uninstalling.
- Physical-device SMS delivery and login remain to be verified.

## Previous local APK — version 2.0.0, build 46

Installable file: [`releases/RESQPERATION-build46.apk`](./releases/RESQPERATION-build46.apk).

- 22,584,069 bytes; version code 46; package `com.kathlnbarro.resqperationmobile`; existing release signing certificate verified.
- SHA-256: `BBE7B084B0799504E92CBF56B609E16019DAAF2F9985C21495FA787B861BAAB0`.
- API routes are checked in order: the configured ngrok base URL first, followed by any configured HTTPS fallbacks. Ngrok is currently the only configured route.
- Mobile TypeScript checks and all seven focused transport tests passed. Android production build, APK identity and signing-certificate verification passed.
- Backend authentication feature tests passed (27 tests, 159 assertions). Accounts with unsupported legacy password values now receive password-reset guidance instead of an HTTP 500; those accounts must reset their password before they can sign in.
- Physical-device installation and login remain to be verified.

## Previous local APK — version 2.0.0, build 45

Installable file: [`releases/RESQPERATION-build45.apk`](./releases/RESQPERATION-build45.apk).

- 22,577,153 bytes; version code 45; package `com.kathlnbarro.resqperationmobile`; existing release signing certificate verified with APK Signature Scheme v2.
- Release API configuration requires HTTPS; previously saved HTTP API addresses are rejected in release mode. The packaged Android manifest explicitly disables cleartext traffic and the APK is not debuggable.
- TypeScript checks and all 130 mobile tests passed during release verification, including six transport-security tests. The earlier handoff reports 39 focused backend tests passing; backend tests were not rerun during artifact verification.
- SHA-256: `2B97935AEFD4A33C618C80CA898313F21F61E04C55FEC59BF668140FA8523AFD`.
- Physical-device installation, login, background location, dispatch alarms, and push delivery remain to be verified for this APK.
- The existing API listener was still bound to `0.0.0.0:8000` during verification. The new loopback startup default takes effect only after that service is safely restarted; this release verification did not restart it.

## Previous local APK — version 2.0.0, build 44

Installable file: [`releases/RESQPERATION-build44.apk`](./releases/RESQPERATION-build44.apk).

- 22,577,265 bytes; version code 44; package `com.kathlnbarro.resqperationmobile`; existing release signature verified.
- The rescuer and household offline fallback views display `OFFLINE MODE`.
- TypeScript checks and all 124 mobile tests passed. Release APK identity, non-debuggable status, and signing certificate were verified.
- SHA-256: `3349219A03E6B6D21F59A0D5A92DEDED3B337A0D1842E7C59D0E4F93A2AF2829`.

## Build 42 notes — version 1.0.0

Local file: [`releases/RESQPERATION-build42.apk`](./releases/RESQPERATION-build42.apk).

- 22,574,181 bytes; version code 42; existing release signature verified.
- Restored native map mounting across Android lifecycle changes; the embedded rescuer map uses a TextureView inside its scroll container.
- Undownloaded map packs no longer replace the street-map style. A failed saved style falls back to the street map.
- Preserved Expo's reflection-loaded `RNHeadlessAppLoader` in the release shrinker. Build 41 logged ClassNotFoundException for this removed class; build 42 retains it and the error was absent during the emulator check.
- Dispatch actions reuse GPS saved within 30 seconds. New GPS acquisition has an eight-second timeout. Navigation overview/bootstrap requests run concurrently.
- 122 mobile tests and TypeScript checks passed. Build 42 was installed in the Android emulator; navigation map rendering and background/resume were checked. Map-tab mounting and style fallback have component regression tests. Authenticated field operations, heat and performance on the user's physical phone remain unverified.
- SHA-256: `E71B460732163D805ADD4E11E0F8E53C3150D1CD56C650DE2867FA2AD6F8BAAA`.

## Previous local APK — version 1.0.0, build 40

Local copy: [`releases/RESQPERATION-build40.apk`](./releases/RESQPERATION-build40.apk).

- Download: **22,492,501 bytes**.
- Package: `com.kathlnbarro.resqperationmobile`; version code **40**; not debuggable.
- APK Signature Scheme v2 verification passed using the existing release certificate.
- SHA-256:
  `88E0F8951C7A029ABBC0BC15EA02E0EB23E4E7EFB58743FFFC1BFD51C519DA98`.
- Evacuation-center vehicle routes now use the same OSRM road-routing service as
  dispatch routes; successful center routes are cached locally and reused when
  the rescuer's origin and center destination remain nearby. The backend change
  must be deployed for this routing behavior to be available.
- The MapLibre map is released while its screen is unfocused; route rendering is
  capped at 800 points on the center map and 1,200 points in dispatch
  navigation. GPS sampling and HQ uploads remain movement/time limited. Route
  refresh requests are cancelled when navigation is left.
- TypeScript checks, focused map/cache/GPS tests, rescuer/household login-flow
  tests, backend login/navigation tests, production APK build, and APK identity
  and signing-certificate verification passed. Physical device performance and
  live routing still need to be checked on target phones.

## Historical GitHub release — version 1.0.0, build 6


[Download the signed APK from GitHub](https://github.com/pyunpyunn/G10-CAPSTONE/releases/download/v1.0.0-build6/RESQPERATION.apk)

Local copy: `releases/RESQPERATION.apk`.

The build 6 release details below are retained as historical documentation;
build 45 above is the latest local APK.

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

**Updated:** See [automatic API connection fallback](./TAILSCALE_LAN.md). Build
21 contains API readiness probing, but its production configuration includes
only the ngrok URL; it has no configured Tailscale or LAN fallback. The mobile
client now probes the base URL first, followed by fallback URLs in the order
listed in `EXPO_PUBLIC_API_FALLBACK_URLS`.

The current Tailscale Serve feature is disabled by the tailnet administrator,
and the backend has no HTTPS listener on its LAN address. Release APKs reject
HTTP API URLs. Add these routes only after valid, phone-trusted HTTPS endpoints
are available and tested. All routes must reach the same Laravel backend and
database. Never expose the database port or embed Laravel secrets or the
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
