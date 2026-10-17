# RESQPERATION Situation Report API — Partner Integration Guide

**Contract version:** 2.0  
**Updated:** October 17, 2026  
**Audience:** EvaTrack, SafeTrack, TrackingAid and partner-system developers

## 1. Connection details

| Setting | Value |
| --- | --- |
| Current Wi-Fi/LAN API base URL | `http://10.90.159.87:8000/api/v1` |
| API key | `__RESQPERATION_API_KEY__` |
| Authentication header | `X-API-KEY` |
| Connection check | `POST /external/reports/handshake` |
| Dropdown choices | `GET /external/reports/options` |
| Submit situation report request | `POST /external/report-requests` |
| RESQPERATION review page | `http://localhost:5175/situation/external-reports` |
| File-link validity | 30 minutes by default; use the returned `expires_at` |

Paths in this document are relative to the API base URL unless shown as full URLs. For example, the submission URL is `http://10.90.159.87:8000/api/v1/external/report-requests`.

The API runs on port **8000**. Port **5175** serves the operator website and is not the reports API port.

Machine addresses verified on October 17, 2026:

| Connection | API base URL | Who can use it |
| --- | --- | --- |
| Current Wi-Fi/LAN | `http://10.90.159.87:8000/api/v1` | Partner servers that can reach this Wi-Fi/LAN address |
| Tailscale | `http://100.121.155.55:8000/api/v1` | Partner servers connected to the permitted Tailscale network |
| Same-machine testing | `http://127.0.0.1:8000/api/v1` | A client running directly on the RESQPERATION machine |

The earlier Wi-Fi address `10.189.234.87` is not currently assigned. Both current network addresses returned HTTP 200 with successful API-key authentication when tested from the RESQPERATION machine. Connectivity from a partner machine must also be tested there. The backend now listens on `0.0.0.0:8000`; `0.0.0.0` is a listening address, not a URL for partners.

Use the LAN URL in the examples below for partners on this network. For Tailscale partners, replace the host with `100.121.155.55`. `localhost` and `127.0.0.1` refer to the requesting machine, so they must not be used by a partner running on a different machine. Wi-Fi IPs can change after reconnecting or moving networks; this address records the machine's current assignment. No public production URL is assigned by this document. Preserve returned status and download URLs exactly, and ensure their hosts are reachable from the partner backend.

Store the key in your backend environment, not browser JavaScript. Use it only for requests to RESQPERATION's authenticated API endpoints; signed status and download links need no API key. The Downloads copy of this guide contains the configured integration key. The repository template uses a placeholder. This credential belongs to RESQPERATION; EvaTrack's example credential does not authenticate here.

## 2. Required partner interface

Before submitting a request, the external system must provide:

1. **Disaster event selection:** load `data.events` from the options endpoint; submit the selected `event_id`.
2. **Sitio selection:** load `data.sitios`; submit the selected ID as a **string**, or the literal string `"all"` for an explicit **All sitios** choice.
3. **Report section selection:** choose at least one section from `data.sections`. A single selected section produces a standalone report.
4. **File format selection:** `pdf`, `xlsx` or `csv`.
5. **Purpose:** a nonempty explanation of why the report is needed.
6. **Request tracking:** store the returned `request_id` and exact `status_url`, show Pending / Accepted / Rejected, and offer a download after acceptance.

The partner backend supplies its configured `source_system`. A partner reference is optional. Disable submission until the disaster, sitio scope, section, format and purpose are selected. IDs must come from RESQPERATION, rather than a partner's own database IDs. Do not hardcode the list of disaster events or sitios.

Both an omitted sitio and an omitted disaster produce HTTP **422**. Null and empty strings do not count as selections. Selecting All sitios requires `"sitio_id": "all"`.

## 3. Lifecycle

```text
Load options → Select disaster, sitio, section(s), format and purpose
             → Submit request → HTTP 202 + request_id + signed status_url
             → Poll status
                 pending  → Wait for RESQPERATION operator review
                 rejected → Display decision_note; correct and submit a new request
                 accepted → Download data.file.download_url before it expires
```

Submission does **not** immediately release a file. RESQPERATION must accept or reject the request. Acceptance generates the selected sections from the selected disaster and sitio scope, then stores an approved snapshot. File-generation failure leaves the request pending. Accepted and rejected decisions cannot be overwritten.

