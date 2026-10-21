# RESQPERATION External Situation Report API — 4.0

Updated 19 October 2026. Integration guide for SafeTrack, EvaTrack and TrackingAid. All three request the same six-finding BDRRMC Situation Report available to operators in PDF, CSV or Excel.

## 1. API URL and credentials

| Setting | Current value / requirement |
| --- | --- |
| Verified local backend origin | `http://127.0.0.1:8000` |
| Local API base URL | `http://127.0.0.1:8000/api/v1` |
| Document contract version | `4.0` (the route prefix remains `/api/v1`) |
| Authentication header | `X-API-KEY: <configured report integration key>` |
| Backend secret setting | `EXTERNAL_SYSTEM_API_KEY` in backend `.env`, read through `config/reports.php` |
| JSON headers | `Accept: application/json`; POST bodies also use `Content-Type: application/json` |
| Download lifetime | Currently 30 minutes; configurable with `REPORT_URL_EXPIRES_MINUTES` |

The current configured key was accepted by the running local handshake and options endpoints on 19 October 2026. It has not been rotated or copied into this document. Obtain the existing key from the RESQPERATION administrator through a private channel and store it in the partner backend's secret configuration. A handshake does not issue a new key or session token. Send the key on every handshake, options and request submission call. These routes do not require a RESQPERATION operator login or Bearer token.

`127.0.0.1`/`localhost` works only when the partner backend runs on the same computer. For another computer or deployment, the administrator must supply the reachable backend origin (LAN address or HTTPS hostname); no public production API URL has been verified. Port `5175` is the operator frontend, not the partner backend address. Configure the partner's API base URL with `/api/v1` exactly once.

All three partners currently share the configured report key; `source_system` is a required request label, not separate partner authentication. This is different from resource-request intake's `EXTERNAL_REQUEST_INTAKE_KEY` / `X-RESQPERATION-INTEGRATION-KEY`, and from credentials RESQPERATION uses to fetch reports from partners.

For administrators changing the host or key: keep `APP_URL` consistent with the reachable backend origin and proxy configuration so absolute signed URLs are usable by partners. Reload configuration (`php artisan config:clear`, or rebuild the deployment configuration cache) and restart persistent workers/services as applicable. Coordinate an actual key rotation with all three partners. Updating this guide alone does not rotate credentials or reconfigure deployment.

## 2. Available findings and selection identifiers

| API selection key | Printed section | Heading | Recorded source |
| --- | --- | --- | --- |
| `I` | I | Situation Overview | Event, hazard, alert and weather records |
| `II` | II | Status of Affected Areas and Population | Event-linked household status reports; known member counts enriched with matching SafeTrack census records |
| `IV` | III | Damage Assessment | Recorded household / responder property-damage assessments |
| `V` | IV | Search, Rescue and Retrieval Operations | Command-center responder assignments, status and outcome notes |
| `VI` | V | Status of Evacuation Centers | Recorded center status/capacity/occupancy and compatible EvaTrack reports |
| `VII` | VI | Resources Status | Event-linked resource requests and compatible TrackingAid inventory/request reports |

Use API keys from options, not the printed Roman numerals. Keys retain their existing meanings; `III` is retired and rejected on new requests. Select 1–6 distinct keys. Do not hardcode a continuous I–VI range. Missing values remain `Not recorded`; known zeroes remain zero. Population tables pair Families and Persons, provisionally treating one registered household as one family unit.

Displaced Population, casualties, lifelines, assistance costs, needs/issues/planned actions, addenda and annexes are excluded. Damage uses the actual `minor_damage`, `major_damage` and `completely_destroyed` categories; it does not invent total/partial house-damage classifications. Counts describe recorded assessments, not a complete damage census. Do not infer displacement from household status or center occupancy, affected persons from total census, or available stock from requested quantities.

## 3. Required partner workflow

