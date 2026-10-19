# Automatic SitRep source synchronization

Updated 19 October 2026. Scope: refresh data for the six findings on /situation before generating a new PDF, CSV or XLSX. Existing saved snapshots are never changed by a sync.

## Source ownership

- SafeTrack: household registry, member census and demographics. Existing paginated households and members JSON endpoints are used. Local disaster assessments remain authoritative for which families are affected; a census record is not itself a disaster assessment.
- EvaTrack: current evacuation-center details, capacity and occupancy from its signed CSV report.
- TrackingAid: resource stock and event-linked requests from its signed CSV report.
- ResQperation: event overview, recorded house damage and command-center dispatch activities.

Sync enriches a report snapshot; it does not overwrite the local registry, silently alter member disaster statuses, or create a city integration.

## Configuration

Existing backend settings are reused:

```dotenv
SAFETRACK_API_URL=<SafeTrack origin>
SAFETRACK_API_KEY=<server-side secret>
SAFETRACK_API_PREFIX=api/external
EVATRACK_REPORTS_URL=<EvaTrack origin>
EVATRACK_REPORTS_API_KEY=<server-side secret>
TRACKINGAID_REPORTS_URL=<TrackingAid origin>
TRACKINGAID_REPORTS_API_KEY=<server-side secret>
SITREP_AUTO_SYNC=true
SITREP_EVATRACK_REPORT_TYPE=general
SITREP_TRACKINGAID_REPORT_TYPE=general
```

No working keys are included here. Configure real values on the backend only. A configured address/key does not prove that the remote server is reachable. Report types may be set to the actual supported upstream type that supplies the required CSV columns. Unsupported report schemas are reported as a failed source, never as a successful sync.

SafeTrack uses its existing api_prefix/households and api_prefix/members routes and retrieves all pages up to the configured limit. An exceeded limit fails rather than silently truncating the census. Stable household_id/member_id values must match ResQperation records for enrichment; missing matches leave demographics/counts unknown.

## EvaTrack CSV

The existing POST /api/v1/external/reports/generate endpoint is called with configured report_type and format=csv. Its successful signed download is retrieved from the configured origin without forwarding the API key.

Minimum usable center columns:

```csv
center_id,center_name,status,capacity,occupancy,event_id,sitio_id
EC1,School,Open,100,25,EVENT-001,SITIO-001
```

center_id aliases: evacuation_center_id, site_id. center_name alias: name. occupancy alias: current_occupancy. Vacancy is computed only when capacity and occupancy are recorded. An event_id column filters out other events; a generic center-status report may omit event_id. Unknown numbers remain null/Not recorded. Site attribution is required for rows to appear in a sitio-specific partner report.

## TrackingAid CSV

```csv
record_type,item,quantity,unit,status,event_id,sitio_id
resource,Water,40,boxes,Available,,SITIO-001
request,Boat,1,unit,Pending,EVENT-001,SITIO-001
```

record_type must distinguish resource from request. Item aliases: item_name, resource_name. Quantity must be a nonnegative number or unknown marker; an unknown value is not zero. Request rows must include event_id and are limited to the selected event. Resource availability is never inferred from a request. Site attribution is required for sitio-specific partner exports.

If the actual upstream general CSV does not supply these fields, that subsystem must provide a compatible report type or the adapter must be updated against its verified schema. No live compatibility with unavailable upstream servers is claimed by automated tests.

## Automatic behavior and failure policy

The page syncs on opening/selecting a draft, every 60 seconds while visible, on focus and relevant operational changes. A Sync latest reports button retries. Per-source states and timestamps are displayed concisely.

POST /api/v1/situation-reports/sync requires an authenticated operator and event_id. It returns an updated draft summary plus sync_id, ready and source statuses. Raw datasets are encrypted in a five-minute server cache and are not returned as cache payloads. No secrets, upstream response bodies or configured addresses are exposed in status messages.

New save/export and partner acceptance require all three sources to succeed. A complete sync no older than 60 seconds can be reused. The backend automatically refetches missing/stale preflight data, independent of the browser. Failed, unconfigured, unreachable or incompatible sources block new generation with 409. The browser cannot bypass this by inventing a sync_id. Source failures do not silently roll back to old data and label it current.

Saved reports and renewed accepted partner files use their stored snapshots without refetching. A correction/new report uses a fresh sync and gets a new event report number. Tests disable automatic source calls only for older isolated fixture contracts; dedicated sync tests explicitly enable the gate and validate its behavior.

## Download and scope protections

Signed downloads must remain on the configured upstream origin; redirects and embedded URL credentials are rejected. CSV downloads do not receive API keys. Downloads are limited to 5 MB/10,000 rows, malformed row lengths and duplicate headers fail. Partner sitio scope excludes unattributed rows. Source sync is locked per event to prevent overlapping expensive pulls.
