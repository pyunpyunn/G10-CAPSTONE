# Use-case narratives - entire RESQPERATION system

The 36 canonical use cases below are covered by the unified narrative map, the end-to-end sequence and 9 focused sequence diagrams in `narratives/`. These are requirement/behavior narratives, not declarations that all deployment gates are complete. Account recovery source files have concurrent work; exact current recovery details should be checked against the account-type-specific implementation.

Common rules: protect role/module/device/household scope; use stored identifiers; validate all writes; report network/provider failures honestly; expose only permitted data. GAP and conditional paths remain explicitly identified.

## UC-01: Sign in, restore an authorized session, and sign out

- **Actor:** All users.
- **Precondition:** Eligible assigned account; linked household/device where required.
- **Main flow:** Submit credentials; validate active account and scope; open permitted workspace; sign out.
- **Alternative / failure:** Reject invalid/inactive accounts; offline restoration uses an existing saved mobile session only.
- **Postcondition:** Authorized session or explicit rejection.

## UC-02: Maintain personal profile and password

- **Actor:** All users.
- **Precondition:** Authenticated account.
- **Main flow:** Open own profile; change allowed fields/password; validate and save.
- **Alternative / failure:** Reject invalid input or changes outside own permissions.
- **Postcondition:** Permitted profile changes persisted.

## UC-03: Configure security questions and recover access

- **Actor:** Account holder.
- **Precondition:** Configured recovery information and supported account type.
- **Main flow:** Start recovery; complete required verification; choose password; save.
- **Alternative / failure:** Invalid/expired verification must not reset a password.
- **Postcondition:** Verified access recovery or unchanged account.

## UC-04: Manage rescuer accounts, teams, and validation state

- **Actor:** Authorized HQ/admin.
- **Precondition:** Personnel-management permission.
- **Main flow:** Create/update rescuer; assign team; record validation; activate/deactivate.
- **Alternative / failure:** Reject unauthorized/duplicate data; official-ID capture/review remains a gap.
- **Postcondition:** Eligible responder account and recorded validation state.

## UC-05: Manage HQ accounts, roles, module access, and approval rights

- **Actor:** Authorized leader/admin.
- **Precondition:** Operations/Admin or explicit personnel-management entitlement.
- **Main flow:** Create/update HQ role and module/approval settings; enforce backend policy.
- **Alternative / failure:** Reject management actions outside granted rights.
- **Postcondition:** Stored role/module configuration.

## UC-06: Maintain barangay operational profile and lookup options

- **Actor:** Authorized HQ/admin.
- **Precondition:** Authorized configuration access.
- **Main flow:** Maintain permitted barangay profile and disaster/status/request lookup choices.
- **Alternative / failure:** Reject invalid or unauthorized configuration; conditional tables require deployment.
- **Postcondition:** Saved available configuration.

## UC-07: Submit, review, and archive inquiries

- **Actor:** Public sender; super administrator.
- **Precondition:** Public sender for intake; Operations/Admin for management.
- **Main flow:** Submit validated inquiry; record receipt; administrator reviews and updates state.
- **Alternative / failure:** Throttle/reject malformed intake; deny non-admin management.
- **Postcondition:** Stored inquiry and authorized status/history.

## UC-08: Complete household setup and maintain profile/geotag

- **Actor:** Household resident.
- **Precondition:** Authenticated linked household and selected device owner.
- **Main flow:** Review member/address data; complete setup; obtain permitted GPS; save allowed profile/geotag changes.
- **Alternative / failure:** Handle denied/unavailable GPS; do not modify another household.
- **Postcondition:** Linked household setup and location recorded.

## UC-09: Receive and review disaster alerts

- **Actor:** Household resident.
- **Precondition:** Current broadcast; subscribed device and configured delivery for push.
- **Main flow:** HQ publishes; provider attempts delivery; resident reviews latest alert/history.
- **Alternative / failure:** No connectivity/permission/provider failure can prevent push; in-app review remains separate.
- **Postcondition:** Saved event-scoped alert; device delivery only when successful.

## UC-10: Report household/member safety status and review history

- **Actor:** Household resident.
- **Precondition:** Linked household/member and applicable event/status choices.
- **Main flow:** Select allowed status; include available location; validate and save; review history.
- **Alternative / failure:** Rejected/offline submission is not successful synchronization.
- **Postcondition:** Current safety record and timestamped history.

## UC-11: Connect and assist a trusted household

- **Actor:** Household resident.
- **Precondition:** Resident and supported trusted connection.
- **Main flow:** Find/invite household; recipient decides; verify PIN; report permitted member status.
- **Alternative / failure:** Reject inactive/unapproved connection or incorrect PIN.
- **Postcondition:** Authorized trusted relationship or member report.

## UC-12: Find an evacuation center and follow a route

