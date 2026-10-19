# RESQPERATION system use cases

Updated: 20 October 2026. Scope: the Laravel backend, HQ web application, household/rescuer Android application, and connected partner workflows in the current working tree.

This is the canonical use-case reference for the entire system. It describes current behavior and explicitly marks incomplete workflows. It does not certify that the latest source is installed on every phone or that all external services have passed deployment testing. See the [must-haves implementation audit](MUST_HAVES_IMPLEMENTATION_AUDIT.md) for evidence, test results, and outstanding issues.

## System boundary and actors

RESQPERATION coordinates barangay disaster alerts, household safety reporting, rescue operations, requests, and operational documentation. It uses shared household/member records and exchanges information with partner systems; it does not replace their census, evacuation-center, or inventory ownership.

| Actor | Responsibilities and access |
| --- | --- |
| Household resident | Signs into the mobile application, maintains permitted household/member information, reports safety status, views alerts/routes, and manages trusted-household connections. |
| Rescuer | Uses the mobile application for assignments, GPS/navigation, check-ins, assessments, field reports, resource requests, and internet PTT communication. |
| HQ personnel | Uses authorized web modules for monitoring, reporting, dispatch, and coordination. Available modules and request approval rights depend on role configuration. |
| HQ leader | Coordinates HQ operations and normally has request approval rights and personnel access. Leader eligibility is checked by the backend. |
| Super administrator | Has system administration access, including personnel/role configuration and inquiry management. |
| Authorized evacuation verifier | Intended operator of evacuation verification. Backend endpoints authorize HQ/rescuer roles; a complete dedicated scanner/operator UI is still missing. This is not a confirmed separate account role. |
| SafeTrack | Supplies linked household/census information and participates in external report exchange. |
| EvaTrack | Participates in evacuation/report information exchange and external resource/personnel request intake. |
| TrackingAid | Receives validated forwarded requests and supplies compatible resource/request reports. |
| OneSignal / Android push services | Deliver notifications to subscribed devices. Delivery requires provider configuration, permission, and internet. |
| Weather, map, and routing providers | Supply weather snapshots/advisories, map tiles, and route calculations where configured. |
| SMS gateway | Supports configured inbound SMS processing; it is not a substitute for every mobile workflow. |

The database, queue workers, scheduler, and Tailscale/LAN connection are supporting infrastructure rather than human actors. A working local database alone does not make the API reachable from a phone.

## Access and shared rules

- Users authenticate against database-backed accounts. Household access requires a linked household; rescuer mobile sessions also use device binding.
- The standalone Android APK supports both household and rescuer accounts. It does not require Expo Go, Metro, or scanning a development QR code.
- Web module permissions and request approval permissions are checked by the backend. A visible menu or an authenticated session does not grant every operation.
- Event-scoped operations must reference the correct disaster event. Household, member, sitio, purok, team, and resource identifiers must come from stored records/options.
- API writes require validation and authorization. Operations with implemented idempotency support protect retries; this must not be generalized to every endpoint or report request.
- Realtime updates, queue processing, scheduled reminders, push delivery, and partner synchronization depend on their configured services running.
- “Implemented” below means a connected source workflow exists. “Partial” identifies a missing flow, relevant regression, or unverified completion gate. “Conditional” identifies an integration/deployment dependency.

## Use-case catalog

### Accounts and administration

| ID | Use case | Primary actor | Current scope |
| --- | --- | --- | --- |
| UC-01 | Sign in, restore an authorized session, and sign out | All users | Implemented; web/mobile entry points and role-specific access. Mobile logout also updates push subscription state. |
| UC-02 | Maintain personal profile and password | All users | Implemented within each role's permitted fields. |
| UC-03 | Configure security questions and recover access | Account holder | Implemented question/verification/reset endpoints; requires configured recovery information. |
| UC-04 | Manage rescuer accounts, teams, and validation state | Authorized HQ/admin | Partial against full registration requirements: account/team/cluster and certification reference exist; distinct official-ID capture/review is not established. |
| UC-05 | Manage HQ accounts, roles, module access, and approval rights | Authorized leader/admin | Implemented; enforcement follows role policy and account eligibility. |
| UC-06 | Maintain barangay operational profile and lookup options | Authorized HQ/admin | Implemented within available profile, disaster-type, status-type, team, and request-type controls. |
| UC-07 | Submit, review, and archive inquiries | Public sender; super administrator | Implemented public intake and restricted management. |