The snapshot reflects recorded data **at acceptance**, rather than at submission or download. Renewing a download uses the same approved snapshot. Request a new report when updated figures are needed.

## 4. Authentication and endpoint summary

For authenticated calls, send:

```http
X-API-KEY: <RESQPERATION_API_KEY>
Accept: application/json
Content-Type: application/json
```

`Content-Type` is needed for JSON POST bodies. No operator login, session cookie or Bearer token is needed for these external endpoints.

| Method | Endpoint | Authentication | Result |
| --- | --- | --- | --- |
| POST | `/external/reports/handshake` | API key | Contract and connection metadata |
| GET | `/external/reports/options` | API key | Database-backed dropdown choices |
| POST | `/external/report-requests` | API key | New request awaiting operator review |
| GET | Exact returned `status_url` | Signed URL | Request state, reason and file metadata |
| GET | Exact returned `data.file.download_url` | Signed URL | Binary report download |

Authenticated handshake, options and submission routes each use `throttle:30,1`. Request-status polling uses `throttle:60,1`. Laravel may share throttle buckets across requests from the same client. Poll every **15–30 seconds** while pending and honor `Retry-After` on HTTP 429.

### Connection check

```http
POST /api/v1/external/reports/handshake
```

Send the API key; no body is required. HTTP 200 includes:

```json
{
  "status": "success",
  "authenticated": true,
  "source": "RESQPERATION",
  "api_version": "2.0",
  "situation_requires_review": true,
  "request_endpoint": "/api/v1/external/report-requests",
  "options_endpoint": "/api/v1/external/reports/options",
  "all_sitios_value": "all",
  "formats": ["pdf", "csv", "xlsx"],
  "expires_in_minutes": 30
}
```

The actual response also includes report types, section labels, request fields and archive category metadata. This guide covers situation reports.

## 5. Load disaster, sitio and section choices

```http
GET /api/v1/external/reports/options
X-API-KEY: <RESQPERATION_API_KEY>
```

Example HTTP 200 response, abbreviated:

```json
{
  "data": {
    "events": [
      {
        "event_id": "<actual-event-id>",
        "name": "Flood Response",
        "label": "Flood Response - Oct 2026",
        "type": "Flood",
        "severity": "High",
        "declared_at": "Oct 17, 2026 8:00 AM",
        "finished_at": "Ongoing",
        "status": "Active",
        "scope": "All registered households"
      }
    ],
    "sitios": [
      {"sitio_id": 710, "sitio_name": "Sitio Alaska"},
      {"sitio_id": 165, "sitio_name": "Sitio Zapatera"}
    ],
    "all_sitios_value": "all",
    "sections": {
      "I": "Situation Overview",
      "II": "Affected Population",
      "III": "Casualties and Immediate Needs",
      "V": "Rescue Operations Timeline",
      "VI": "Initial Damage Assessment",
      "VII": "Resources Deployed and Requests",
      "VIII": "Actions Taken and Recommendations"
    },
    "formats": ["pdf", "csv", "xlsx"],
    "source_systems": ["evatrack", "safetrack", "trackingaid"]
  }
}
```

- The event list returns the latest **50** undeleted events, ordered by declaration date. Active and closed events can be selected. Older known, undeleted event IDs are also validated on submission, but are not necessarily in this dropdown list.
- Sitios belong to RESQPERATION's configured barangay. The list includes registered sitios even when no households or event records are available for them.
- Sitios apply across events; there is no separate event-to-sitio catalog restriction. The selected event and sitio jointly determine report records.
- Convert numeric sitio option IDs to strings when constructing the request. Labels are for display; send IDs.
- Empty lists should disable the relevant selection. An invalid selection is not replaced with all areas.
- Refresh options when opening the request form and after a stale-ID validation error.

## 6. Available reports and data meaning

| Section key | Report | Shared content |
| --- | --- | --- |
| `I` | Situation Overview | Event context, weather condition, wind, rainfall, temperature, observation source and advisory |
| `II` | Affected Population | Household totals, reporting progress, safe / evacuated / unsafe / unchecked counts, sitio summaries, purok breakdowns and registered-person counts when available |
| `III` | Casualties and Immediate Needs | Recorded dispatch outcome totals for missing, injured and rescued; deaths may be unrecorded |
| `V` | Rescue Operations Timeline | Deployments, teams, dates/times, assigned areas, coverage, outcomes and geographically linked broadcast activity |
| `VI` | Initial Damage Assessment | Partially / totally damaged houses and estimated cost, when recorded |
| `VII` | Resources Deployed and Requests | Event-linked resource requests, quantities, sources and validation status |
| `VIII` | Actions Taken and Recommendations | Action text available for the generated report |

