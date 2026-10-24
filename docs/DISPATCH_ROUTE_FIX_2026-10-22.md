# Accepted dispatch route repair — 22 October 2026

## Cause and repair

Active assignment 12 was a group dispatch with a null main `household_id`. Its actual household was recorded under `route_notes.responder_households`. The previous route writer ignored that coverage map, so it saved GPS trail points without a destination/road polyline. Separately, mobile En route immediately submitted On scene, and the navigation screen depended on a different cached-route dataset and device-bound session from the dashboard.

- Route generation now resolves the authenticated rescuer's coverage household, uses its latest pin, and persists the planned road route before returning acceptance/en-route success when GPS is supplied. Later GPS refreshes still use the operations queue. Explicitly empty coverage never inherits another rescuer's destination.
- Additive migration `2026_10_22_000001_add_responder_destination_to_routes.php` adds nullable responder/household identifiers and navigation steps. It was applied to the current backend. Road routes are separate per rescuer; the writer rejects a destination changed during a routing-provider call.
- Personalized mobile responses expose the actual assigned household and pin. Navigation bootstrap includes the same road route encoded as polyline6, rather than waiting for unrelated offline team-base precomputation. Bootstrap reads remain authenticated, role checked and scoped to the user's own assignments. Trip synchronization remains device-bound.
- The web map polls route geometry along with responder positions, and initially fits active route geometry. Selecting a route still focuses it.
- Mobile source removes the automatic On scene update, adds an explicit Arrived action, opens assigned navigation after acceptance/en-route, enables map GPS for those statuses, adds zoom/recenter/refresh controls and displays route readiness on the dashboard.
- Mobile navigation can cache the dashboard's own planned road geometry even with an ordinary authenticated session; it does not mislabel every 403 as an expired login. Updated sign-in obtains a device-bound session for rescuer/TL/TM roles. Session/team validation is retained for trip writes.

## Current operation

Regenerated assignment 12's route from its rescuer's saved GPS to household `HH-2026-8561`: route 12, 349 geometry points, 15.56 km, 11 maneuvers. Web mapping data, the mobile overview and navigation bootstrap were checked against that saved route. No dispatch lifecycle state was rewritten during the repair. The operations worker was restarted with current code. Both the API readiness endpoint and the web mapping URL returned HTTP 200.

On an installed APK, reopen Navigation and use Route options → Refresh assigned routes while connected to Tailscale. Refresh the web Mapping page. These server reads do not consume Expo APK build quota. Mobile source/UI changes only reach phones after installing a new APK.

## Validation and release

- 51 focused Laravel tests passed (277 assertions), covering acceptance, per-rescuer group targets, latest pins, provider failure, bootstrap scoping, authentication, role checks, sync, mapping and resources.
- 12 mobile route/snapshot/geometry tests passed; TypeScript passed. Targeted lint passed except the existing navigation effect dependency warning. Web production build and lint passed.
- No phone is attached over ADB, so physical-device UI and GPS behavior were not verified in this session.
- EAS refused the cloud build because the account exhausted its free Android build quota. The remote versionCode advanced from 18 to 19, but that cloud attempt produced no artifact. EAS reports reset on 1 November 2026. Billing was not changed.
- Built local release APK 19 successfully on 23 October 2026. Microsoft JDK 17 and matching Android 36/build tools, NDK 27.1 and CMake were installed and checked against their publishers' checksums. The existing Expo production signing key remains in a private directory outside the repository. APK signature verification passed and the certificate matches the existing release certificate. Package `com.kathlnbarro.resqperationmobile`, versionCode 19, versionName 1.0.0, non-debuggable, ARM64, Android API 24 minimum. APK ZIP integrity, embedded JavaScript bundle, configured Tailscale API address and updated Arrived UI strings were checked.
- Repeatable local command: from `frontend-mobile`, run `npm run build:android:local -- -VersionCode 19`. The script takes optional `-SigningJson`, `-JavaHome` and `-AndroidSdk` paths. The signing JSON must contain `storeFile`, `storePassword`, `keyAlias`, `keyPassword` and the existing certificate's `sha256`; keep both it and the keystore outside the repository. The build reads production API/OneSignal values from `eas.json`, generates native files, signs the release and checks app identity, version, debuggable flag and certificate. Output is `frontend-mobile/releases/RESQPERATION-build19.apk` only after a successful build.

The local script uses a Windows SDK junction without spaces and a short C++ staging directory with `CMAKE_OBJECT_PATH_MAX=240` to avoid compiler alias and generated filename problems. Defaults point to the installed tools under `C:\backend\tools\resq-android`.

Install APK 19 over the existing app on each phone to receive the En route/Arrived changes and map controls. Building locally does not automatically update other phones. Tailscale connectivity and the Laravel backend remain necessary for live sign-in, assignments and route data.

Artifact: `frontend-mobile/releases/RESQPERATION-build19.apk` (22,349,169 bytes). A copy is available at `http://100.121.155.55:8000/downloads/RESQPERATION-build19.apk`; the download returned HTTP 200 with the correct APK content type and size. The backend readiness endpoint also returned HTTP 200. SHA-256: `880ec7653452c590660cf274a817d539cbb2236479c6ede7a4e1d0263eabf8df`. Physical-phone installation, UI and GPS verification are still required.