### Household mobile application

| ID | Use case | Primary actor | Current scope |
| --- | --- | --- | --- |
| UC-08 | Complete household setup and maintain profile/geotag | Household resident | Implemented; linked household/member records, address/location, permitted member changes. |
| UC-09 | Receive and review disaster alerts | Household resident | Implemented; OneSignal integration, latest alert card, and recent-broadcast history modal. Push delivery is conditional. |
| UC-10 | Report household/member safety status and review history | Household resident | Implemented; four status buttons reflect the latest applicable broadcast's configured labels/order. |
| UC-11 | Connect and assist a trusted household | Household resident | Implemented lookup, connection response/removal, PIN verification, and authorized member-status reporting. |
| UC-12 | Find an evacuation center and follow a route | Household resident | Implemented online map/route and foreground GPS following. Fully verified offline household navigation is not complete. |
| UC-13 | Display resident QR and verify evacuation arrival | Resident; authorized verifier | Partial: QR display and backend verification/check-in exist; complete staff scan/entry UI is missing. |
| UC-14 | Continue household reporting without connectivity | Household resident | Partial: no established persistent household overview cache or durable status-submission outbox. |

### Rescuer mobile application

| ID | Use case | Primary actor | Current scope |
| --- | --- | --- | --- |
| UC-15 | View assigned incidents and household details | Rescuer | Implemented assignment list/detail and linked household/member views. |
| UC-16 | Navigate an assignment and share operational location | Rescuer | Implemented online routes, foreground GPS following, assignment status and location updates. |
| UC-17 | Download navigation data and record an offline trip | Rescuer | Partial end-to-end verification: SQLite/trip/outbox and offline-map code exist, but compatible deployed packs, reachable assets, cached routes, and physical offline testing remain gates. |
| UC-18 | Check in and assess household members | Rescuer | Implemented check-ins and per-member assessment tied to operational records. |
| UC-19 | Submit field findings and damage assessments | Rescuer | Implemented incident details, injuries/deaths, recorded property damage, notes, and timestamps. |
| UC-20 | Communicate using PTT/radio clips | Rescuer; HQ monitor | Implemented internet-based sessions, voice clips/signaling, and monitoring. It is not offline hardware radio. |
| UC-21 | Request logistics and track/cancel an eligible request | Rescuer | Partial: connected workflow exists, but the audited resource-request test loses the submitted urgency value. |

### HQ operations and documentation

| ID | Use case | Primary actor | Current scope |
| --- | --- | --- | --- |
| UC-22 | Monitor dashboard, maps, weather, and operational updates | Authorized HQ user | Implemented summaries, maps, weather workspace, notifications, and realtime/fallback refresh paths. |
| UC-23 | Create/update a disaster event and broadcast instructions | Authorized HQ user | Implemented event-scoped alerts, selected report-status options, and queued push sending. |
| UC-24 | Classify event severity and annotate affected areas | Authorized HQ user | Partial: event/broadcast severity exists; affected-area annotations do not establish the exact same four-level classification per area. |
| UC-25 | Review and confirm household status reports | Authorized HQ user | Implemented household/status views, history, and confirmation workflow. |
| UC-26 | Prioritize households and dispatch a rescue team | Authorized HQ user | Implemented HQ-assisted selection, assignment, and monitoring; not guaranteed automatic optimal dispatch. |
| UC-27 | Monitor field reports, team progress, and communications | Authorized HQ user | Implemented operational status/location/report and radio views, subject to connectivity. |
| UC-28 | Prepare, save, lock, and export a situation report | Authorized HQ user | Implemented structured six-finding report and saved snapshots. |
| UC-29 | Escalate an incident using an exported report | Authorized HQ user | Implemented manual export/handoff scope; automatic city receipt/acknowledgment is not established. |
| UC-30 | Browse, export, and manage incident archives | Authorized HQ user | Partial: archive flows exist; an authenticated legacy-admin contract test returns 403 instead of 200 and needs policy/fixture reconciliation. |

