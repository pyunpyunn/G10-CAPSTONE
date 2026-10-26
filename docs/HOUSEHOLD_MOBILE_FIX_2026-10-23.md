# Household reports and rescuer mobile — 23 October 2026

The household review and rescuer mobile now share the dispatch's saved household coverage. Addresses use the names referenced by the saved purok and sitio IDs.

## Expected workflow

1. Sign in as the dispatched rescuer. Home shows the current household, household ID, canonical address, and queue position. The queue lists the remaining households and completed work.
2. Accept the dispatch, open directions, and mark arrival as appropriate.
3. Assess the current household's members and submit its field report. Reports are unique per assignment, rescuer, and household; identical retries preserve the original report.
4. Tap **Complete household**. This button remains enabled. If a report is missing, it explains the requirement and offers **Open report**. After a saved report, the report screen also provides **Complete household**.
5. Completion advances the saved queue. A stale completion cannot accidentally advance the next household. The shared HQ dispatch closes after every selected rescuer's household coverage is finished. Each rescuer sees their own completed work even when another rescuer is still working.
6. Log out from Profile. Recording stops and background sync is canceled before credentials are removed. Server unavailability does not prevent local logout. Pending events stay in an account-specific local database and resume syncing when that account signs in again. Another account can sign in on the same phone using its own device registration.

Individual member assessments remain authoritative. When a member has no individual assessment, the active event's household report supplies the displayed status, explicitly labeled **Household report**. An old event's household report does not carry into a new event.

## Live data checked

For HH-2026-8561, all 11 members display Unsafe based on the household report. The canonical address is Purok 3, Paglaum. Address record 16 contained temporary test wording; its full_address was updated to the same canonical value without inventing a street or house number.

Dispatch 12 lists Julius R. Ledesma as assigned to HH-2026-8561. His overview now includes the household, address and pending queue; the assigned-household field-report endpoint returns 200. The other selected responder has an explicitly empty household list and does not inherit Julius's target.

Applied these specific migrations to the local running backend:

- 2026_10_17_000001_add_assignment_field_report_records (previously pending; required for report submission).
- 2026_10_23_000001_allow_household_queue_field_reports (changes the old one-report-per-dispatch constraint).

## Verification

48 relevant backend tests passed (173 assertions across the targeted integration suite and queue unit tests). 18 mobile tests passed, including SQLite account isolation, offline logout, shared-phone login and cancellation of requests that could cross account sessions. TypeScript passed. Targeted mobile lint has zero errors and one existing array-style warning. The web production build passed.

A broader backend test selection also reported seven failures outside the repaired flows: two status-catalog permission expectations, two geographic fixture tests without sitios, a response-contact fixture without addresses, a team-code expectation, and a resource-request urgency expectation. These remain unresolved. An additional assignment transaction regression found during that run was corrected and its test passes.

No Android phone was attached. Physical-device sign-in, logout and navigation taps still need checking after installation. No live household report or dispatch completion was submitted during verification.

## Android update

Build 21 uses the existing release identity and certificate so it can update the installed app. The build script now writes its ASCII-only Gradle signing configuration without a UTF-8 BOM, fixing a Windows PowerShell compilation failure. Install the new APK over the existing app; do not clear app storage, which would erase pending offline events.

Build 21 completed successfully. APK ZIP integrity and embedded queue/session fixes passed. SHA-256: dcb2f1f73ea960d590a07193d1bca3d42d0a3936a55c6bc897012707986dbfe5. Download: http://100.121.155.55:8000/downloads/index.html
