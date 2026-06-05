# RESQPERATION Barangay Profile Scope

## Purpose

RESQPERATION clients are barangays. The system needs one active Barangay Profile so every module focuses on the same location.

Without this, the app can accidentally show one barangay on the dashboard, another barangay on the map, and another location for weather. That is unsafe for disaster operations.

## Current Decision

Use one active Barangay Profile as the deployment setting.

This profile should control:

- dashboard subtitle and command overview label
- weather location and forecast coordinates
- map center, bounds, and zoom
- household filtering by `addresses.barangay_id`
- purok list used in household status and disaster broadcasting
- evacuation sites and rescue map layers
- situation reports and archive labels

## Existing DB Reuse

The shared DB already has:

```text
barangays
addresses
puroks
```

Do not duplicate the barangay master list.

Use `barangays.barangay_id` when available, then store RESQPERATION-specific deployment settings in the proposed `barangay_profiles` table.

Review-only SQL proposal:

```text
docs/sql_proposals/initial/2026_06_03_g10_barangay_profile_review.sql
```

## Current Implementation

The backend now has `BarangayProfileService`.

Behavior:

1. If `barangay_profiles` exists and has an active row, Laravel uses that row.
2. If the table does not exist yet, Laravel uses `.env` fallback values.

This lets development continue while the DB member reviews the table proposal.

## Needed UI Later

Add a simple HQ/Admin settings page or profile modal:

```text
Barangay Profile
```

Fields:

- barangay from existing `barangays` list
- display name
- city
- province
- office address
- contact number
- email
- map center latitude
- map center longitude
- map zoom
- weather location name
- weather latitude
- weather longitude

Rules:

- Only HQ/Admin can update the Barangay Profile.
- Keep only one active profile at a time.
- Do not allow changing the active barangay while there is an active disaster event, unless the user confirms the operational risk.
- After changing the profile, all modules should reload using the new active barangay.

## Beginner Explanation

Think of Barangay Profile as the system's home base.

Once the barangay is registered, the dashboard, map, weather, households, broadcasts, and reports should all use that same barangay.

