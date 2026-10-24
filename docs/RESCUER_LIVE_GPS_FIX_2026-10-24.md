# Live GPS and mapping verification — 24 October 2026

Active rescue assignments now start GPS tracking independently of opening a map or starting a navigation trip. The same captured position updates mobile maps and is sent with its capture timestamp to the backend for web mapping. Tracking stops on logout or when the active assignment ends. Build 24 includes Android background-location and foreground-service permissions.

Foreground tracking requests high-accuracy updates every three seconds, with a fresh-fix watchdog. Background tracking requests updates every five seconds. Actual delivery depends on permissions, Android scheduling, connectivity and GPS reception. Stale fixes are rejected. Fresh approximate fixes remain visible with their accuracy instead of freezing the marker.

Offline navigation uses actual GPS coordinates instead of the saved route origin. Navigation controls use the navy application theme. Shared dispatch members appear on the web map, including outside the household boundary. Old queued road-route jobs cannot overwrite a newer location's route.

## Mapping outage

The database was available. A shared-responder JSON comparison passed an integer to MySQL JSON_CONTAINS, producing error 3146 and the generic database-unavailable message. The query now casts the MySQL candidate to text while retaining SQLite compatibility. Read-only live MySQL checks loaded both mapping overview and tracking: one marker and one active route. No operational records were changed for this check.

## Verification

- Backend workflow/mapping checks: 85 tests, 379 assertions passed.
- After the MySQL fix: 28 targeted tests, 132 assertions passed; live MySQL overview and tracking succeeded.
- Selected mobile GPS, authentication, navigation and map tests: 36 passed.
- Mobile TypeScript passed. Targeted mobile lint has zero errors and one existing navigation-effect dependency warning.
- Web production build passed.
- No Android phone was attached through ADB. Physical movement and locked-screen delivery remain device acceptance checks.

Evidence: `backend-laravel/docs/verification/live-gps-2026-10-24/`.

Install build 24 and allow precise and background location. During an active assignment, compare the mobile and web rescuer markers while moving and during a screen-lock interval. A force-stopped app cannot continue tracking until reopened.