### Requests and partner exchange

| ID | Use case | Primary actor | Current scope |
| --- | --- | --- | --- |
| UC-31 | Receive and record an external resource/personnel request | Authorized partner system | Implemented authenticated intake, validation, and recorded request identity. |
| UC-32 | Review, validate, return, or cancel a request | Authorized HQ approver | Implemented controlled review and supported transitions; approval permission is separate from module visibility. |
| UC-33 | Forward a validated request and track fulfillment | HQ approver; TrackingAid | Partial end-to-end: durable forwarding/synchronization and mirrors exist; live partner-backed fulfillment has not been established. |
| UC-34 | Request a RESQPERATION report and decide its release | Partner system; HQ reviewer | Implemented pending request, manual acceptance/rejection, frozen accepted snapshot, signed status/download URLs, and operator renewal. |
| UC-35 | Request and review a partner report | Authorized HQ user; partner system | Implemented provider selection and configured partner-client flows; availability depends on each partner service. |
| UC-36 | Export selected situation findings | Authorized HQ user | Implemented selected sections and PDF/CSV/XLSX exports using recorded data and permitted scope. |

## Detailed operational flows

### UC-01: Authenticate and enter the correct workspace

**Preconditions:** The API is reachable, the database is available, and an eligible account exists. The installed APK's Server settings point to the backend API, not the HQ Vite address or the phone's own IP.

**Trigger:** A user submits a supported account identifier and password.

1. The backend verifies credentials, account state, role, and applicable device restrictions.
2. It issues an authorized session/token and identifies the user's permitted workspace.
3. Mobile opens household or rescuer screens; web applies module permissions.
4. The mobile client registers eligible push subscription information when available.
5. Sign-out ends the session and updates mobile subscription state.

**Alternatives:** Invalid credentials, inactive accounts, missing household links, device restrictions, and network failures must not grant access. Recovery follows UC-03 when recovery information is configured.

**Postcondition:** The user has only the access allowed by current backend policy.

### UC-08, UC-10, UC-11: Maintain and report household safety

**Preconditions:** A resident is signed in with a linked household. Status submission uses an applicable event/broadcast and valid stored member/status identifiers.

1. The resident completes required setup and reviews household/member information.
2. The application loads current alerts and the configured four status choices.
3. The resident selects a household/member status and supplies available location information.
4. The backend validates the status against the applicable broadcast, stores the report/history, and makes the update available to HQ.
5. The resident can submit a later update and review recorded history.
6. For another household, the resident first completes the supported trusted connection and PIN/authorization checks, then uses the permitted member-reporting flow.

**Alternatives:** Denied/unavailable GPS does not justify inventing a fix. Rejected or unauthorized trusted connections cannot be used to report on another household. A failed network submission must not be described as successfully synchronized; durable household offline queuing remains incomplete.

**Postcondition:** Accepted reports are stored with their event/member/household context and timestamps.

### UC-09, UC-23: Broadcast the configured alert and status choices

**Preconditions:** An authorized HQ user selects an event. Push credentials, workers, subscribed devices, Android permission, and internet are required for native delivery.

1. HQ sets the broadcast title, instructions, target scope, and four report-status choices.
2. Submission stores the event-scoped broadcast and its configured status snapshot.
3. The backend schedules/sends push containing the alert and report-status prompt with the selected labels.
4. The mobile notification handler displays received foreground notifications and requests refreshed application data.
5. Household mobile shows one latest alert card; the history icon opens earlier broadcasts.
6. The household's four clickable status choices use the applicable backend broadcast configuration, including its order.

