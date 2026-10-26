# Bidirectional EvaTrack reports

Contract reference: https://github.com/klint-droid/EvaTrack/blob/main/docs/EXTERNAL_REPORTS_API.md

## View and request EvaTrack reports

Sign in to the web app with a role that has report/documentation access (Chair, Mitigation/Prevention, Preparedness, Response, or Recovery/Rehabilitation). Operations/Admin is technical and does not have operational report access. Open **Archive → EvaTrack Reports**, select demographics, DROMIC, utilization, vulnerable, or general; select PDF or CSV and optional dates; click **Generate EvaTrack report**, then **View / download report**. A successful result verifies the remote connection and displays the signed link expiration. Expired links require generating again.

The browser calls authenticated local routes `/api/v1/reports/evatrack/configuration` and `/api/v1/reports/evatrack/generate`. The backend calls EvaTrack's `POST /api/v1/external/reports/generate` using `X-API-KEY`; the key is never returned to the browser. Signed download URLs work without that key and support EvaTrack's local or S3 storage. Configuration status does not claim the remote server is reachable.

Backend `.env` settings:

```dotenv
EVATRACK_REPORTS_URL=http://127.0.0.1:9000
EVATRACK_REPORTS_API_KEY=<must match EvaTrack EXTERNAL_SYSTEM_API_KEY>
EVATRACK_REPORTS_TIMEOUT=60
```

A temporary random outgoing key has been prepared locally if none existed. Configure EvaTrack's `EXTERNAL_SYSTEM_API_KEY` with that same value, or replace the outgoing value with the actual EvaTrack key. No EvaTrack server was available at localhost port 9000 during setup. The public guide's example key is not used as a live credential.

Run `php artisan config:clear` after changes. When moving to a shared database, retain these settings; change the URL when EvaTrack moves to another host and synchronize the secret with its administrator. Database connection settings and report API connection settings are independent. Localhost addresses refer to the machine running the backend; signed download URLs must also be reachable from the user's browser.

## EvaTrack requests our reports

Our receiving API is implemented separately from the outgoing client. Set EvaTrack's caller to our reachable backend origin and give it our backend `.env` value `EXTERNAL_SYSTEM_API_KEY`. A random local key was prepared if this setting was empty. It is distinct from `EVATRACK_REPORTS_API_KEY`.

EvaTrack first calls `POST /api/v1/external/reports/handshake` with `X-API-KEY` to authenticate and discover our supported categories. Then it calls `POST /api/v1/external/reports/generate` with the same header and one of these JSON payloads:

```json
{"report_type":"archive","category":"resource-requests","format":"csv"}
```

```json
{"report_type":"situation","event_id":"YOUR_EVENT_ID","format":"pdf"}
```

Archive categories: disaster-events, household-status-logs, dispatch-logs, radio-communication-logs, resource-requests, situation-reports. Formats: PDF, CSV, XLSX. Situation reports require a valid `event_id` or saved `sit_rep_id`. Our report categories reflect our data; EvaTrack's demographic categories are requested from EvaTrack instead.

Successful generation returns `status: success`, `file_name`, `download_url`, and expiration information. Download with GET on the returned signed URL. Large archive exports can return 202 with `status: pending` and signed `status_url`; poll that URL until success and then download. Run the reports worker for queued exports: `php artisan queue:work reports --queue=reports`. Incoming generation and handshake are throttled to 30/minute; incorrect/missing keys return 401. Invalid or expired download signatures return 403.

## Check operation

- Outgoing live check: `php artisan reports:verify-evatrack` generates a general CSV, downloads it without sending the secret on the download request, and saves it under `storage/app/private/evatrack-verification`. Use `--type=demographics --format=pdf` for PDF.
- Automated outgoing and incoming-handshake checks: `php artisan test --filter=EvaTrackReportsTest`. These use controlled HTTP responses and do not claim live EvaTrack availability.
- Existing receiving API checks: `php artisan test --filter=ExternalReportsTest` covers credentials, signed downloads/expiration, file formats, and queued export status.
- In the UI, successful generation shows **EvaTrack connection verified**. If EvaTrack is down, the page explains that it cannot be reached. An incorrect outgoing key reports that EvaTrack rejected it.

During local setup, our incoming handshake, real resource-requests CSV generation, and signed download were checked over HTTP; a tampered download URL was rejected. The downloaded verification artifact is under `storage/app/private/report-integration-verification`.