1. Configure a backend-to-backend connection using the API URL and privately supplied report key.
2. Optionally check authentication with `POST /external/reports/handshake` and JSON `{}`. Expect HTTP 200 with `authenticated: true`, `api_version: "4.0"`, current `situation_sections`, endpoint paths and `expires_in_minutes`. No report is created by the handshake.
3. Load `GET /external/reports/options`. Read `data.events`, `data.sitios`, `data.sections`, `data.formats`, `data.source_systems`, `data.api_version` and `data.all_sitios_value`. Use the returned event and sitio IDs; do not send event names or display labels.
4. Submit `POST /external/report-requests` with the fields below. Expect HTTP 202 with `status: "pending"`, `request_id` and a signed `status_url`. Submission does not immediately return a report.
5. Store the returned `request_id` and the complete `status_url`. Poll that exact URL with GET, for example every 10–15 seconds with backoff. Operator review is manual; there is no guaranteed approval time or webhook.
6. Read `data.status`. While `pending`, keep waiting. If `rejected`, show `data.decision_note` and stop polling. If `accepted`, use `data.file.download_url` before `data.file.expires_at`.
7. Download the file with GET and save its binary body. Use `format: "xlsx"` for Excel, not `"excel"`.

All endpoint paths above are relative to the API base URL. Status/download URLs are returned as absolute signed URLs: use them exactly as returned, without prepending the base URL, replacing the host, removing query parameters or constructing a signature. They use URL-signature authentication rather than `X-API-KEY`; do not forward the report key to a download host. Treat signed URLs as private access links.

The request status URL is currently signed without a time expiry; the report download expires after the configured lifetime. If `data.file.expired` is true, `download_url` is null. Ask a RESQPERATION operator to renew the file, then poll the same status URL again. Partners have no public renewal endpoint. Renewal uses the frozen accepted snapshot; it does not fetch current findings. Submit a new reviewed request when a fresh report is needed.

## 4. Request fields

| Field | Required | Validation / meaning |
| --- | --- | --- |
| `api_version` | Yes | String `"4.0"`. Unversioned, v2 and v3 submissions return 422. |
| `source_system` | Yes | Exactly `safetrack`, `evatrack` or `trackingaid` (lowercase). |
| `event_id` | Yes | String, maximum 255 characters; existing RESQPERATION event ID from options. |
| `sitio_id` | Yes | String, maximum 120 characters; `"all"` or registered sitio ID from options converted to string. |
| `included_sections` | Yes | Array of 1–6 distinct keys from `data.sections`: `I`, `II`, `IV`, `V`, `VI`, `VII`. |
| `format` | Yes | Exactly `pdf`, `csv` or `xlsx`. |
| `purpose` | Yes | Nonempty string, maximum 2,000 characters, explaining the request. |
| `reference` | No | Nullable string, maximum 120 characters; partner reference for tracking. It is not an idempotency key. |

Partner scope is all sitios or one sitio; there is no partner purok filter. Operator exports may narrow internal details by sitio/purok. Unattributed external records are excluded from sitio-specific partner reports. Do not send operator identity, signatures, approval decisions, source-sync IDs or arbitrary report content as partner request fields.

Example submission (replace the event placeholder using options):

```json
{
  "api_version": "4.0",
  "source_system": "evatrack",
  "event_id": "<event_id from data.events>",
  "sitio_id": "all",
  "included_sections": ["I", "II", "IV", "V", "VI", "VII"],
  "format": "pdf",
  "purpose": "Review recorded post-disaster findings",
  "reference": "EVA-SITREP-001"
}
```

There is no automatic deduplication by `reference`. After a timeout, check an already received status URL or coordinate with the operator before blindly resubmitting: a repeated POST can create another review request.

## 5. PowerShell integration example

Run this on the partner backend host. Supply `RESQPERATION_REPORT_API_KEY` privately in that process's environment; the variable below is a partner-side example, not a new RESQPERATION backend setting.

```powershell
$reportApiBase = 'http://127.0.0.1:8000/api/v1'
if ([string]::IsNullOrWhiteSpace($env:RESQPERATION_REPORT_API_KEY)) {
    throw 'Configure RESQPERATION_REPORT_API_KEY privately first.'
}
$reportHeaders = @{
    Accept = 'application/json'
    'X-API-KEY' = $env:RESQPERATION_REPORT_API_KEY
}
$handshake = Invoke-RestMethod -Method Post -Uri "$reportApiBase/external/reports/handshake" -Headers $reportHeaders -ContentType 'application/json' -Body '{}'
$options = Invoke-RestMethod -Method Get -Uri "$reportApiBase/external/reports/options" -Headers $reportHeaders
$options.data.events | Select-Object event_id, name

# Replace this value with an event_id returned above before submitting.
$selectedEventId = '<event_id from options>'
$payload = @{
    api_version = '4.0'
    source_system = 'evatrack' # safetrack or trackingaid for the other partners
    event_id = $selectedEventId
    sitio_id = 'all'
    included_sections = @($options.data.sections.PSObject.Properties.Name)
    format = 'pdf'
    purpose = 'Review recorded post-disaster findings'
    reference = 'EVA-SITREP-001'
} | ConvertTo-Json -Depth 5
$submitted = Invoke-RestMethod -Method Post -Uri "$reportApiBase/external/report-requests" -Headers $reportHeaders -ContentType 'application/json' -Body $payload

# Recheck with a delay/backoff while pending; do not resubmit the POST.
$current = Invoke-RestMethod -Method Get -Uri $submitted.status_url -Headers @{ Accept = 'application/json' }
if ($current.data.status -eq 'accepted' -and $current.data.file.download_url) {
    Invoke-WebRequest -Uri $current.data.file.download_url -OutFile './BDRRMC-Situation-Report.pdf'
}
```