`IV` (Evacuation Centers) is **not supported**. Keys retain their established identifiers; do not renumber them. Request only `['II']` for Affected Population alone. Request several keys to receive those sections in one file. To obtain separate files for different sections, submit separate requests.

### Sitio filtering and data limitations

- Household data uses registered household → address → purok → sitio relationships and the latest valid household status for the selected disaster.
- Affected households mean **unsafe plus evacuated/relocated** households. All registered households are not automatically affected. Unchecked households remain unchecked.
- Person totals describe registered members of the relevant households, not individually confirmed casualties. Missing member counts remain unrecorded; they are not inferred as zero.
- Dispatch records are included when linked by household or affected area to the selected sitio. Broadcasts require a matching target purok or affected area. Free-text area names do not establish sitio ownership.
- Weather observations are shared barangay context, rather than a sitio-specific sensor feed. The latest observation, capped at the event's end for a closed event, can be used; it is not necessarily a dedicated event observation.
- Resource requests currently have no authoritative sitio ownership. They are excluded for a specific-sitio export. Choose All sitios for event-linked resource requests. Untagged action notes are also excluded from sitio-specific exports.
- Dispatch outcome sums can include repeat visits. They are not a deduplicated individual casualty register. Deaths require separate verification.
- Initial damage totals currently have no connected assessment source and may be unrecorded. The current event-summary API also does not aggregate saved reports' manually entered action notes; section VIII can be blank. The section title does not imply a complete needs assessment or deployed-inventory register.
- Zero matching records can produce a valid report for an existing event/sitio. Empty tables and unrecorded values are not evidence that no disaster impact occurred.
- PDF displays missing values as `Not recorded`; spreadsheet/CSV values use `Not recorded` for missing scalar fields. Some narrative/table sections have no rows when no linked records exist.

Every export includes common report/event metadata. A one-section request excludes unrelated report sections, while retaining this header context. Specific-sitio exports identify the sitio in the area coverage and include a short data-scope note.

## 7. Submit a report request

```http
POST /api/v1/external/report-requests
```

### Required JSON fields

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `source_system` | string | Yes | `evatrack`, `safetrack` or `trackingaid`; sender's declared system |
| `event_id` | string | Yes | Existing undeleted RESQPERATION disaster ID; maximum 255 characters |
| `sitio_id` | string | Yes | Registered sitio ID from options, or the exact lowercase string `all`; maximum 120 characters |
| `included_sections` | array of strings | Yes | 1–7 distinct keys from `I`, `II`, `III`, `V`, `VI`, `VII`, `VIII` |
| `format` | string | Yes | `pdf`, `csv`, `xlsx`; use `xlsx`, not `excel` |
| `purpose` | string | Yes | Nonempty; maximum 2,000 characters |
| `reference` | string | No | Partner tracking reference; maximum 120 characters |

The shared API key authenticates the integration; `source_system` is a declared label, not a separate per-system credential. Additional systems require a RESQPERATION configuration/code change.

### Example: Affected Population for one sitio

Replace the event placeholder with an ID returned by options. The sitio ID below is an example; always use current options.

```json
{
  "source_system": "evatrack",
  "reference": "EVA-2026-042",
  "event_id": "<actual-event-id>",
  "sitio_id": "710",
  "included_sections": ["II"],
  "format": "pdf",
  "purpose": "Plan relief distribution for Sitio Alaska by purok."
}
```

For all sitios, change only `sitio_id` to `"all"`. For an Excel file, use `"format": "xlsx"`. For a combined overview and population report, use `"included_sections": ["I", "II"]`.

### Submission response: HTTP 202

```json
{
  "status": "pending",
  "request_id": "7d665570-58a8-4ee1-a903-c68745e0fe10",
  "message": "Request received. A RESQPERATION operator must accept it before a file is released.",
  "status_url": "http://10.90.159.87:8000/api/v1/external/report-requests/7d665570-58a8-4ee1-a903-c68745e0fe10?signature=<server-signature>"
}
```

