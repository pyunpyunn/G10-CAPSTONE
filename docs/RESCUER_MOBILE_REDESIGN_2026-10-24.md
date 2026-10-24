# Rescuer mobile redesign — 24 October 2026

## Home screen

The previous assignment card repeated the event, area, address, household context and instructions, and repeated map/report shortcuts below the queue. The replacement shows the current household, ID, priority and status directly. Acceptance, En route and Arrived remain state-dependent actions. Map, Field report and Complete household share one equal-width row with accessible touch targets and wrapping labels. The area/address appears in the household queue rather than being repeated through the assignment card.

Dispatch history is opened using the history icon at the right of the Household queue heading. A modal loads paginated history from a new authenticated endpoint, 20 assignments per page, including older events. It only returns assignments belonging to the signed-in rescuer, including shared dispatch membership. Duplicate Open map / Send field report quick links and inline dispatch history were removed.

## Field report

- Each member occupies a compact row: name and a status dropdown. Dropdowns show the saved individual event status. An unassessed member defaults to the database's Unknown/Unchecked status, when configured; a household-wide report is not fabricated into an individual assessment.
- Options and labels are read from `member_statuses`; the mobile app does not define the status choices.
- Incident type is removed from the interface and optional in the assignment-report API. Existing clients can still supply it.
- Property damage has a separate section with a dropdown sourced from `property_damage_levels`. The additive migration seeds the existing five canonical damage levels and adds casualty classification flags to the member-status catalog. The migration has been applied.
- Death and injury totals are read-only and derived from member selections using catalog flags. The backend independently recalculates them, overriding incorrect client totals. Tests include a custom database damage level, a database-defined casualty status and deliberately incorrect submitted counts.
- Mixed assessed/unassessed members are not automatically classified as a fully safe household. Urgent/casualty assessments take precedence; incomplete assessments retain an unknown household status where configured.
- Detailed field reports remain optional for task completion. Completion still requires the assigned rescuer's explicit Safe/Evacuated confirmation after arrival.

## GPS and route visibility

The rescuer map starts foreground GPS automatically, requests a current high-accuracy fix, and rejects fixes older than 15 seconds or with poor accuracy. Accepted/en-route/on-scene and dispatched preview tasks can sync their location without changing their acceptance state. Location submission can request immediate road-route refresh rather than waiting for a worker. The first eligible fix requests directions; subsequent refresh requests are spaced by 20 seconds. GPS marker movement continues between route refreshes.

The full navigation screen also requests a current fix when opening the selected dispatch/household/profile. The server only routes the rescuer's current queued household. Routing errors retain an explicit unavailable/saved-route state rather than claiming old-origin directions are current.

The embedded Leaflet map replaces marker and route layers without replacing the WebView document. GPS follow, manual zoom and panning therefore remain intact during data refreshes. Route-layer refresh behavior has a regression test.

The web mapping query now associates a route with its actual rescuer, rather than always using the shared dispatch's primary rescuer. Active route listing no longer applies the previous 50-row cap. Closed routes are excluded. Registered household targets establish scope; a valid road route is not discarded merely because part of its geometry crosses the barangay boundary. The Routes sidebar identifies the rescuer and target household.

## Verification

- 85 backend tests passed, with 374 assertions. This includes dispatch/report regression, database dropdown defaults, server casualty calculation, history pagination and ownership, immediate GPS-origin route updates, and active map routes.
- 32 selected mobile UI/GPS/navigation tests passed. This includes compact home actions, history entry point, member dropdown defaults, reactive casualty totals, incident-free submission, fresh GPS fixes, stale-fix rejection and map layer refresh without camera movement.
- Mobile TypeScript check and targeted lint passed. Web production build and targeted MappingPage lint passed. The existing navigation-screen hook warning is outside the targeted clean component lint.
- Read-only checks against current records returned 1 active web route, 5 active damage levels, 12 active member statuses, and one active status flagged for each casualty count category. These checks did not add or change operational households or dispatches.
- Android Build 23 completed and passed release-certificate, package identity, versionCode 23 and non-debuggable checks. The final rebuild includes the compact member rows and incremental map-layer updates.

Evidence files: `../backend-laravel/docs/verification/mobile-redesign-2026-10-24/`.

## APK

- `../frontend-mobile/releases/RESQPERATION-build23.apk`
- Convenience copy: `C:/Users/Kathleen Barro/Downloads/RESQPERATION-build23.apk`
- SHA-256: `1E6CD68853BD94EBC288D8E0BAFDA1D9F09745A5CED9BCF1D16D56357CE03C68`

No physical Android device was connected. Native screen layout, real GPS travel, tile loading and notification receipt still require device acceptance. Component rendering and geometry tests do not certify physical navigation or route safety. The four previously documented household-alert test-loader failures are not included in the selected passing suites and were not changed by this redesign.
