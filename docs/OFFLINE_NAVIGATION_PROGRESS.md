# Offline navigation continuation — 18 October 2026

## Traced state

Copilot added deployment/tiles, route schema and precompute, device-bound APIs,
server trip statistics, and mobile dependencies/configuration. It stopped after
creating `frontend-mobile/utils/navigationStore.ts`. There was no mobile
navigation screen, offline pack manager, GPS task, or outbox sync consumer.
HQ trip history/aggregates/CSV and device revocation/retention remain unfinished.
Existing partner report and situation report changes in the working tree belong
to other work; this continuation does not modify those files.

## Implemented in this continuation

- Rescuer console → **View directions / Offline navigation**, available even if
  the online overview cannot load. Choose a saved dispatch, vehicle/foot profile,
  and household/evacuation route. Directions are visible as a numbered list.
- MapLibre 11.5 APIs checked against installed source and
  https://maplibre.org/maplibre-react-native/docs/modules/offline-manager/.
  Native region downloads persist across restarts and resume on Wi-Fi; completed
  versions are reused. Old regions are removed after a new version finishes.
- Polyline6 decoding, segment projection, maneuver advancement, heading-up
  camera at pitch 50°, remaining distance/ETA, off-route destination distance and
  bearing, online household rerouting, configurable arrival radius, and queued
  household status updates. Camera/text updates are limited to once per second;
  fixes are held in refs and the route layer is memoized.
- TaskManager location recording during an active trip, explicit foreground and
  background permission flow, Android foreground notification, foot/vehicle
  sampling and jitter/speed/accuracy filtering. Ending a trip stops GPS.
- Atomic SQLite trip/point/outbox writes; local-to-server trip ID mapping;
  ordered sync on trip events, foreground, and each 60 seconds during trips;
  exponential backoff without background retry polling. Acknowledged events
  are deleted; at most 100 acknowledged completed trips are retained.
- Persisted sync results return the same trip ID after a lost acknowledgement.
  Added migration `2026_10_18_000032_add_navigation_sync_results.php` must be
  applied with the other navigation migrations to a reviewed database.
- Fixed missing HTTP Response import in the bootstrap controller. Fresh routes
  require device/team ownership and a team-assigned household.

## Validation

- Mobile tests: **6 passed**, including signed/ranged native pack resume,
  checksum rejection, ignored Range rejection, precision-six decoding, malformed
  geometry, a known 1 km segment, projection, off-route distance and bearing.
- Navigation backend suite: **8 passed, 55 assertions**, covering bootstrap,
  sync idempotency/results, navigation schema, precompute, server trip statistics,
  and Valhalla response handling.
- `npx tsc --noEmit`: only the two existing household errors remain:
  missing trusted-screen callbacks in `app/household/index.tsx` and missing
  `saveHouseholdGeotag` export for `HouseholdSetupScreen.native.tsx`.
- Native build/airplane-mode/real walking tests were not run: no test phone or
  running tile/Valhalla deployment is connected to this workspace.

## Remaining gates — Phase 4 is not complete

1. **Checksum-verified signed pack delivery:** the existing server ZIP contains
   MBTiles, styles and glyphs. MapLibre's `mergeOfflineRegions` expects a native
   offline database, not MBTiles or that ZIP. This continuation uses native
   region resource downloads; it does **not** claim the server ZIP checksum
   validates those resources. Provision a versioned native offline database
   artifact. The signed/ranged downloader, SHA-256 verification and native import
   are implemented and unit tested; registration accepts `pack_format:
   maplibre_db`, `<version>.db`, and a matching manifest. Apply migration
   `2026_10_18_000033_add_offline_pack_format.php` first. The current build script
   still generates `mbtiles_zip`, so provisioning and device-testing a compatible
   native artifact remains a deployment gate. Do not pass the ZIP or MBTiles
   into `mergeOfflineRegions`.
2. Validate native MapLibre rendering/marker movement, permissions, screen-lock
   recording, process restart, offline arrival/status and reconnect sync on the
   actual Android fleet. OS force-stop cannot guarantee continued recording.
3. Rerouting currently supports household destinations; evacuation routes keep
   the cached line and bearing fallback. Extend fresh routes for evacuation
   centers with equivalent authorization.
4. Rejected events are retained as failed for diagnosis and block logout; a
   field-facing correction/recovery flow remains necessary.
5. Validate Phase 5 on a measured 1 km walk with live and late uploads, then
   implement Phase 6 HQ history/aggregates/CSV and Phase 7 revocation/retention.
   Stop for phase review before those phases, as the original task requests.

## Lightweight targets and measured values

| Requirement | Current evidence |
| --- | --- |
| Vector pack <40 MB, Mambaling only | z12–17 native region, manifest bbox; download pauses above 40,000,000 resource bytes. Actual pack size unmeasured; the completed event may exceed the cap. |
| One Latin font stack, minimal map | Existing generated style; not inspected on a device. |
| Versioned/resumable/Wi-Fi download | Native region API and signed/ranged SHA-256 native DB downloader implemented; compatible artifact generation/device verification pending. |
| Routes <1 MB/team, gzip | Polyline6 backend present; real team payload size and deployed gzip unmeasured. |
| SQLite/history/outbox | SQLite implemented, 100 acknowledged completed trips, acknowledged outbox pruned. |
| GPS | Foot 4 s/5 m; vehicle 3 s/10 m with speed-based acceptance distance up to 25 m. Accuracy >30 m rejected; stationary jitter excluded. Device energy measurement pending. |
| Rendering | One memoized route line, one marker, 1 Hz camera/text updates; device memory unmeasured. |
| Sync payload sizes | At most 25 events/request (one accepted fix per point event); actual byte sizes unmeasured. |
| APK/AAB size | Unmeasured; native release build required. |
| Cold start | Unmeasured; native device required. |
| Navigation memory | Unmeasured; native device required. |
| Battery drain/hour | Unmeasured; native device required. |
| Android optimization | Existing Hermes, arm64-v8a, minification/shrinking config. Fleet ABI compatibility unconfirmed. |
| Server limits/indexes/retention | Existing Compose/config/schema; deployment and scheduled retention still need verification/implementation. |

No operational migrations, deployment, commits, or physical-device measurements
were performed. The requested Expo v54 documentation URLs redirected to latest;
implementation signatures were checked against the installed SDK 54 packages.