`request_id` is RESQPERATION's identifier. `reference` is your own optional identifier; it is **not an idempotency key**. Repeating a POST creates another request, even with the same reference. Once HTTP 202 is received, poll the existing request rather than submitting again. A network timeout after submission is ambiguous: avoid automatic repeated POSTs without checking with the operator.

`start_date`, `end_date`, `period_start`, `period_end`, custom location labels, callbacks, and partner-supplied data counts are **not supported filters** on this external submission endpoint. Unknown fields are not applied. The event selects the disaster; data is captured when the operator accepts.

### Compatibility endpoint

`POST /external/reports/generate` also accepts an external situation request with all fields above **plus** `"report_type": "situation"`. It returns the same HTTP 202 review workflow. Prefer `/external/report-requests` for new integrations. Do not send EvaTrack report types such as `demographics` or `dromic` to RESQPERATION; choose the applicable RESQPERATION section keys.

## 8. Track acceptance or rejection

GET the exact `status_url` from submission. Its signed query authenticates access to that request. Do not reconstruct the path, remove parameters, change the hostname, or append extra query fields.

Example HTTP 200 while pending:

```json
{
  "data": {
    "id": "7d665570-58a8-4ee1-a903-c68745e0fe10",
    "source_system": "evatrack",
    "reference": "EVA-2026-042",
    "event_id": "<actual-event-id>",
    "sitio_id": "710",
    "sections": ["II"],
    "format": "pdf",
    "purpose": "Plan relief distribution for Sitio Alaska by purok.",
    "status": "pending",
    "decision_note": null,
    "decided_by": null,
    "decided_at": null,
    "created_at": "2026-10-17 14:30:00",
    "file": null
  }
}
```

The status response uses **`data.sections`**, whereas submission uses **`included_sections`**.

### Accepted

The same object changes to `data.status: "accepted"`, contains the reviewer/time and optional review note, and includes:

```json
{
  "file": {
    "status": "success",
    "report_type": "situation",
    "format": "pdf",
    "file_name": "situation_20261017_150000_aB3dE5fG7hJ9kL2m.pdf",
    "download_url": "http://10.90.159.87:8000/api/v1/external/reports/download/situation_20261017_150000_aB3dE5fG7hJ9kL2m.pdf?expires=<timestamp>&signature=<server-signature>",
    "expires_at": "2026-10-17T15:30:00+08:00",
    "expires_in_minutes": 30
  }
}
```

This excerpt is **inside `data`**, not a top-level generation response. Read `data.file.download_url`. `file.status: "success"` describes the prepared file; the request's state remains `data.status: "accepted"`.

### Rejected

`data.status` becomes `"rejected"`, `data.file` stays null, and `data.decision_note` contains the operator's reason. Display it to the requester, stop polling and let them submit a corrected request. Rejection is a normal HTTP 200 status result, not HTTP 403.

The signed request-status link currently has **no time-based expiry**, but becomes unusable if its record is removed or the server signing key changes. Treat the complete URL as a private access token. No callback/webhook is sent and no automatic approval deadline is guaranteed.

## 9. Download and expiration

GET the exact accepted `data.file.download_url`. The response is a binary file, not JSON. Do not send the API key to an external storage host. Use a safe local filename, or the returned basename after validation.

| Format | Extension | Content |
| --- | --- | --- |
| PDF | `.pdf` | A4 portrait report with selected sections |
| Excel | `.xlsx` | Actual Excel workbook, not a CSV renamed to `.xlsx` |
| CSV | `.csv` | UTF-8 with BOM and CSV quoting |

Excel and CSV use three columns: **Section**, **Field**, **Value**. Population figures and sitio/purok metrics are individual labeled rows, rather than a normalized resident dataset. Formula-like CSV cells may be prefixed with an apostrophe for spreadsheet safety. Parse CSV with a CSV parser and handle its BOM; do not split rows on commas manually.

When a download expires, polling returns `data.file.expired: true` and `data.file.download_url: null`. The request remains accepted. Ask a RESQPERATION operator to **Renew download** in the request details, then poll the same status URL for the new link. Renewal is currently an authenticated operator action; there is no external API-key renewal endpoint. A deleted/missing file can return HTTP 404 even before its link expires.