## 6. Responses, errors and limits

Submission response (illustrative placeholders):

```json
{
  "status": "pending",
  "request_id": "<UUID>",
  "prepared_by": "Emergency Operations Center: ResQperation System",
  "message": "Request received. A RESQPERATION operator must accept it before a file is released.",
  "status_url": "<absolute signed status URL>"
}
```

Status responses wrap the record in `data`: `id`, `api_version`, `source_system`, `reference`, `event_id`, `sitio_id`, `sections`, `format`, `purpose`, `status`, `decision_note`, review timestamps and `file`. `file` is null before acceptance. An accepted file includes `file_name`, `format`, `download_url`, `expires_at` and `expires_in_minutes`; expired files also expose `expired: true`.

| HTTP status | Meaning / partner action |
| --- | --- |
| 200 | Handshake, options, status or successful file download. |
| 202 | Report request queued for operator review. |
| 401 | Missing/incorrect `X-API-KEY`; check the privately shared report key and backend configuration. |
| 403 | Invalid/expired signed URL; preserve the original URL and request operator renewal for an expired file. |
| 404 | Unknown request or missing report file. |
| 422 | Invalid fields/version/event/sitio/section; inspect JSON `errors` and refresh options. Retired key `III` is invalid. |
| 429 | Rate limit reached; respect `Retry-After` when returned and back off. |
| 5xx | Server/integration failure; retain the request reference and coordinate with the operator before resubmitting. |

Handshake, options and submission routes use a 30-per-minute throttle; request-status polling uses 60 per minute. There is no partner-facing accept/reject route: review and file renewal require an authenticated, authorized RESQPERATION operator. A 409 during operator acceptance can indicate unavailable/incompatible/stale source sync or an already reviewed request; source failures do not release a partial report.

## 7. Review, freshness and scope

On acceptance, the backend requires fresh SafeTrack, EvaTrack and TrackingAid source data, reusing a complete preflight no older than 60 seconds or fetching again. Unconfigured, unavailable or incompatible sources prevent acceptance/file generation. A successful handshake checks report-key authentication; it does not prove source-sync readiness. The partner requesting a RESQPERATION report does not call the authenticated operator sync endpoint.

The accepted report freezes the recorded findings and metadata. Prepared by remains `Emergency Operations Center: ResQperation System`; reviewed by is the reviewing operator; approved by is blank for the Punong Barangay's wet signature. PDF uses A4; CSV has labeled rectangular blocks; XLSX has one Summary sheet. No annexes/addenda are produced. Metadata, report series, signatures and the Data Privacy notice remain.

Title: `BDRRMC Situation Report No. [number] on the [event] as of [date, time]`. Government reporting terminology is adapted to barangay data; this is not a DSWD-issued DROMIC report or a claim of local official-form approval. Downloaded reports can be handed over manually; no city/regional/national transmission channel exists.

The compatibility route `POST /external/reports/generate` with `report_type: "situation"` and all the same required fields delegates to this reviewed workflow. It does not bypass approval. Prefer `/external/report-requests` for SitReps; generic archive generation uses a separate flow.

## 8. Verification and related documents

Local read-only checks on 19 October 2026: configured-key handshake returned 200/authenticated/API 4.0; options returned 200 with the six current identifiers and event/sitio choices; options without a key returned 401. No report request was submitted and no credentials were changed by this documentation update. Remote partner reachability and deployment URLs have not been verified.

See [source-sync configuration and CSV requirements](situation-report-sync.md), [report implementation](situation-report-implementation.md) and [partner acceptance checklist](partner-report-checklist.md).
