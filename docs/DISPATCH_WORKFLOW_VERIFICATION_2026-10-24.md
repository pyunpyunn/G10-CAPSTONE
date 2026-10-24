# Dispatch workflow verification — 24 October 2026

## Outcome and scope

An isolated SQLite test exercises the actual web dispatch validation/service, priority and dashboard queries, responder selection, mobile assignment workflow, routing persistence, field-report workflow, and official household status/history writes. No unsafe test records, assignments or status changes were added to the operational database. Notification jobs and routing responses are faked in the integration scenario so no test alert reaches real personnel.

This is automated business-logic and API-contract verification, not a claim of flawless field operation or a completed browser/phone demonstration. No Android device was attached (`adb devices` returned an empty list).

## Scenario

| Step | Test data and verified result |
| --- | --- |
| Unsafe input | Three households, HH-4/HH-5/HH-6, receive event-linked needs-help reports. Dashboard unsafe total becomes 3. |
| Ranking | Actual sitio query ranks Test Priority ahead of Test Standby using the stored reports and vulnerable-member flags. No priority score is inserted manually. |
| Web assignment | Select sitio 1, purok 1, team 1, responders 5 and 6. Assign HH-4 and HH-5 to responder 5; HH-6 to responder 6. The real dispatch service validates/saves the selection and returns HTTP 201. |
| Notification | The service queues SendDispatchPush. This verifies job submission, not provider delivery or a notification received on a phone. |
| Mobile visibility | Each rescuer's assignment query exposes only that rescuer's current household. The existing mobile service supplies the assigned-household list, pending IDs and completion count. |
| Accept / en route | Rescuer 5 accepts and starts en route with a synthetic GPS fix. A persisted road polyline and household-specific destination are returned. |
| Shared dispatch isolation | Rescuer 6 accepting does not reset rescuer 5's en-route state. Each has independent acceptance/navigation status. |
| Complete without report | After arrival, rescuer 5 explicitly confirms HH-4 as Safe. No detailed field report is created. Household current status and a responder-attributed history log are saved. Unsafe total drops to 2; safe total becomes 1. |
| Next household | HH-5 becomes current with status Dispatched, requiring a fresh acceptance. HH-4's retired route is not returned. Acceptance/en route creates a separate route record for HH-5. |
| Optional report | HH-5 receives a detailed report with member M-3 assessed Safe. Both the member assessment and household current status are updated. |
| Complete own queue | Rescuer 5 completes HH-5, becomes available, and has no current household. Rescuer 6 remains accepted/deployed. The shared dispatch remains open. |
| Finish shared dispatch | Rescuer 6 travels to HH-6, arrives, and confirms Safe. The dispatch closes only after all selected rescuers finish. Dashboard unsafe becomes 0; safe total becomes 3. Sitio status bars and team coverage also reflect the changes. |

The fourth household is an unchecked comparator in the other sitio, so the test does not claim the entire registry is reported or safe.

## Changes made after testing

- Detailed field reports are optional. Completion requires a deliberate Safe or Evacuated confirmation for the current household.
- Acceptance, en-route and arrival transitions are checked on the server. Completing before arrival is rejected. Active-event ownership is checked.
- Household completion writes official event status plus timestamped, rescuer-attributed history and clears its dispatch-needed flag. It does not fabricate individual member assessments.
- Each rescuer's state is stored separately inside the shared dispatch. Completing one rescuer's work does not release or reset another rescuer.
- The next household returns to Dispatched for acceptance; the overall HQ status summarizes remaining active work.
- Completed routes are retired. The next destination receives a distinct route/trail, and stale household directions are filtered from the mobile response.
- Navigation bootstrap returns the rescuer's own pending households and own state. The navigation selector offers the current household rather than previously completed ones.
- Household completion ends local trip recording. An error stopping recording is reported separately from the successful household save.
- A rescuer's navigation status report does not automatically withdraw the dispatch before the rescuer completes the task. Household-origin safe reports retain their existing withdrawal behavior.
- Dispatch outcome counts and team coverage include the quick completion path as well as detailed reports.

## Verification evidence

- **77 backend tests / 333 assertions passed**, covering dispatch lifecycle, selection, coverage, assignment/report ownership, safe/evacuated completion, retries, transaction rollback, household updates, priorities, navigation bootstrap and sync. Output: `../backend-laravel/docs/verification/dispatch-2026-10-24/backend-tests.txt`.
- **41 mobile workflow/navigation/UI tests passed**, covering assigned-route selection, route handoff, geometry, GPS lifecycle and permission denial, map pan/recenter behavior, notifications, session cancellation, storage and pack recovery. Output: `../backend-laravel/docs/verification/dispatch-2026-10-24/mobile-workflow-tests.txt`.
- **2 web dispatch-geography tests passed**. Web production build succeeded. Mobile TypeScript check succeeded.
- Targeted mobile lint reported no errors and one existing exhaustive-dependencies warning in OfflineNavigationScreen. Targeted web lint passed.
- A separate read-only request to the configured live road-routing service, using synthetic coordinates, returned **32 geometry points, 0.62 km, 2 minutes and 7 steps**. This confirms a live route response; it does not certify route safety, map tile availability, or GPS tracking on a phone. Output: `../backend-laravel/docs/verification/dispatch-2026-10-24/live-road-provider.txt`.
- A broader mobile run found **4 failing household-alert tests**: the test loader rejects the extracted `DisasterBroadcastBanner` import. They are outside the selected dispatch/navigation suites and are not represented as passing. Diagnostic output: `../backend-laravel/docs/verification/dispatch-2026-10-24/household-alert-diagnostics.txt`.

## Remaining physical-device acceptance

Install the updated APK, use dedicated test accounts on a staging dataset, and verify: notification receipt while backgrounded; map tiles on Wi-Fi/mobile data; GPS movement along and away from the route; foreground/background permission behavior; arrival and Safe/Evacuated confirmation; next-household acceptance and route replacement; queue recovery after app restart; and HQ refresh after each report. Test weak connectivity and delayed/retried submissions. No walking/driving test or physical push receipt was performed in this session.

All test fixtures use synthetic names and records. Existing operational households and live dispatches were left unchanged.


## Updated Android artifact

Release build 22 completed successfully after a retry. The standard local release script verified the existing release certificate, package `com.kathlnbarro.resqperationmobile`, versionCode 22, and non-debuggable metadata.

- APK: `../frontend-mobile/releases/RESQPERATION-build22.apk`
- Convenience copy: `C:/Users/Kathleen Barro/Downloads/RESQPERATION-build22.apk`
- SHA-256: `450DA88E6AD1BCCFBE87596AAD052015C5CD51BDDBC0C210E8F475EDC1F95658`
- Build log: `../frontend-mobile/releases/build22-dispatch-workflow-retry.log`

APK compilation/signature checks do not replace the physical-device acceptance steps above.