**Alternatives:** “Accepted” by OneSignal means provider acceptance, not confirmed display on every device. Logged-out/unsubscribed devices, permissions, provider configuration, and connectivity can prevent delivery. Refreshing the in-app alert list and receiving an Android notification are separate outcomes.

**Postcondition:** The saved broadcast is available to authorized readers. Device delivery remains subject to provider/device conditions. The four in-app status buttons are not four Android notification action buttons.

### UC-12, UC-16, UC-17: Navigate with GPS

**Preconditions:** A destination or assignment is selected. Online routing/assets must be reachable unless compatible offline data has been prepared. GPS following requires foreground permission and an accepted fix.

1. The user opens the selected route and views its map/directions.
2. When GPS is available, the lightweight map follows the current fix and initially zooms closer to street level.
3. Later fixes retain the user's zoom; dragging pauses following, and the Follow GPS control recenters.
4. Foreground tracking pauses when the relevant lightweight map/app is inactive and cleans up when stopped.
5. Rescuer operational location/status updates use authorized assignment endpoints.
6. The separate rescuer offline-navigation flow uses prepared map data, stored trip records, and its synchronization outbox where configured.

**Alternatives:** The map must handle denied permission or an unavailable fix. Online route availability does not prove offline operation. Household Leaflet assets/tiles and online routing are not a complete offline household solution. Rescuer offline pack compatibility, phone-reachable asset URLs, route caching, and airplane-mode verification remain required.

**Postcondition:** Available location is represented without adding a heavy street-view engine. Fully verified offline navigation is not claimed. See [GPS/offline checks](GPS_FOLLOW_AND_OFFLINE_CHECKS.md) and [offline navigation progress](OFFLINE_NAVIGATION_PROGRESS.md).

### UC-13: Verify arrival at an evacuation center

**Preconditions:** A resident has a valid generated QR payload; an authorized HQ/rescuer verifier and valid center/event records are required.

1. The resident opens their evacuation QR.
2. The intended operator scans or enters the payload and checks the resident/household and destination context.
3. Backend verification/check-in endpoints validate the submitted information and record an authorized arrival.

**Gap:** Step 2 does not have a proven connected staff scanner/entry interface. QR display and backend endpoints alone do not complete this use case.

### UC-15, UC-18, UC-19, UC-26, UC-27: Dispatch and report a rescue

**Preconditions:** HQ has an event, households requiring attention, and an eligible rescue team/account. The rescuer has authorized access to the assignment.

1. HQ reviews household reports, location, priority, and team availability.
2. HQ creates the assignment and selects the household/team.
3. The rescuer opens the assignment and navigates to the location.
4. The rescuer updates operational status/location and records a check-in.
5. The rescuer assesses household members and submits field findings, including recorded injuries/deaths, property damage, and notes where applicable.
6. The backend validates and stores the report, assessment, and timestamps; HQ reviews progress and recorded outcomes.
7. Internet PTT clips/signaling support communication where available.

**Alternatives:** Unauthorized assignment changes are rejected. Available retry/idempotency handling does not establish a durable offline payload queue for every field-report operation. No automatic optimal-team or safe-route guarantee is implied.

**Postcondition:** Accepted assignment updates and field records support operational monitoring and reporting.

### UC-21, UC-31, UC-32, UC-33: Coordinate requests through fulfillment

**Preconditions:** A rescuer/HQ user has applicable access, or an external caller supplies configured intake authentication. Referenced request types, items, and locations must be valid.

1. A rescuer/HQ user creates a request, or a partner submits through authorized external intake.
2. The backend validates and records origin, requested personnel/resources, location, and tracking information.
3. An HQ user with approval rights reviews it and validates, returns, or performs another permitted transition.
4. An approved/validated request is forwarded through the configured TrackingAid handoff and durable synchronization path.
5. HQ reviews mirrored/tracked progress and records or receives completion through the supported workflow.