- **Actor:** Household resident.
- **Precondition:** Valid household/destination coordinates and available online routing.
- **Main flow:** List available centers; choose destination; display route/distance/ETA and GPS follow.
- **Alternative / failure:** Handle routing/GPS failure; offline maps are not guaranteed.
- **Postcondition:** Displayed available evacuation route.

## UC-13: Display resident QR and verify evacuation arrival

- **Actor:** Resident; authorized verifier.
- **Precondition:** Resident QR and authorized verifier/center context.
- **Main flow:** Show/save QR; verifier scans/enters identity; API validates and records arrival.
- **Alternative / failure:** Complete staff scanner/entry UI remains a gap; reject invalid identity/center.
- **Postcondition:** Accepted evacuation record when supported interface completes flow.

## UC-14: Continue household reporting without connectivity

- **Actor:** Household resident.
- **Precondition:** Existing authenticated mobile account and previously synchronized data.
- **Main flow:** Restore account-scoped full-page snapshot; show Offline mode/last-sync; refresh after recovery.
- **Alternative / failure:** No prior sync means no historical page; household durable write queue/replay remains missing.
- **Postcondition:** Cached read access, without claiming queued household reporting.

## UC-15: View assigned incidents and household details

- **Actor:** Rescuer.
- **Precondition:** Authenticated assigned rescuer/team.
- **Main flow:** Read own assignments and linked household/member details.
- **Alternative / failure:** Deny another team/account scope; show stored data when available offline.
- **Postcondition:** Permitted assignment and household information displayed.

## UC-16: Navigate an assignment and share operational location

- **Actor:** Rescuer.
- **Precondition:** Own assignment, supported coordinates and GPS permission.
- **Main flow:** Calculate route; follow position; update operational location/progress.
- **Alternative / failure:** Reject invalid transitions; handle unavailable route/GPS; offline assets separate.
- **Postcondition:** Saved allowed progress/location and displayed available route.

## UC-17: Download navigation data and record an offline trip

- **Actor:** Rescuer.
- **Precondition:** Compatible navigation pack, scoped cached route and device-bound session.
- **Main flow:** Download/verify pack; start trip; save points/outbox; sync supported events after reconnect.
- **Alternative / failure:** Missing/incompatible assets or rejected events prevent complete offline workflow.
- **Postcondition:** Local trip evidence and acknowledged supported server events.

## UC-18: Check in and assess household members

- **Actor:** Rescuer.
- **Precondition:** Own assignment/household scope and valid members.
- **Main flow:** Check in; assess each member; submit valid household/member outcome.
- **Alternative / failure:** Reject invalid identities/statuses or missing assessments.
- **Postcondition:** Recorded visit and member assessment evidence.

## UC-19: Submit field findings and damage assessments

- **Actor:** Rescuer.
- **Precondition:** Authenticated rescuer and valid event/household context.
- **Main flow:** Enter incident details, injuries/deaths, damage and notes; submit validated report.
- **Alternative / failure:** Reject incomplete/invalid scope; do not claim failed network report saved.
- **Postcondition:** Persisted field report and linked outcomes.

## UC-20: Communicate using PTT/radio clips

- **Actor:** Rescuer; HQ monitor.
- **Precondition:** Internet connection, microphone permission and valid radio session.
- **Main flow:** Start transmission; record/upload clip/signals; listen through authorized feed; stop session.
- **Alternative / failure:** No connection/microphone or conflicting transmission prevents communication.
- **Postcondition:** Recorded supported voice communication, not hardware radio.

## UC-21: Request logistics and track/cancel an eligible request

- **Actor:** Rescue Team Leader for mobile submission; authorized HQ for review.
- **Precondition:** Team Leader role for mobile submission.
- **Main flow:** Select personnel/equipment/supply items; submit; review status; cancel if allowed.
- **Alternative / failure:** Reject non-leader submission or ineligible cancel; audit urgency issue needs current verification.
- **Postcondition:** Recorded request and permitted lifecycle changes.

## UC-22: Monitor dashboard, maps, weather, and operational updates

- **Actor:** Authorized HQ user.
- **Precondition:** HQ role with requested module access.
- **Main flow:** Open dashboard/maps/weather/activity; receive authorized refreshes and notifications.
- **Alternative / failure:** Deny unavailable modules; degraded realtime uses configured fallback.
- **Postcondition:** Current permitted operational view.

## UC-23: Create/update a disaster event and broadcast instructions

- **Actor:** Authorized HQ user.
- **Precondition:** Operations/Admin or chair publication authority.
- **Main flow:** Create/update event; compose instructions/scope/status choices; save and request delivery.
- **Alternative / failure:** Reject unauthorized publisher; provider acceptance is not device receipt.
- **Postcondition:** Saved broadcast/event and delivery attempt state.

## UC-24: Classify event severity and annotate affected areas

