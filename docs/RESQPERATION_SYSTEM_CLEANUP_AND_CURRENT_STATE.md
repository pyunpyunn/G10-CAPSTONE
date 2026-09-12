# RESQPERATION System Cleanup and Current State

Last refreshed: July 4, 2026

This document records the current cleaned-up system state so future work starts from the same understanding.

## Cleanup Performed

- Removed generated mobile/backend runtime log files that were not needed for source control.
- Left two Laravel serve logs in place because a running backend process still had them locked.
- Kept source code, migrations, SQL proposals, and user work intact.
- Updated the core documentation to reflect current OneSignal, TrackingAid, mobile, map, and request-flow implementation.

## Current Active Project Folder

Use this folder as the maintained capstone project:

```text
C:\backend\G10CAPSTONE\resqperation-system
```

Do not work in the older `C:\backend\CAPSTONE\resqperation` copy unless the team intentionally migrates back to it.

## Current System Scope

RESQPERATION now contains:

- Laravel API backend
- React/Vite HQ/Admin web frontend
- Expo React Native household and rescuer mobile frontend
- Shared MySQL main database connection
- TrackingAid second database connection for forwarded requests
- OneSignal mobile notification registration and sending
- Web mapping with Leaflet/OpenStreetMap
- Mobile mapping through native map support or external map links depending on build/API-key availability

## Implemented HQ/Admin Web Modules

- Dashboard
- Disaster Broadcasting
- Weather Updates
- Mapping
- Household Status
- Rescue Dispatch
- Rescuer Accounts
- Resources and Requests
- Situation Reporting
- Archive
- Notifications
- Profile
- Super Admin / Inquiries where the route is enabled

## Implemented Mobile Flows

Household mobile:

- Login through Laravel API
- First-time setup with location permission and geotag setup
- Household/family member display
- Household/member status update
- QR access
- Trusted household flow where enabled
- Device/token registration after login

Rescuer mobile:

- Login through Laravel API
- Assignment display and status updates
- Location tracking
- Route/map view
- Resource request creation
- Team radio/PTT voice clip workflow
- Device/token registration after login

## Current External Integrations

| Integration | Current state |
| --- | --- |
| SafeTrack | RESQPERATION reads household/account identity from the active shared DB records. |
| EvaTrack | External request intake endpoint exists: `POST /api/v1/external/resource-requests`. |
| TrackingAid | Second DB connection exists. Forwarded requests are written to `resqperation_forwarded_requests`. TrackingAid inventory is read from their `inventory` table when available. |
| OneSignal | Mobile app registers Player IDs; Laravel sends push through `OneSignalNotificationService`. |
| Open-Meteo | Laravel weather snapshots are stored in `weather_logs`. |
| PAGASA | Official warning confirmation links are documented; Ten-Day API remains optional/pending token approval. |

## Current Request Flow

1. EvaTrack, rescuer mobile, or HQ/Admin creates a request.
2. RESQPERATION stores the request in `resource_requests`.
3. HQ/Admin validates, returns, rejects, or forwards the request.
4. Forwarded requests are upserted into TrackingAid `resqperation_forwarded_requests`.
5. RESQPERATION keeps its validation and forwarding audit trail for SitRep and Archive.
6. TrackingAid owns release, delivery, and fulfillment after handoff.

Detailed guide:

```text
docs/RESQPERATION_TRACKINGAID_REQUEST_INTEGRATION_GUIDE.md
```

## Current Notification Flow

1. Mobile user logs in.
2. App requests notification permission.
3. App initializes OneSignal with `EXPO_PUBLIC_ONESIGNAL_APP_ID`.
4. App sends the OneSignal Player ID to Laravel.
5. Laravel stores it in `device_tokens.player_id` with `push_provider = onesignal`.
6. Disaster broadcasts and dispatch assignments can call `OneSignalNotificationService`.

Detailed guide:

```text
docs/RESQPERATION_ONESIGNAL_MOBILE_SETUP.md
docs/RESQPERATION_SHARED_DB_AUDIT_AND_PUSH_TOKENS.md
```

## Development Rules

- Do not run `migrate:fresh`, `db:wipe`, or destructive seeders on the shared DB.
- Main DB connection should be changeable from `backend-laravel/.env`.
- TrackingAid DB connection should be changeable from `TRACKINGAID_DB_*` values.
- Do not display fake fallback production data when the shared DB is empty or unreachable.
- Keep API logic in Laravel services and keep controllers thin.
- Keep frontend pages focused; move repeated UI into feature components.
- Keep private credentials out of committed docs and source files.

## Cleanup Still Safe To Do Later

- Delete locked `backend-laravel/laravel-serve.log` and `backend-laravel/laravel-serve.err.log` after stopping the backend server.
- Regenerate `docs/ResQperation_File_Tree.md` if the team wants an exact final file-tree snapshot.
- Review old SQL proposal files only as historical references; do not run them blindly on the shared DB.

