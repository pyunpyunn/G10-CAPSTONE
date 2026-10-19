# Six-Finding SitRep Partner Checklist

Updated 19 October 2026. Contract 4.0 for EvaTrack, SafeTrack and TrackingAid.

- Fetch handshake/options and confirm api_version 4.0 and exactly six I–VII labels.
- Update the meanings of IV–VII before sending requests; do not reuse v3 mappings.
- Send api_version: "4.0", source_system, event_id, sitio_id, selected sections, format and purpose.
- Verify 1–7 distinct selections; an eighth, duplicates, unknown keys and old versions must fail.
- Confirm pending review, rejection notes, authenticated acceptance and no early file release.
- Confirm source sync completes before a new acceptance; unavailable data blocks new generation.
- Verify only the six findings render in PDF/CSV/XLSX, with no annexes or addendum.
- Verify source/site scoping, recorded zeroes, missing values and frozen snapshots.
- Confirm the fixed system preparer, operator reviewer, blank approval signature and event numbering.
- Preserve signed status/download URLs and their 30-minute expiry behavior.
- Coordinate subsystem owners before deploying the breaking version change.

For external input columns/configuration see situation-report-sync.md. This API serves partner systems; city handoff occurs outside the application.