- **Actor:** Authorized HQ user.
- **Precondition:** Appropriate response/geospatial permissions.
- **Main flow:** Review incident; choose event severity; annotate affected area/priority.
- **Alternative / failure:** Exact four-level per-area classification is a required gap in reviewed audit.
- **Postcondition:** Stored supported severity/priority; unmet exact requirement remains visible.

## UC-25: Review and confirm household status reports

- **Actor:** Authorized HQ user.
- **Precondition:** Authorized household review/confirmation access.
- **Main flow:** Read event/member/household history; inspect evidence; confirm permitted status.
- **Alternative / failure:** Reject invalid scope or unauthorized confirmation.
- **Postcondition:** Recorded review/confirmation and updated safety view.

## UC-26: Prioritize households and dispatch a rescue team

- **Actor:** Authorized HQ user.
- **Precondition:** Authorized dispatcher, available team and registered household/area.
- **Main flow:** Review priorities/welfare queue; select team/responders; confirm assignment.
- **Alternative / failure:** Reject unavailable or out-of-scope selection; dispatch is HQ-assisted.
- **Postcondition:** Saved assignment linked to team, event and target.

## UC-27: Monitor field reports, team progress, and communications

- **Actor:** Authorized HQ user.
- **Precondition:** Authorized HQ field-operation access.
- **Main flow:** Read field reports, dispatch progress/location and communication feed.
- **Alternative / failure:** Provider/network/realtime failure may delay updates.
- **Postcondition:** Permitted recorded operational evidence displayed.

## UC-28: Prepare, save, lock, and export a situation report

- **Actor:** Authorized HQ user.
- **Precondition:** Authorized report role and event/geographic scope.
- **Main flow:** Select sections; synchronize configured sources; generate/save/lock snapshot; export.
- **Alternative / failure:** Unavailable partner data is flagged; missing source-only tables need deployment.
- **Postcondition:** Saved report with available evidence and export.

## UC-29: Escalate an incident using an exported report

- **Actor:** Authorized HQ user.
- **Precondition:** Authorized report/export access.
- **Main flow:** Generate appropriate export and manually deliver to city authority.
- **Alternative / failure:** Do not infer automatic receipt/acknowledgment from export.
- **Postcondition:** Export available for documented manual escalation.

## UC-30: Browse, export, and manage incident archives

- **Actor:** Authorized HQ user.
- **Precondition:** Authorized archive/report-documentation access.
- **Main flow:** Browse/filter historical records; export; manage saved groups/records within policy.
- **Alternative / failure:** Legacy authorization failure in audit was not re-evaluated for this diagram.
- **Postcondition:** Permitted historical record access and managed groups.

## UC-31: Receive and record an external resource/personnel request

- **Actor:** Authorized partner system.
- **Precondition:** Authenticated authorized partner and agreed request payload.
- **Main flow:** Receive intake; validate sender/identity; detect references; persist request.
- **Alternative / failure:** Reject unauthenticated/invalid/duplicate intake per workflow.
- **Postcondition:** Recorded external request or explicit controlled rejection.

## UC-32: Review, validate, return, or cancel a request

- **Actor:** Authorized HQ approver.
- **Precondition:** HQ approval permission and existing eligible request.
- **Main flow:** Review details; validate/return/cancel using supported transitions; save history.
- **Alternative / failure:** Module visibility does not grant approval; invalid transitions rejected.
- **Postcondition:** Recorded decision and request state.

## UC-33: Forward a validated request and track fulfillment

- **Actor:** HQ approver; TrackingAid.
- **Precondition:** Validated request and configured TrackingAid connection.
- **Main flow:** Forward recorded payload; store reference; query mirror until confirmed fulfillment.
- **Alternative / failure:** Partner unavailability causes pending/retry; live full cycle needs acceptance.
- **Postcondition:** Confirmed handoff/state only when acknowledged.

## UC-34: Request a RESQPERATION report and decide its release

- **Actor:** Partner system; HQ reviewer.
- **Precondition:** Authorized partner request and HQ report-review permission.
- **Main flow:** Record request; HQ accepts/rejects; freeze accepted snapshot; issue controlled status/download.
- **Alternative / failure:** Missing source-only request table blocks path; reject/expire unauthorized download.
- **Postcondition:** Recorded release decision and authorized file when accepted.

## UC-35: Request and review a partner report

- **Actor:** Authorized HQ user; partner system.
- **Precondition:** Configured reachable partner report provider and permitted HQ access.
- **Main flow:** Select provider/scope; request report; await compatible authorized result; review.
- **Alternative / failure:** Partner pending/rejection/error must be displayed, not fabricated.
- **Postcondition:** Available partner evidence or explicit unavailability.

## UC-36: Export selected situation findings

- **Actor:** Authorized HQ user.
- **Precondition:** Permitted saved/generated report and selected sections.
- **Main flow:** Select allowed findings; export PDF/CSV/Excel with scope validation.
- **Alternative / failure:** Reject unauthorized sections/scope or unavailable evidence.
- **Postcondition:** Export containing selected permitted recorded findings.
