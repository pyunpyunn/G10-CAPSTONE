# RESQPERATION entire system use case diagram

Source: [RESQPERATION_ENTIRE_USE_CASE.mmd](RESQPERATION_ENTIRE_USE_CASE.mmd).

Paste the source into https://mermaid.live/edit. The native use-case syntax requires Mermaid 12 or later. The companion `RESQPERATION_ENTIRE_USE_CASE_COMPATIBLE.mmd` uses flowchart shapes for editors that do not recognize `usecase-beta`.

The diagram covers the required system scope, including unfinished requirements. A use case appearing in this diagram is not a completion claim. Red GAP nodes identify missing workflows; orange nodes identify partial implementation or unresolved acceptance gates. Ordinary nodes describe source workflows, not a certification of deployment or device testing.

Solid lines associate actors and use cases. Actor generalization points from a specialized actor to a parent with shared duties. Include means required supporting behavior; extend means conditional behavior. Role relationships summarize default capabilities and do not override configured permissions or backend checks. The compatible flowchart represents these same relationships using labeled arrows rather than native UML arrowheads.

## Coverage of all 30 must-haves

| ID | Must-have | Diagram use cases |
| --- | --- | --- |
| A1 | Household Profile | householdProfile, deviceOwner, householdLocation, sharedHouseholds |
| A2 | Disaster Alerts and Notifications | householdAlerts, deliverAlerts |
| A3 | Household Status Reporting | householdStatus, broadcastChoices, operationLog |
| A4 | Evacuation Support and Mapping | evacuationRoute, spatialMap |
| A5 | Offline and Connectivity Features | offlineView, offlineSubmit, replayStatuses |
| A6 | Evacuation Verification | residentQr, evacuationVerify |
| B1 | Registration and Validation | personnel, officialId, teams |
| B2 | Authentication and Access Control | signIn, authorize, accessRules |
| B3 | Profile Management | ownProfile |
| B4 | Spatial and Mapping Functionalities | spatialMap, householdLocation, gpsFollow |
| C1 | Incident Viewing and Navigation | assignments, assignmentProgress, gpsFollow, offlineNavigation |
| C2 | Field Communication | radio, fieldMonitor |
| C3 | Household Rescue Verification | rescueVerify, confirmStatus |
| C4 | Field Reporting | fieldReport |
| C5 | Operational Logging | operationLog, assignmentProgress, archive |
| C6 | Logistics Request | logistics, requestItems, requestTracking |
| D1 | Disaster Alert Management | disaster, broadcast, deliverAlerts |
| D2 | Rescue Team Dispatch | dispatch, priorityRules, teams |
| D3 | Incident Monitoring | dashboard, incidentMonitor |
| D4 | Household Status Monitoring | householdMonitor, confirmStatus |
| D5 | Severity Classification | severity, areaSeverity |
| D6 | Field Operations Monitoring | fieldMonitor, gpsFollow, radio |
| D7 | Situation Reporting | situation, partnerReport, exportReport |
| D8 | Incident Escalation | escalate, exportReport |
| D9 | Incident Archiving | archive, operationLog |
| R1 | Receive incoming external requests | externalIntake |
| R2 | Handle personnel, equipment and goods/supply requests | requestItems, logistics |
| R3 | Record, HQ-validate, forward and track fulfillment | externalIntake, requestReview, forwardRequest, requestTracking |
| R4 | Verification, controlled coordination and duplicate prevention | preventDuplicates, requestReview, forwardRequest |
| R5 | Track Pending, Approved, In Progress and Completed | requestTracking; the current approval-equivalent label is Validated |

Additional use cases include trusted households and PIN protection; household device-owner selection; password recovery; personnel and team administration; public inquiries; notifications; global search; weather/advisory and hazard layers; shared household records; SMS intake; and controlled partner report requests/releases.

## Important boundaries

- The latest mobile source adds persistent household/rescuer overview snapshots and an offline banner. Durable offline household status submission/replay is still a separate requirement. The older audit predates this cache change.
- Fully offline navigation needs compatible downloaded packs, route/assets readiness and phone testing. Internet PTT is not offline hardware radio.
- Evacuation QR verification has backend support; the older audit identifies the complete staff-facing scanner/entry workflow as unfinished. The verifier is an authorized duty, not an invented account role.
- Official-ID capture/review and exact Low/Medium/High/Critical classification for each affected area remain required gaps in the reviewed audit.
- The audit recorded logistics urgency persistence and legacy archive authorization failures. These tests were not rerun for this diagram; their present resolution is not assumed.
- Live TrackingAid fulfillment and other external exchanges require configured, reachable partner systems and acceptance testing. SafeTrack/EvaTrack/TrackingAid retain ownership of their own census, evacuation and inventory functions.
- Escalation is manual report export/handoff. No automatic city receipt acknowledgment is claimed.
- Operations/Admin is the current normalized administrative role, including legacy Super Admin aliases. Chair, Response, Mitigation/Prevention, Preparedness and Recovery/Rehabilitation are represented separately. Team Leader and Team Member inherit shared rescuer duties; logistics submission is associated specifically with Team Leader.

## Sources inspected

- [Canonical use-case catalog](USE_CASES.md)
- [Must-have implementation audit](MUST_HAVES_IMPLEMENTATION_AUDIT.md)
- [API routes](../backend-laravel/routes/api.php)
- [Web access policy](../backend-laravel/app/Services/Web/WebAccessPolicy.php)
- [User role normalization](../backend-laravel/app/Models/User.php)
- [Mobile overview and offline cache](../frontend-mobile/hooks/useMobileOverview.ts)

This task changes diagram/documentation files only. It does not alter application behavior or refresh the older implementation audit.