With local storage, Laravel serves a signed download URL. With configured S3 storage, the server can return an S3 pre-signed URL. Follow the returned URL instead of constructing a storage path. File availability does not imply a published deletion/retention schedule.

## 10. Errors and retry behavior

| HTTP status | Meaning | Partner action |
| --- | --- | --- |
| 200 | Successful handshake/options/status or binary download | Interpret the endpoint-specific response |
| 202 | Request stored and awaiting review | Save request ID and status URL |
| 401 | Missing/invalid API key | Check the key/header with RESQPERATION |
| 403 | Invalid/tampered signed URL, or expired download link | Use the original status URL; request download renewal if expired |
| 404 | Request record or report file unavailable | Check the request/link with RESQPERATION |
| 422 | Invalid request fields, disaster or sitio | Show field errors, refresh options and correct input |
| 429 | Rate limit reached | Honor `Retry-After`, reduce polling, retry GETs with backoff |
| 500 / 503 | Server/storage problem or database unavailable | Back off on GETs; do not blindly duplicate submissions |

Validation errors have this structure; exact wording can vary:

```json
{
  "message": "The event id field is required. (and 1 more error)",
  "errors": {
    "event_id": ["The event id field is required."],
    "sitio_id": ["The sitio id field is required."]
  }
}
```

Key failures return HTTP 401 with `{"status":"error","message":"Unauthorized: Invalid or missing X-API-KEY header."}`. A removed event or sitio after submission can prevent acceptance; the operator can reject the request so the partner can choose valid options and resubmit.

Request timestamps without an offset, such as `created_at`, use the configured server timezone, currently Asia/Manila (UTC+08:00). File `expires_at` includes its offset and should be parsed as an instant. Event display labels are human-readable and should not be parsed as IDs or filters.

## 11. Integration examples

Configure your backend environment:

```env
RESQPERATION_API_BASE_URL=http://10.90.159.87:8000/api/v1
RESQPERATION_API_KEY=<API key from section 1>
```

### cURL / Postman

```bash
# Connection check
curl --fail-with-body -X POST "$RESQPERATION_API_BASE_URL/external/reports/handshake" \
  -H "X-API-KEY: $RESQPERATION_API_KEY" -H 'Accept: application/json'

# Populate your dropdowns from this response
curl --fail-with-body "$RESQPERATION_API_BASE_URL/external/reports/options" \
  -H "X-API-KEY: $RESQPERATION_API_KEY" -H 'Accept: application/json'

# Replace the event ID and selected sitio using the options response
curl --fail-with-body -X POST "$RESQPERATION_API_BASE_URL/external/report-requests" \
  -H "X-API-KEY: $RESQPERATION_API_KEY" -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"source_system":"evatrack","event_id":"<actual-event-id>","sitio_id":"710","included_sections":["II"],"format":"pdf","purpose":"Relief planning","reference":"EVA-2026-042"}'
```

In Postman, create the same requests, put the key in Headers, and choose **raw → JSON** for submission. Copy the returned `status_url` into a GET request. After acceptance, open a GET for `data.file.download_url` and select **Send and Download**. Use Postman's Desktop Agent for a local API.

### PowerShell

```powershell
$baseUrl = $env:RESQPERATION_API_BASE_URL.TrimEnd('/')
$headers = @{ 'X-API-KEY' = $env:RESQPERATION_API_KEY; Accept = 'application/json' }
$options = (Invoke-RestMethod -Uri "$baseUrl/external/reports/options" -Headers $headers).data

# Bind options.events and options.sitios to dropdowns in your application.
$selectedEventId = Read-Host 'Enter the event_id selected from options.events'
$selectedSitioId = Read-Host 'Enter a sitio_id selected from options.sitios, or all'
$payload = @{
    source_system = 'evatrack'
    event_id = $selectedEventId
    sitio_id = $selectedSitioId
    included_sections = @('II')
    format = 'pdf'
    purpose = 'Relief planning'
} | ConvertTo-Json
$submitted = Invoke-RestMethod -Uri "$baseUrl/external/report-requests" -Method Post `
    -Headers $headers -ContentType 'application/json' -Body $payload

