# Weather and external reports

External Reports is at `/situation/external-reports`. It uses authenticated `GET /api/v1/reports/evatrack/configuration` and `POST /api/v1/reports/evatrack/generate`; credentials remain on the backend. The existing EvaTrack server URL and API key configuration are used. Configuration presence does not claim that the remote server is available. Report types come from backend configuration, no external records are seeded, and generation failures are shown honestly.

PDF and CSV are returned by EvaTrack. XLSX requests ask EvaTrack for a real CSV and use the existing RESQPERATION ReportFileService/OpenSpout writer. The signed CSV download must be on the configured EvaTrack origin, cannot redirect, is downloaded without the API key, and is limited to 20 MiB. The resulting XLSX uses the existing local report storage and expiring signed-download infrastructure. No direct connection to the remote database or invented report content is used.

Weather Updates displays the stored decimal temperature, without rounding to a whole degree. Live Situation Report previews select the same latest saved weather log; locked snapshots remain historical. Closed-event previews do not use observations after the event ended. The UI reads the database every minute, and polls queued manual refreshes every five seconds for up to one minute. Saved data stays visible while polling and during failures.

The existing Laravel `weather:refresh` schedule remains every three hours. The operations queue schedule now includes `weather`, fixing manual refresh jobs being left unconsumed. In deployed environments run the Laravel Scheduler and configured queue workers as usual.

For local environments without a scheduler, run `php artisan weather:work`. This weather-only worker consumes manual weather jobs and captures a new snapshot when the last capture is three hours old. It does not process broadcasts or reminders. `--once` runs one maintenance cycle. Use either this local worker or the normal scheduler setup. The worker must be restarted when PHP closes or the machine restarts; deployment should supervise it or use the standard scheduler.