| Stored status | Operational meaning |
| --- | --- |
| `needs_validation` | Pending review |
| `verified` | Validated; approval equivalent, not a claim that the UI literally says “Approved” |
| `forwarded` | In progress / forwarded for fulfillment |
| `fulfilled` | Completed |
| Returned / cancelled states | Require the corresponding supported action and business context |

**Alternatives and limits:** Failed partner synchronization needs worker/retry handling and must not be presented as completed distribution. Resource requests cover personnel and resource items, including equipment/goods and vehicle support; these are not necessarily separate top-level categories. Duplicate controls in request intake must not be assumed for report-request references. The audited mobile urgency persistence regression and unverified live full fulfillment cycle remain open.

**Postcondition:** Accepted transitions preserve a traceable request lifecycle. Full partner-backed completion still requires integration acceptance testing.

### UC-28, UC-29, UC-36: Produce and hand off situation findings

**Preconditions:** An authorized operator selects an event and valid scope/sections. Partner data sources must be configured to contribute synchronized findings.

1. The operator reviews recorded event, household, damage, dispatch, evacuation-center, and resource information.
2. Report generation synchronizes configured partner findings and assembles the requested sections without inventing missing values.
3. The operator saves/locks the report as supported, preserving its snapshot.
4. The operator exports PDF, CSV, or XLSX and manually hands the document to the receiving office when escalation is required.

**Report scope:** Six findings: Situation Overview; Status of Affected Areas and Population; Damage Assessment; Search, Rescue and Retrieval Operations; Status of Evacuation Centers; Resources Status. Missing information remains unrecorded, and known zero values remain zero. Requested quantities are not automatically available stock; occupancy is not automatically displaced population.

**Postcondition:** A recorded/exported report is available for coordination. Sending an export does not establish automatic city-system receipt.

### UC-34, UC-35: Exchange reports with external systems

**Preconditions:** The selected partner is configured and reachable. External requests use the appropriate report authentication, distinct from resource-intake credentials.

1. A partner loads report options and submits a versioned request with valid event/sitio, sections, format, and purpose.
2. RESQPERATION returns a pending request and signed status link.
3. HQ opens External Reports → Incoming requests, reviews the request, and accepts it or rejects it with a reason.
4. Acceptance produces a frozen report snapshot and time-limited signed download. The partner polls its returned status URL and downloads the accepted file.
5. An operator can renew an expired file from the accepted snapshot; a new current-data report needs a new reviewed request.
6. In the opposite direction, HQ uses Request a report, selects SafeTrack/EvaTrack/TrackingAid, and follows that provider's configured request/retrieval flow.

**Alternatives:** Pending review has no guaranteed approval time. Rejection stops the release. Signed URLs must be used as returned. A repeated reference is not report-request deduplication, and blindly repeating POST can create another request.

**Contract detail:** Current report API version is `4.0`. Select 1–6 section keys from options: `I`, `II`, `IV`, `V`, `VI`, `VII`; `III` is retired. Printed section numerals differ from selection keys. Partner formats are `pdf`, `csv`, and `xlsx`. See the [external report API guide](../backend-laravel/docs/RESQPERATION_EXTERNAL_REPORTS_API.md).

### UC-30: Retrieve incident archives

**Preconditions:** An authenticated user has reports/documentation access and the operation is allowed by current policy.

1. The user selects the relevant archive group/event and filters recorded material.
2. The system returns authorized event, status, dispatch, communication, request, or report records.
3. The user exports records or performs a permitted archive-management action.

**Open verification issue:** The audit's legacy-admin contract fixture received HTTP 403 instead of 200. Reconcile the fixture and current role policy before marking the requirement complete; this does not prove all current HQ roles are blocked.

## Must-haves traceability

The verdicts below carry forward the dated audit, rather than treating this document as a new deployment test.