# Save these for later; operator review may take longer than a script session.
$submitted | Select-Object request_id, status_url
$requestState = (Invoke-RestMethod -Uri $submitted.status_url -Headers @{ Accept = 'application/json' }).data
if ($requestState.status -eq 'accepted' -and $requestState.file.download_url) {
    $safeName = [IO.Path]::GetFileName($requestState.file.file_name)
    Invoke-WebRequest -Uri $requestState.file.download_url -OutFile $safeName
} elseif ($requestState.status -eq 'rejected') {
    Write-Output $requestState.decision_note
} else {
    Write-Output 'Pending review, or download renewal needed. Check the saved status URL later.'
}
```

### Partner backend: JavaScript / Node.js (native fetch)

```javascript
const base = process.env.RESQPERATION_API_BASE_URL.replace(/\/$/, '');
const key = process.env.RESQPERATION_API_KEY;

async function jsonResponse(response) {
  const body = await response.json();
  if (!response.ok) throw new Error(JSON.stringify({ status: response.status, body }));
  return body;
}

export async function loadReportOptions() {
  return (await jsonResponse(await fetch(`${base}/external/reports/options`, {
    headers: { 'X-API-KEY': key, Accept: 'application/json' }
  }))).data;
}

export async function requestPopulationReport(eventId, sitioId, reference) {
  // Values come from your user's dropdown selections. Use 'all' explicitly.
  if (!eventId || !sitioId) throw new Error('Select a disaster and sitio scope.');
  return jsonResponse(await fetch(`${base}/external/report-requests`, {
    method: 'POST',
    headers: { 'X-API-KEY': key, Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({
      source_system: 'evatrack', event_id: String(eventId), sitio_id: String(sitioId),
      included_sections: ['II'], format: 'pdf', purpose: 'Relief planning', reference
    })
  })); // HTTP 202: persist request_id and status_url in your database.
}

export async function checkReport(statusUrl) {
  // statusUrl must be the unchanged URL previously issued by RESQPERATION.
  return (await jsonResponse(await fetch(statusUrl, {
    headers: { Accept: 'application/json' }
  }))).data;
}

// Schedule checkReport every 15–30 seconds while pending, with bounded retries.
// rejected: show decision_note. accepted + file.download_url: enable download.
// accepted + file.expired: show 'Download renewal required'.
```

## 12. RESQPERATION administrator setup

The external partner does not need to recreate RESQPERATION's controllers, report templates or database tables. On the RESQPERATION server, configure:

```env
APP_URL=http://10.90.159.87:8000
EXTERNAL_SYSTEM_API_KEY=<RESQPERATION integration key>
REPORT_DISK=local
REPORT_URL_EXPIRES_MINUTES=30
```

For LAN/production, use the reachable server origin for `APP_URL` and the partner's base URL. Serve requests through that host so signed URLs use the correct origin; configure trusted proxy settings if deployed behind a proxy. Run `php artisan config:clear` after environment changes, or rebuild the deployment's config cache. API-key rotation requires updating partners' configured key.

The report inbox migrations must be installed:

```bash
php artisan migrate --path=database/migrations/2026_10_17_000001_create_external_report_requests_table.php
php artisan migrate --path=database/migrations/2026_10_17_000002_add_sitio_to_external_report_requests.php
```

To start the development API with network access:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

The backend needs its configured database, writable report storage and installed PHP dependencies. Situation request submission/approval does not require the archive export worker: the operator prepares the file during acceptance. Heavy archive exports use a separate queue workflow.

Only authenticated RESQPERATION web accounts with Reports Documentation access can list the inbox, accept/reject, or renew files. An external API key does not grant operator access. Partners should use the signed status URL for their own request rather than the operator inbox API.

## 13. Partner acceptance checklist

- Handshake succeeds using the issued RESQPERATION key and reachable backend URL.
- Disaster and sitio dropdowns load from options; ID values are passed as strings.
- Request cannot submit without a disaster and explicit sitio/All sitios selection.
- Invalid IDs, missing selections, unsupported section IV and duplicate sections show validation errors.
- Affected Population alone sends `included_sections: ["II"]`.
- HTTP 202 persists the request ID and status URL; Pending does not show a download.
- Rejection displays the reason and stops polling; correction creates a new request.
- Acceptance downloads only the requested sections and correct sitio scope.
- PDF, Excel and CSV are handled as files; spreadsheets use the documented three-column layout.
- Expired downloads show a renewal state; returned signed URLs are kept intact.
- Polling honors rate limits; repeated POSTs are not used as polling.
- The deployment's API and returned download URLs work from the partner server, not only the developer's own machine.
