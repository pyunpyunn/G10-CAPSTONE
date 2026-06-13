# Mambaling Household Geotag Update Note

Prepared: June 13, 2026

## Status

The live shared MySQL server was not reachable from this machine during the update check. The configured host/port timed out, so the household geotag update was not executed directly.

## Review Script

Review this file before running it:

`docs/sql_proposals/initial/2026_06_13_mambaling_household_geotag_update.sql`

## What It Does

- Updates existing household address scope to Barangay Mambaling.
- Assigns test GPS coordinates inside the Mambaling area.
- Uses Mambaling locality labels such as Sitio Lawis, Alaska Mambaling, Sitio Nava, and M. Gochan Street.
- Inserts missing rows in `geotagged_locations`.
- Updates existing rows in `geotagged_locations`.

## When To Run

Run only when:

- the shared MySQL server is reachable;
- the DB member approves the script;
- SafeTrack has not yet provided final household geotags.

Do not run this if SafeTrack already has final real household coordinates.
