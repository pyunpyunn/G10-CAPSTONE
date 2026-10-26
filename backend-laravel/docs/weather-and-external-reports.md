# Weather and external reports

External Reports is at `/situation/external-reports`. It offers EvaTrack, SafeTrack, and TrackingAid through authenticated Laravel routes; API keys remain on the backend. Configuration presence does not claim that a remote service is reachable. Report types come from each provider contract, no external records are seeded, and generation failures are shown honestly.

Configure provider APIs in `backend-laravel/.env`:

```dotenv
EVATRACK_REPORTS_URL=<EvaTrack origin>
EVATRACK_REPORTS_API_KEY=<EvaTrack external reports key>
EVATRACK_REPORTS_TIMEOUT=60

SAFETRACK_API_URL=<SafeTrack origin>
SAFETRACK_API_KEY=<SafeTrack X-API-KEY>
SAFETRACK_API_PREFIX=api/external
SAFETRACK_API_TIMEOUT=20
SAFETRACK_REPORT_MAX_PAGES=20

TRACKINGAID_REPORTS_URL=<TrackingAid report API origin>
TRACKINGAID_REPORTS_API_KEY=<TrackingAid X-API-KEY>
TRACKINGAID_REPORTS_TIMEOUT=60
```

These outbound report API settings are independent of `TRACKINGAID_DB_*`; this integration does not connect to either partner database. SafeTrack exposes read-only household, member, and sitio endpoints, which Laravel pages and formats into local signed report downloads. TrackingAid accepts the report-generation contract at `/api/v1/external/reports/generate` and returns a time-limited signed download URL. SafeTrack reports do not offer date filters; they support household search, sitio ID, and household ID where applicable. SafeTrack API keys are sent only to SafeTrack, and TrackingAid keys only to TrackingAid.

After adding credentials, run `php artisan config:clear`. Check provider readiness with `GET /api/v1/reports/external/providers` while authenticated; the response reports configuration presence only and never includes keys.

PDF and CSV are returned by EvaTrack. XLSX requests ask EvaTrack for a real CSV and use the existing RESQPERATION ReportFileService/OpenSpout writer. The signed CSV download must be on the configured EvaTrack origin, cannot redirect, is downloaded without the API key, and is limited to 20 MiB. The resulting XLSX uses the existing local report storage and expiring signed-download infrastructure. No direct connection to the remote database or invented report content is used.

Weather Updates displays the stored decimal temperature, without rounding to a whole degree. Live Situation Report previews select the same latest saved weather log; locked snapshots remain historical. Closed-event previews do not use observations after the event ended. The UI reads the database every minute. Manual refresh calls Open-Meteo directly, saves the result, and returns HTTP 201 without dispatching a job. Provider requests have a three-second connection timeout and an eight-second total timeout with no retry; failures return HTTP 503 and missing storage returns HTTP 409. Saved data stays visible while polling and during failures.

The existing Laravel `weather:refresh` schedule remains every three hours. Weather refreshes do not require a queue worker. The operations queue still consumes legacy weather jobs from earlier versions. In deployed environments run the Laravel Scheduler and configured queue workers as usual.

For local environments without a scheduler, run `php artisan weather:work`. This weather-only updater calls the provider directly when the last capture is three hours old; it does not enqueue or consume requests. On Windows, `./start-weather-worker.ps1` starts it hidden and avoids duplicate updater processes. It does not process broadcasts or reminders. `--once` runs one maintenance cycle. Use either this local worker or the normal scheduler setup. The worker must be restarted when PHP closes or the machine restarts; deployment should supervise it or use the standard scheduler.
