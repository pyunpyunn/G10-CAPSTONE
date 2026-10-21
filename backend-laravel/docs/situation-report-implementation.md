# BDRRMC Situation Report Implementation

Updated 19 October 2026. Current document and partner contract: 4.0. This specification replaces the earlier twelve-section/annex design.

## Report scope

Only these post-disaster findings are rendered, in order:

1. Situation Overview
2. Status of Affected Areas and Population
3. Damage Assessment
4. Search, Rescue and Retrieval Operations
5. Status of Evacuation Centers
6. Resources Status

There are no additional casualty, lifeline, assistance-cost, needs, issues, planned-action, addendum or annex outputs. Metadata, numbered title, signatures and RA 10173 notice remain. The optional old manual handoff logging backend does not add anything to this report; its card was removed from the simplified page. City handoff remains outside the system.

The barangay report uses government reporting terminology but does not claim to be a DSWD-issued DROMIC report or an office-approved blank form. See report-terminology-and-sources.md.

## Data ownership and sync

SafeTrack supplies census/demographic information, EvaTrack supplies evacuation-center details, TrackingAid supplies resource inventory and event-linked requests. ResQperation owns disaster assessments, house-damage findings and dispatch activities. External census data enriches known family/member IDs; it never determines that a family was affected by the event on its own.

The operator draft automatically syncs on opening/selecting an event, every 60 seconds while visible, and on focus/relevant operation changes. Sync latest reports retries. Each provider has a concise status and timestamp. New saves, draft exports and partner acceptance require all three providers to be current. Failed/incompatible/unconfigured sources block generation instead of claiming old data is fresh. A complete encrypted-cache preflight up to 60 seconds old can be reused; otherwise the backend fetches again. The report as-of time comes from that sync. Saved snapshots bypass syncing and remain immutable.

Details, configuration and upstream CSV requirements: situation-report-sync.md. Live compatibility with unavailable subsystem servers is not implied by mocked tests.

## Files and source paths

| Responsibility | Implementation |
| --- | --- |
| Six section names and contract version | app/Support/SituationReportSections.php |
| Historical read-only projection | app/Support/SituationReportSnapshot.php |
| Summary and population data | SituationReportPresenter, SituationReportData, SituationReportPopulation |
| Shared findings document | app/Presenters/SituationReportDocument.php |
| PDF | resources/views/reports/situation.blade.php and ReportFileService |
| CSV and XLSX | SituationReportSpreadsheet; one Summary sheet, no annex sheets |
| External fetch/normalize | app/Services/Shared/SituationExternalSource.php; existing SafeTrack/EvaTrack/TrackingAid clients |
| Sync/cache/freshness/enrichment | app/Services/Web/SituationReportSync.php |
| Generation/snapshot integration | SituationReportService, SituationReportWorkflow, ReportGenerationService, ReportExchangeController |
| Operator UI | SituationReportPage, SituationReferenceSections, situationSections helpers |

The new authenticated POST /api/v1/situation-reports/sync route accepts event_id and returns an updated preview with per-source state. API keys stay on the backend; signed CSV downloads are restricted to the configured source origin and receive no API credentials. Data payloads are encrypted in the server cache for five minutes and omitted from client status metadata. Counts and file size are bounded; malformed/incompatible CSV is rejected.

## Snapshot and contract compatibility

New reports use schema_version 4.0 and keys I–VII. Old v2/v3 JSON is not rewritten. Read-only rendering projects supported old findings into the six-section document and drops unsupported topics. Version 4.0 is required on new partner requests because IV–VII changed meaning. Old/unversioned requests receive 422; older pending requests must be resubmitted. Previously accepted files renew from their saved data rather than live sources.

Per-event numbering remains transaction protected. Corrections create a new number and point to the superseded report. Partner preparer remains Emergency Operations Center: ResQperation System; reviewer is the accepting operator; approval space stays blank for wet signature. Authentication, operator review, source/site scoping and signed 30-minute files remain.

## Storage and deployment

No additional database migration is needed for sync: it uses the configured server cache. Earlier metadata/manual-log/request-version migrations remain relevant to this branch and must be applied before deployment if not already applied. No application migration or deployment was performed here. Configure all three remote services and compatible report columns before enabling new report generation; the default is fail-closed when any required source cannot sync.

The source normalizer preserves recorded zeroes and unknowns. A missing evacuation location is not assigned outside EC. The registry currently uses a registered household as a provisional family unit, pending local confirmation. Operator sitio/purok filters narrow detail lists; aggregate population findings are kept separately. Partner sitio scope excludes external rows without matching site attribution.

## Validation

Report and integration tests cover the exact six headings, removed-content exclusions in PDF/CSV/XLSX, zero/unknown values, scope and snapshot immutability, versions and selection limits, source synchronization, stale refresh, failure blocking, secret-safe statuses, signed same-origin downloads and saved-export sync bypass. Isolated legacy fixture tests disable external calls; dedicated sync tests explicitly enable the gate.

Run backend checks with PHP memory_limit=512M. Frontend production build and targeted ESLint verify the page changes. Current results are recorded in report-validation.md. The sample PDF is synthetic and is regenerated by docs/report-templates/generate-sample.php without using application records.

## Source boundary correction

Displaced Population is excluded: RESQPERATION has no verified displacement register for this report. Household status, census totals and center occupancy must not be translated into displaced families/persons or inside/outside-center totals. Existing internal evacuation records and historical snapshot JSON are preserved, but displacement tables are not rendered.

The six findings display consecutive numbers I�VI. Partner selection identifiers retain their existing meanings (I, II, IV, V, VI, VII); III is retired and returns 422 on new requests. Version 4.0 remains unchanged because retained identifiers have not been reassigned. Partners must refresh options. Maximum selection is six.

| Finding | Recorded source |
| --- | --- |
| Situation Overview | disaster_events, disaster_types, severity_levels and weather_logs |
| Affected Areas and Population | latest event-linked household status reports and registered sitio/purok; known member counts, optionally enriched from matching SafeTrack records |
| Damage Assessment | property_damage in recorded household status/linked responder field assessments |
| Rescue/Dispatch Operations | responder_assignments: team, assigned area, times, status and recorded outcome notes |
| Evacuation Centers | recorded center status/capacity/occupancy; compatible EvaTrack center report when sync is enabled |
| Resources Status | event-linked resource_requests; compatible TrackingAid inventory/request report when sync is enabled |

Missing values stay Not recorded. Do not estimate affected persons from total census, infer displacement from household status/occupancy, infer rescue results from assignment counts, or treat requested resources as available stock. Live partner CSV compatibility still requires verification.

Damage output uses recorded minor_damage, major_damage and completely_destroyed assessments, plus scoped detail records. No conversion to total/partial house-damage classifications or monetary estimates is performed. Category counts describe recorded assessments only, not a complete damage census.
