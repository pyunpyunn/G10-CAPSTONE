# Rescuer push and dispatch navigation

Disaster posts retain the existing household status prompt. A separate `rescuer_broadcast` push sends the plain title/message to all registered Android rescuers, including for selected-purok broadcasts. Rescuer messages never contain the household status toggle/options.

Dispatch creation sends `rescue_dispatch` to selected responders and the selected team's `leader_responder_id`. Both need an active, granted OneSignal subscription registered through their rescuer login. The message includes assigned sitio/area and total target households. Tapping it opens dispatch navigation. Household IDs are included in navigation bootstrap so an area dispatch offers each assigned household as a destination.

Route precomputation runs before the dispatch push in a job chain. Valhalla must be reachable at `VALHALLA_URL`, households need geotags, and the team needs a base location. Routing failures remain recorded as failed routes; they do not indicate that a road route exists. Mobile also offers routing from the rescuer's current GPS fix. Navigation shows kilometers, maneuver directions, and follows GPS during an active trip. Downloaded map packs support offline display.

Run `php artisan onesignal:status` to check API authentication, Android FCM setup, and local subscriptions. Keep `ONESIGNAL_APP_ID` in sync with mobile `EXPO_PUBLIC_ONESIGNAL_APP_ID`; keep `ONESIGNAL_API_KEY` on the backend only.

Start `./start-broadcast-worker.ps1` on Windows. It consumes `broadcasts,operations,navigation` on `operations_outbox`. Other deployments must consume those queues as well. Route computation can require a longer worker timeout for large assignments; monitor failed jobs and route coverage.

Backend alarm defaults: 60 seconds sounding, 15 seconds paused, four blasts (285 seconds including three pauses). `DISPATCH_ALARM_PAUSE_SECONDS` accepts 10–15; `DISPATCH_ALARM_CYCLES` accepts 3–4 so the total remains under five minutes. Run `php artisan config:clear` and restart queue workers after configuration changes.

The Android Expo config plugin installs a OneSignal native notification extension and media-playback foreground service. **Rebuild and install the Android app** to enable it; an OTA JavaScript update and Expo Go cannot install native code. The service uses the device alarm sound, retains a foreground notification with a Stop alarm action, and suppresses duplicate assignment alarms for 24 hours. Test foreground, background, locked-screen, muted/alarm-volume, permission-denied, duplicate, and stop-action cases on a physical Android device. Android background-start restrictions, force-stop, and user sound settings can prevent playback; the ordinary push remains available. This does not implement a repeating background alarm on iOS.

References: [OneSignal push API](https://documentation.onesignal.com/reference/push-notification), [OneSignal native extensions](https://documentation.onesignal.com/docs/en/service-extensions), [Android foreground service types](https://developer.android.com/develop/background-work/services/fgs/service-types).
