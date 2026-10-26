# GPS follow and offline navigation acceptance

## Implemented GPS follow

- Household map: select a center and tap **Use live GPS**. The first fix anchors
  the existing route; later fixes move the blue GPS marker/camera without
  requesting a new road route on every fix. Select **Household geotag** to stop.
- Rescuer map: tap **Track Me**; the camera follows GPS while the existing
  dispatch location submission continues. Tap **Stop** to stop this map watch.
- Both map views zoom to level 17 on the first fix, retain subsequent user zoom,
  allow dragging to pause camera follow, and provide **Follow GPS** to recenter.
- GPS uses existing expo-location, balanced accuracy, 5 seconds / 5 metres,
  rejects invalid coordinates and fixes worse than 75 metres, and pauses when
  the app backgrounds. Pending watch startup is cancelled on screen cleanup.
- Live fixes update the existing WebView marker/camera by JavaScript injection;
  they do not become part of the HTML source or reload the page on each fix.
- No dependency, map SDK, native permission, or bundled map pack was added.
  Existing OneSignal configuration remains unchanged.

This is simple GPS following, not a new turn instruction or automatic rerouting
system. The selected planned route remains visible. It does not change the
separate rescuer dispatch-trip background recording service.

## Automated and local service evidence

The mobile GPS/map/pack test set passed 12 tests, including watch cleanup,
background/resume, denied permission, pending-start cancellation, bad-fix
filtering, zoom/recenter, and signed native-pack resume/checksum/import.
TypeScript passed. The selected navigation backend suite passed 5 tests with
37 assertions.

During this check Docker was initially stopped. After temporary startup:

- Valhalla status returned HTTP 200 inside its container.
- The Mambaling style returned HTTP 200 inside the tile container.
- A real pedestrian request between (10.292222, 123.876389) and
  (10.2905, 123.8798) returned status 0, 0.759 km and 13 maneuvers.
- The current operational DB had **0 offline_packs and 0 cached_routes**.
- The generated v1 ZIP manifest used `http://localhost:8080`, which a phone
  cannot use to reach the laptop. Its style also returned localhost resource URLs.

These observations are not proof of phone offline navigation. No Android device
was connected for an airplane-mode test. Docker availability and route/tile
responses can change after this check.

## Offline completion gates — not yet fully verified

1. Publish the style, vector tiles and glyphs using a phone-reachable server
   URL; configure access policy/firewall for the selected tile port.
2. Register a compatible versioned map pack and precompute the assigned routes.
   There are currently no registered packs or cached routes in the checked DB.
3. The server build script produces an MBTiles ZIP. Do not import it into
   MapLibre's native offline database importer. Either provision the required
   compatible native DB artifact or complete and verify native region downloads.
4. Finish household offline data/map delivery. Its current basic route view
   uses online OSRM and Leaflet/CDN/OSM tiles; simple GPS following does not make
   that view fully offline.
5. Test the downloaded rescuer region, style/glyphs, saved routes, relaunch,
   offline location recording, arrival, and reconnect synchronization on Android.
6. Measure actual map-pack/APK size, startup, memory and battery on the target
   low-spec phone. Do not present the <40 MB map-pack target as a measurement.

See [OFFLINE_NAVIGATION_PROGRESS.md](./OFFLINE_NAVIGATION_PROGRESS.md) for
remaining deployment/artifact gates and navigation phases.

## Phone test procedure

### GPS follow

1. Install the updated signed APK containing this change, preserving app data.
2. Enable device Location and allow the app's foreground location permission.
3. Household: open Map, select an evacuation center, tap Use live GPS. Rescuer:
   open the dispatch map and tap Track Me.
4. Confirm the map zooms and the blue marker/camera move as you walk outdoors.
5. Drag the map: it should stop recentering. Tap Follow GPS to resume.
6. Pinch zoom and move again: the selected zoom should remain.
7. Disable GPS/deny permission: the selected route must remain visible with an
   availability/permission message when the location provider reports failure.
8. Background the app or leave this map: its foreground watch should stop.
   Return to the map and verify tracking can resume. Stop active trip recording
   separately if a rescuer dispatch trip was started.

### Offline acceptance after completing the gates

1. While online on Wi-Fi, log in, refresh the authorized assigned routes, and
   finish the compatible map download. Confirm it reports complete.
2. Select a saved route and record the downloaded bytes/version/checksum.
3. Enable airplane mode, turn Wi-Fi/mobile data off, and keep Location enabled.
4. Verify street geometry/labels, selected route, GPS marker/follow and saved
   directions work without contacting CDN/tile/routing servers.
5. Close/reopen the app offline and repeat. Confirm offline session restoration.
6. For a rescuer trip, record an agreed short known-distance walk and end it.
7. Reconnect and verify events sync once, no points/statuses disappear, and
   server trip statistics are consistent with the measured route.
8. Record phone model, Android/build number, screenshots/logs, bytes, startup,
   RAM and battery observations. Only then mark the tested flow verified.

Do not claim household and rescuer offline support from testing only one role.
Do not claim GPS/map tests prove OneSignal delivery; test push independently.
