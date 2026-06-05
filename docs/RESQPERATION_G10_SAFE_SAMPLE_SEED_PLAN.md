# RESQPERATION G10 Safe Sample Seed Plan

This document explains the small sample dataset used for development testing.

The sample data is intentionally labeled with `DEV TEST` / `DEV-TEST` so the team can easily separate it from real barangay records.

## Seeder File

```text
backend-laravel/database/seeders/SampleDisasterStatusSeeder.php
```

The seeder is also called by:

```text
backend-laravel/database/seeders/DatabaseSeeder.php
```

## Safety Rules

- No tables are created.
- No tables are deleted.
- No records are deleted.
- Existing lookup tables are reused.
- The household status source is `household_mobile`, not manual HQ/Admin editing.
- The sample is idempotent, so rerunning the seeder should not duplicate the status history logs.

## Tables Touched

The sample seeder writes only to these tables:

```text
disaster_events
household_disasters
household_status_logs
```

The seeder reads these existing lookup/support tables:

```text
disaster_types
severity_levels
household_statuses
households
```

## Sample Active Disaster Event

```text
event_id: DEV-TEST-FLOOD-20260602
name: DEV TEST - Active Flood Monitoring
type: Flood
severity: High
active rule: ended_at is NULL
```

## Sample Household Status Data

The sample uses the temporary household account:

```text
household_id: HH-2024035501
household user: USR-HH-2024035501
```

Latest household disaster row:

```text
household_disaster_id: 2026060201
initial status: not_evacuated
current status: evacuated
source: household_mobile
battery level: 72
priority: monitor
needs dispatch: 0
```

Status history:

```text
1. not_evacuated
   source: household_mobile
   location: DEV TEST - Home area
   battery: 82

2. evacuated
   source: household_mobile
   location: DEV TEST - Barangay evacuation area
   battery: 72
```

## Command Used

Run only the sample disaster/status seeder:

```bash
php artisan db:seed --class=SampleDisasterStatusSeeder --force
```

Run all approved seeders:

```bash
php artisan db:seed --force
```

## Verification Queries

```sql
SELECT event_id, name, started_at, ended_at
FROM disaster_events
WHERE event_id = 'DEV-TEST-FLOOD-20260602';

SELECT household_disaster_id, household_id, disaster_id, current_status_id,
       last_status_source, last_battery_level, needs_dispatch
FROM household_disasters
WHERE household_disaster_id = 2026060201;

SELECT status_id, source, location_label, battery_level, submitted_at
FROM household_status_logs
WHERE disaster_id = 'DEV-TEST-FLOOD-20260602'
  AND household_id = 'HH-2024035501'
ORDER BY submitted_at;
```

## Beginner Explanation

This sample is only for checking if the Household Status module can display an active disaster, the latest household status, and status history. HQ/Admin should only review this data. The status itself comes from the household mobile source.

Do not use this as real incident data.
