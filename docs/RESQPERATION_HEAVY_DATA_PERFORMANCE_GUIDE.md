# RESQPERATION Heavy Data Performance Guide

This guide explains how the system should handle large real-world data without loading everything at once.

## Current Code Rules

- Web tables use backend pagination. Do not fetch every row into the browser.
- Household status monitoring is counted per household, not per person.
- Household status page loads only the current page of household rows.
- Purok summary is aggregated in SQL, not by loading every household row into PHP.
- Dispatch logs are paginated.
- Radio communication feed is limited and paginated for mobile use.
- Map pages should use capped marker payloads and should not load old archived events unless requested.

## Database Indexes Needed

The DB member should review and apply the additive index proposal:

```text
docs/sql_proposals/2026_06_28_performance_indexes_for_shared_db.sql
```

Before running each `ALTER TABLE`, check existing indexes:

```sql
SHOW INDEX FROM table_name;
```

Skip an index if the same column combination already exists.

## High-Volume Tables

These tables are expected to grow quickly:

- `household_status_logs`
- `household_disasters`
- `device_tokens`
- `device_tracking_logs`
- `responder_assignments`
- `responder_location_logs`
- `responder_routes`
- `route_coordinates`
- `responder_communication_logs`
- `notifications`
- `resource_requests`
- `incident_archives`

## Practical Limits

- Household status list: 10 to 50 rows per request.
- Dispatch list: 10 to 50 rows per request.
- Radio feed: small mobile pages, newest logs first.
- Archive pages: paginated, not full-table downloads.
- Exports should export the selected page or selected group, not the entire shared DB.

## If Pages Still Load Slowly

Check in this order:

1. Confirm Laravel can reach the active DB host.
2. Apply missing indexes from the SQL proposal.
3. Check if the page is requesting a large `per_page` value.
4. Check if a frontend filter is triggering full page reload instead of table refresh.
5. Use browser Network tab to identify the slow API endpoint.
6. Use MySQL `EXPLAIN` on the slow query.