| Checklist | Related use cases | Audit verdict |
| --- | --- | --- |
| A1 Household Profile | UC-08 | Implemented |
| A2 Disaster Alerts and Notifications | UC-09, UC-23 | Implemented |
| A3 Household Status Reporting | UC-10, UC-11, UC-25 | Implemented |
| A4 Evacuation Support and Mapping | UC-12 | Implemented, online scope |
| A5 Offline and Connectivity Features | UC-14, UC-17 | Partial |
| A6 Evacuation Verification | UC-13 | Partial |
| B1 Registration and Validation | UC-04 | Partial |
| B2 Authentication and Access Control | UC-01, UC-03, UC-05 | Implemented |
| B3 Profile Management | UC-02 | Implemented |
| B4 Spatial and Mapping Functionalities | UC-16, UC-22 | Implemented, online scope |
| C1 Incident Viewing and Navigation | UC-15, UC-16 | Implemented, online scope |
| C2 Field Communication | UC-20 | Implemented, internet PTT scope |
| C3 Household Rescue Verification | UC-18 | Implemented |
| C4 Field Reporting | UC-19 | Implemented |
| C5 Operational Logging | UC-18, UC-19, UC-27 | Implemented |
| C6 Logistics Request | UC-21 | Partial |
| D1 Disaster Alert Management | UC-23 | Implemented |
| D2 Rescue Team Dispatch | UC-26 | Implemented |
| D3 Incident Monitoring | UC-22, UC-27 | Implemented |
| D4 Household Status Monitoring | UC-25 | Implemented |
| D5 Severity Classification | UC-24 | Partial |
| D6 Field Operations Monitoring | UC-27 | Implemented |
| D7 Situation Reporting | UC-28, UC-36 | Implemented |
| D8 Incident Escalation | UC-29 | Implemented, manual export scope |
| D9 Incident Archiving | UC-30 | Partial |
| R1 External request intake | UC-31 | Implemented |
| R2 Personnel/equipment/goods requests | UC-21, UC-31, UC-32 | Implemented |
| R3 Intake through TrackingAid fulfillment | UC-31, UC-32, UC-33 | Partial |
| R4 Verification and controlled distribution | UC-31, UC-32, UC-33 | Implemented coordination controls |
| R5 Request status tracking | UC-32, UC-33 | Implemented semantic status mapping |

## Implementation references and verification

| Area | Source/reference |
| --- | --- |
| API endpoints and role middleware | [Laravel API routes](../backend-laravel/routes/api.php) |
| Web modules and approval policy | [WebAccessPolicy](../backend-laravel/app/Services/Web/WebAccessPolicy.php) |
| Household mobile entry | [Household workspace](../frontend-mobile/app/household/index.tsx) |
| Rescuer mobile entry | [Rescuer workspace](../frontend-mobile/app/rescuer/index.tsx) |
| HQ operational pages | [Web pages](../frontend-web/src/pages) |
| Household overview/trusted workflows | [HouseholdMobileService](../backend-laravel/app/Services/HouseholdMobileService.php) |
| Broadcast writing | [DisasterBroadcastWorkflow](../backend-laravel/app/Services/Web/DisasterBroadcastWorkflow.php) |
| Push provider | [OneSignalNotificationService](../backend-laravel/app/Services/Shared/OneSignalNotificationService.php) |
| External reports UI | [ExternalReportsPage](../frontend-web/src/pages/ExternalReportsPage.jsx) |
| Partner report contract | [External report API v4.0](../backend-laravel/docs/RESQPERATION_EXTERNAL_REPORTS_API.md) |
| Deployment and groupmate setup | [Android release guide](../frontend-mobile/ANDROID_RELEASE.md), [first-run setup](../frontend-mobile/FIRST_RUN_SETUP.md) |
| Requirement evidence and regressions | [Must-haves audit](MUST_HAVES_IMPLEMENTATION_AUDIT.md) |

Acceptance testing should verify allowed and denied roles, persistence after reload, failure/retry behavior, and event/household isolation. Real-device push receipt, airplane-mode navigation, evacuation operator UI completion, and live partner fulfillment require their own checks. This documentation update does not resolve the seven partial must-haves or install a new APK.
