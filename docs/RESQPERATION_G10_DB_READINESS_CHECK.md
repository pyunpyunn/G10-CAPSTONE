# RESQPERATION G10 DB Readiness Check

Date checked: 2026-06-01  
Project folder: `C:\backend\G10CAPSTONE\resqperation-system`

## Result

The project is close to development-ready, but the backend should not start full module coding yet.

The shared MySQL server is reachable in read-only mode. The database exists and currently has 77 tables.

Laravel was initially falling back to SQLite because `backend-laravel/.env` was missing:

```env
DB_CONNECTION=mysql
```

This has now been fixed locally in `.env`, and `php artisan config:clear` was run.

Current important warning:

```text
php artisan migrate:status can read MySQL now, but the default Laravel users migration is still pending.
Do not run php artisan migrate yet because the shared database already has a custom users table using user_id.
```

## Setup Status

### Complete

- `backend-laravel` exists.
- Laravel runs successfully.
- Laravel version checked: 13.12.0.
- `vendor` exists.
- `frontend-web` exists.
- `frontend-web/node_modules` exists.
- `frontend-mobile` exists.
- `frontend-mobile/node_modules` exists.
- The shared MySQL database is reachable.
- Backend `.env` now has `DB_CONNECTION=mysql`.

### Not Complete

- The current Laravel models still assume default `id` primary keys.
- The shared DB uses custom primary keys like `user_id`, `role_id`, `event_id`, `household_id`, and `request_id`.
- API controllers, requests, resources, role middleware, and domain models are not built yet.

## Existing DB Pattern

The current database does not follow the default Laravel `id` primary key style.

Examples from the live DB:

```text
users                 -> user_id
roles                 -> role_id
disaster_events       -> event_id
households            -> household_id
household_members     -> member_id
rescue_teams          -> team_id
resource_requests     -> request_id
situation_reports     -> sit_rep_id
```

This means every Laravel model must explicitly define its primary key.

Example:

```php
protected $primaryKey = 'user_id';
```

## Required Tables Already Present

These important tables already exist:

```text
users
roles
disaster_events
households
household_members
rescue_teams
resource_requests
situation_reports
device_tokens
device_tracking_logs
geotagged_locations
disaster_broadcasts
notifications
notification_logs
notification_recipients
evacuation_centers
responders
responder_assignments
responder_field_reports
responder_location_logs
responder_routes
```

## Overall Lacking Tables

These are the expected RESQPERATION tables from the development guide that are not present in the current shared DB using the exact planned table names.

```text
broadcast_logs
weather_logs
household_devices
household_geotags
household_status_logs
evacuation_sites
rescuer_profiles
rescue_dispatches
dispatch_routes
resource_items
request_validations
audit_logs
integration_logs
```

Important:

Some of these are not completely missing as concepts. The shared DB already has older or differently named tables that partially cover them. Do not create duplicate tables until the team approves whether to reuse, alter, or replace the existing structures.

| Planned table | Current DB status | Existing table that may cover it | Action needed |
| --- | --- | --- | --- |
| `broadcast_logs` | Missing exact name | `disaster_broadcasts`, `notifications`, `notification_logs`, `notification_recipients` | Decide whether to reuse broadcast/notification tables or create a clearer log table. |
| `weather_logs` | Missing | None confirmed | Needed for stored PAGASA/Open-Meteo weather snapshots. |
| `household_devices` | Missing exact name | `device_tokens`, `device_tracking_logs` | Prefer altering existing device tables instead of duplicating device records. |
| `household_geotags` | Missing exact name | `geotagged_locations` | Reuse or extend `geotagged_locations` if it fits household geotag needs. |
| `household_status_logs` | Missing | `household_statuses`, `household_disasters`, `responder_field_reports`, `hq_field_reports` | Needed as a clear status history/source log, unless an existing table is formally assigned for this purpose. |
| `evacuation_sites` | Missing exact name | `evacuation_centers` | Use existing `evacuation_centers`; do not create duplicate sites table unless renamed by team. |
| `rescuer_profiles` | Missing exact name | `responders` | Use or extend `responders`; likely link responders to `users.user_id`. |
| `rescue_dispatches` | Missing exact name | `responder_assignments` | Use or extend `responder_assignments` for dispatch records. |
| `dispatch_routes` | Missing exact name | `responder_routes`, `route_coordinates` | Use existing route tables if they can store dispatch route geometry. |
| `resource_items` | Missing | `unit_allocations`, resource request tables | Confirm whether inventory/resource availability is handled by external TrackingAid or local mirror table. |
| `request_validations` | Missing | None confirmed | Needed for validation history before forwarding requests. |
| `audit_logs` | Missing | `analytics_job_logs`, `import_logs` | Needed for user/module action tracking, unless a general log table is selected. |
| `integration_logs` | Missing | `import_logs`, `csv_uploads`, `data_sources` | Needed for EvaTrack/TrackingAid/API integration tracing. |

Suggested decision for version 1:

- Reuse existing tables when they already match the purpose.
- Add missing columns to existing tables when safer than creating a duplicate table.
- Create new tables only for real gaps such as `weather_logs`, `request_validations`, and `integration_logs`.
- Keep all proposed SQL review-only until the team approves it.

## Important Gaps

The DB has a strong base, but some fields/tables are still needed for the actual RESQPERATION requirements.

### Household Status

Current DB has:

```text
household_statuses
household_disasters
responder_field_reports
hq_field_reports
```

Still needed:

- a clear household status history table
- source tracking for household mobile vs responder field report
- device/battery snapshot per status report
- no `hq_manual` status source

### Household Devices

Current DB has:

```text
device_tokens
device_tracking_logs
```

Still needed:

- device label
- linked household member
- platform
- Expo push token or clarified push token field
- location permission status
- last known location
- last seen timestamp
- active/inactive flag

### Rescuer Accounts

Current DB has:

```text
responders
rescue_teams
```

Still needed:

- link responder profile to `users` if one login table will be used
- emergency contact
- skills/training
- certification reference
- equipment notes
- duty status

### Resources and Requests

Current DB has:

```text
resource_requests
resource_request_status
urgency_levels
```

Still needed:

- source system, such as EvaTrack
- external request reference
- validation status and notes
- validator and validation timestamp
- forwarded system, such as TrackingAid
- TrackingAid reference
- integration log table

## First Thing To Do

The first actual backend task should be:

```text
Fix backend .env for MySQL, then build auth and roles against the existing users/roles table structure.
```

Do not start Household Status, Dispatch, Weather, or Resources controllers until the Laravel models are aligned with the existing DB primary keys.

## Backend Preparation Checklist

Before coding module APIs:

1. Keep `DB_CONNECTION=mysql` in `backend-laravel/.env`.
2. Keep `DB_DATABASE=klint` without trailing spaces.
3. Use `php artisan migrate:status` only to inspect, not migrate.
4. Create or update Laravel models for existing DB tables.
5. Set each model primary key correctly.
6. Decide whether all logins use `users`, or whether `responders` remains a separate credential table.
7. Approve the SQL gap proposal before altering the shared DB.
8. Build backend auth and roles.
9. Add Form Requests and API Resources before module CRUD.

## Development Rule Going Forward

Use `FOR DEV` as the reference source.

Do not read or copy the full `prototype/resqperation-2 (1).html`.

When a UI reference is needed, use the split files under:

```text
FOR DEV/RESQPERATION_PROTOTYPE_MODULES/
```

Only read the specific module needed for the current page.
