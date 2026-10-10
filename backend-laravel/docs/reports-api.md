 Archive and Situation reports

The implementation adapts `EXTERNAL_REPORTS_API.md` to ResQperation's existing
Archive and Situation schemas. It uses the document's API-key authentication,
private storage, rate limiting, and expiring signed download pattern. The
document's placeholder records and totals are not used.

## Local setup

Reports use the existing default database connection (`resq_local` on this
machine). A second/shared database is not needed. Report generation reads existing
queries and saved Situation snapshots; it does not copy or seed operational data.

Settings in `.env`:

```env
REPORT_DISK=local
EXTERNAL_SYSTEM_API_KEY=<server-side secret>
REPORT_URL_EXPIRES_MINUTES=30
REPORT_PDF_MAX_ROWS=5000
REPORT_QUEUE_THRESHOLD=1000
REPORT_QUEUE_CONNECTION=reports
```

A unique external API key has been generated in the local `.env`. Do not expose
it through a Vite environment variable. The web pages use the existing Sanctum
admin authentication on `POST /api/v1/reports/generate`.

The local database already has the `jobs` table. Run the worker for exports over
the queue threshold:

```powershell
php artisan queue:work reports --queue=reports --timeout=600 --tries=1
```

Alternatively, `php artisan schedule:work` runs the configured worker task every
minute and cleans local generated files/status metadata after 24 hours.
`REPORT_DISK=s3` uses the existing AWS filesystem settings and the installed S3
adapter. Configure an S3 lifecycle rule to remove reports after 24 hours.

## Generate and download

External callers use `POST /api/v1/external/reports/generate` with `X-API-KEY`.
The route is limited to 30 requests per minute. Formats are `pdf`, `csv`, `xlsx`
(`excel` is an alias). PDF is the default.

Archive request:

```json
{
  "report_type": "archive",
  "category": "disaster-events",
  "format": "xlsx",
  "start_date": "2026-10-01",
  "end_date": "2026-10-08"
}
```

Archive categories match the existing six category endpoints. Existing `search`,
`purok`, `event_id`, and `status` filters apply. Optional `ids` selects records by
their database IDs, including records in saved groups. Exporting a category
exports all matching records, independent of the displayed page. CSV/XLSX query
records in chunks of 500. PDF rejects exports over the configured row limit
instead of silently truncating them.

Situation request:

```json
{
  "report_type": "situation",
  "event_id": "<database event ID>",
  "format": "pdf",
  "included_sections": ["I", "II", "III", "IV", "V", "VI", "VII", "VIII"]
}
```

Use `sit_rep_id` instead of `event_id` to export a locked saved snapshot. A saved
snapshot retains its recorded sections and actions. Draft requests may provide
`actions_text` and `included_sections`. Missing death/damage data is represented
as unavailable rather than fabricated zeroes.

The report types are **`archive` and `situation`**, adapted to these pages; the
EvaTrack-specific `demographics`, `dromic`, `utilization`, `vulnerable`, and
`general` queries are not defined by the attached document and are not mapped to
unrelated local tables.

Small exports return HTTP 200 with `status`, `file_name`, `format`, `report_type`,
`download_url`, and expiry metadata. Large Archive exports return HTTP 202 with
`status: pending` and a signed `status_url`. Poll that URL until it returns
`status: success` and the download metadata, or `status: error`. The web pages
perform this polling automatically. Generation is synchronous below the queue
threshold and queued above it.

Local downloads use `GET /api/v1/external/reports/download/{filename}`. The
signature authenticates the download; no API key is required for that request.
Expired/tampered URLs return 403, and missing files return 404.

Archive retains Laravel server-side numbered pagination, because the page shows
total pages. CSV/XLSX export uses ID-based chunking rather than loading the entire
matching dataset into memory. The existing CSV stream endpoint remains compatible;
its PDF/Excel variants now return generation/download metadata.
