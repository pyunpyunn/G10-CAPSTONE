# RESQPERATION System Stack, Resources, APIs, and Legal References

Date scanned: 2026-06-05  
Project folder: `C:\backend\G10CAPSTONE\resqperation-system`

## Purpose

This document lists the technology stack, development resources, APIs, data sources, integrations, legal references, and research basis used or planned for RESQPERATION.

This is a development documentation file, not legal advice. Legal references are listed to explain why the system design is defensible for a barangay disaster response capstone.

## Scan Scope

Files and folders checked:

- `README.md`
- `ResQperation_File_Tree.md`
- `backend-laravel/composer.json`
- `backend-laravel/package.json`
- `backend-laravel/.env.example`
- `backend-laravel/routes/api.php`
- `backend-laravel/routes/console.php`
- `backend-laravel/app/Http/Controllers/Api`
- `backend-laravel/app/Services`
- `frontend-web/package.json`
- `frontend-web/.env.example`
- `frontend-web/src/api`
- `frontend-web/src/pages`
- `frontend-web/src/components`
- `frontend-mobile/package.json`
- `frontend-mobile/.env.example`
- `frontend-mobile/app`
- `frontend-mobile/api`
- `docs`
- `FOR DEV`

Files intentionally not loaded:

- Full prototype HTML files, because they are large UI references only.
- `node_modules`, `vendor`, `dist`, `.git`, `.env`, and private cache folders.

## Project Summary

RESQPERATION is a barangay disaster response and rescue coordination system. It focuses on HQ/Admin web operations, with mobile login support for household and rescuer roles.

Main operational workflows:

- HQ/Admin dashboard and active disaster event overview
- Disaster broadcasting
- Weather monitoring
- Barangay map monitoring
- Household status monitoring
- Rescue dispatch
- Verified rescuer account management
- Resources and requests validation
- Situation reporting
- Archive and CSV export
- Notifications and HQ/Admin profile
- Mobile login for household and rescuer roles

## Repository Structure

```text
resqperation-system/
  backend-laravel/      Laravel JSON API backend
  frontend-web/         React + Vite HQ/Admin web app
  frontend-mobile/      Expo React Native mobile app
  docs/                 Development docs and SQL proposals
  FOR DEV/              Private planning/reference docs
  prototype/            Private prototype reference only
```

Private folders excluded from GitHub:

```text
FOR DEV/
prototype/
.sixth/
.dist/
.claude/
node_modules/
vendor/
.env
```

## Development Rules Used

- Laravel is API only.
- Do not build dashboard pages in Laravel Blade.
- HTML prototype files are UI/UX references only.
- React rebuilds prototype screens as pages and feature-based components.
- Mobile uses Expo screens for household and rescuer roles.
- Database schema changes must be reviewed first before applying to the shared DB.
- Large list pages should use pagination, filtering, and summary endpoints.
- Weather provider calls should run through Laravel, not directly from React.
- API keys and provider tokens must stay in backend `.env`, not in frontend code.

## Backend Stack

| Area | Technology | Evidence |
| --- | --- | --- |
| Runtime | PHP `^8.3` | `backend-laravel/composer.json` |
| Framework | Laravel `^13.8` | `backend-laravel/composer.json` |
| Authentication | Laravel Sanctum `^4.0` | `backend-laravel/composer.json`, `routes/api.php` |
| Database | Shared MySQL | `.env.example`, DB readiness docs |
| Auth tokens | `personal_access_tokens` | Sanctum migration and routes |
| API style | JSON API under `/api/v1` | `backend-laravel/routes/api.php` |
| Validation | Laravel request validation and `LoginRequest` | `app/Http/Requests/LoginRequest.php`, controllers |
| Scheduling | Laravel Scheduler | `backend-laravel/routes/console.php` |
| Weather fetching | Laravel HTTP Client | `WeatherSnapshotService.php` |
| CSV export | Laravel streamed response pattern | `ArchiveController.php` |
| Testing | PHPUnit | `backend-laravel/phpunit.xml`, `tests` |
| Debug/dev tools | Tinker, Pail, Pint, Collision, Faker | `composer.json` |

Backend package highlights:

```text
laravel/framework
laravel/sanctum
laravel/tinker
phpunit/phpunit
fakerphp/faker
laravel/pail
laravel/pint
mockery/mockery
nunomaduro/collision
```

## Backend Modules and Controllers

| Module | Controller or service | Main purpose |
| --- | --- | --- |
| Auth | `AuthController` | Login, logout, current user |
| Dashboard | `DashboardController` | HQ summary, active event, close active event |
| Disaster Broadcasting | `DisasterBroadcastController` | Disaster event lifecycle and broadcasts |
| Weather Updates | `WeatherController`, `WeatherSnapshotService` | Open-Meteo snapshots, PAGASA source links |
| Mapping | `MappingController`, `BarangayProfileService` | Barangay map focus, geotags, evacuation pins, routes |
| Household Status | `HouseholdStatusController` | Household list, detail, history, status reports |
| Rescue Dispatch | `RescueDispatchController` | Teams, assignments, updates, locations |
| Rescuer Accounts | `RescuerAccountController` | Verified rescuer account CRUD |
| Resources and Requests | `ResourceRequestController` | Validation and TrackingAid handoff |
| Situation Reporting | `SituationReportController` | Event summary and saved SitRep snapshots |
| Archive | `ArchiveController` | Archived module records and CSV export |
| Notifications | `NotificationController` | DB-based notification feed and view state |
| Profile | `ProfileController` | HQ/Admin profile and password update |

## Internal API Routes

All active API routes are under:

```text
/api/v1
```

Public route:

```text
POST /auth/login
```

Authenticated routes:

```text
GET  /auth/me
POST /auth/logout
```

HQ/Admin routes:

```text
GET  /dashboard
POST /dashboard/active-event/close

GET  /notifications
POST /notifications/mark-read
POST /notifications/delete-selected
POST /notifications/clear-all

GET   /profile
PATCH /profile
PATCH /profile/password

GET  /disaster-events
POST /disaster-events
GET  /disaster-events/{eventId}/broadcasts
POST /disaster-events/{eventId}/broadcasts

GET  /weather
POST /weather/refresh
GET  /disaster-events/{eventId}/weather-logs
POST /disaster-events/{eventId}/weather-logs/refresh

GET /map/overview
GET /map/household-geotags
GET /map/evacuation-sites
GET /map/dispatch-routes

GET /households
GET /households/{householdId}
GET /households/{householdId}/status-logs

GET  /rescue-teams
GET  /dispatches
POST /dispatches
GET  /dispatches/{assignmentId}
POST /dispatches/{assignmentId}/complete

GET   /rescuers
POST  /rescuers
GET   /rescuers/{responderId}
PATCH /rescuers/{responderId}
POST  /rescuers/{responderId}/deactivate

GET  /resource-requests
POST /resource-requests
GET  /resource-requests/{requestId}
POST /resource-requests/{requestId}/validate
POST /resource-requests/{requestId}/forward
POST /resource-requests/{requestId}/return

GET  /situation-reports
POST /situation-reports
GET  /situation-reports/{sitRepId}
GET  /situation-reports/{sitRepId}/pdf
GET  /disaster-events/{eventId}/situation-summary

GET /archive/disaster-events
GET /archive/household-status-logs
GET /archive/dispatch-logs
GET /archive/resource-requests
GET /archive/situation-reports
GET /archive/export
```

Household/rescuer mobile status route:

```text
POST /households/{householdId}/status-logs
```

Admin/rescuer dispatch update route:

```text
PATCH /dispatches/{assignmentId}
```

Rescuer-only location update route:

```text
PATCH /dispatches/{assignmentId}/location
```

## Backend Scheduled Commands

Active command:

```text
php artisan weather:refresh
```

Purpose:

- Fetches Open-Meteo forecast data.
- Saves a snapshot into `weather_logs`.
- Links the snapshot to the active disaster event when one exists.
- Runs every three hours through Laravel Scheduler.

Deployment note:

```text
php artisan schedule:run
```

On a real server, this must be scheduled every minute through cron or Windows Task Scheduler.

## Web Frontend Stack

| Area | Technology | Evidence |
| --- | --- | --- |
| Framework | React `^19.2.6` | `frontend-web/package.json` |
| Build tool | Vite `^8.0.12` | `frontend-web/package.json` |
| Routing | React Router DOM `^7.16.0` | `frontend-web/package.json`, `App.jsx` |
| HTTP client | Axios `^1.16.1` | `frontend-web/package.json`, `src/api/client.js` |
| Icons | Lucide React `^1.17.0` | `frontend-web/package.json` |
| Mapping | Leaflet `^1.9.4`, React Leaflet `^5.0.0` | `frontend-web/package.json` |
| Styling | Plain CSS | `src/App.css`, `src/index.css` |
| Linting | ESLint | `frontend-web/package.json` |

Web pages:

```text
LoginPage
DashboardPage
BroadcastPage
WeatherPage
MappingPage
HouseholdStatusPage
RescueDispatchPage
RescuerAccountsPage
ResourcesRequestsPage
SituationReportPage
ArchivePage
NotificationsPage
ProfilePage
```

Shared UI components:

```text
ActionMenu
Badge
Button
DataTable
EmptyState
FilterBar
IconButton
LoadingState
Modal
PageHeader
Panel
SearchInput
StatCard
```

Feature-based component groups:

```text
archive/
broadcast/
dashboard/
dispatch/
households/
mapping/
notifications/
profile/
rescuers/
resources/
situation/
weather/
```

Frontend API files:

```text
archiveApi.js
authApi.js
broadcastApi.js
client.js
dashboardApi.js
dispatchApi.js
householdApi.js
mappingApi.js
notificationApi.js
profileApi.js
rescuerApi.js
resourceRequestApi.js
situationReportApi.js
weatherApi.js
```

## Mobile Frontend Stack

| Area | Technology | Evidence |
| --- | --- | --- |
| Framework | Expo `~54.0.34` | `frontend-mobile/package.json` |
| Runtime | React Native `0.81.5` | `frontend-mobile/package.json` |
| React | React `19.1.0` | `frontend-mobile/package.json` |
| Routing | Expo Router `~6.0.23` | `frontend-mobile/package.json`, `app.json` |
| Navigation | React Navigation packages | `frontend-mobile/package.json` |
| HTTP client | Axios `^1.16.1` | `frontend-mobile/api/client.ts` |
| Secure token storage | Expo SecureStore `~15.0.8` | `frontend-mobile/api/client.ts` |
| Location | Expo Location `~19.0.8` | `frontend-mobile/package.json` |
| Notifications | Expo Notifications `~0.32.17` | `frontend-mobile/package.json` |
| Type checking | TypeScript `~5.9.2` | `frontend-mobile/package.json`, `.tsx/.ts` files |

Current mobile implementation:

- Shared mobile login screen exists in `frontend-mobile/app/index.tsx`.
- Auth token is saved through Expo SecureStore.
- Household users route to `/household`.
- Rescuer users route to `/rescuer`.
- HQ/Admin users are told to use the web dashboard.
- Household and rescuer home screens are currently placeholders.

Important current mismatch with early planning:

- Planning docs prefer beginner JavaScript for mobile.
- The actual Expo starter currently uses TypeScript files (`.ts`, `.tsx`).
- This is acceptable if the team can explain it, but future mobile screens should stay simple.

## Database Stack and Structure

Database:

```text
Shared MySQL database
```

Main DB rule:

- Do not run destructive SQL against the shared DB.
- Do not run migrations without team/DB-member approval.
- Use SQL proposal files first when schema changes are needed.

Current shared DB uses custom primary keys, not default Laravel `id`.

Examples:

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

Important tables used by current modules:

```text
users
roles
personal_access_tokens
disaster_events
disaster_types
severity_levels
disaster_broadcasts
notifications
notification_logs
notification_recipients
weather_logs
households
household_members
household_disasters
household_statuses
household_status_logs
device_tokens
device_tracking_logs
geotagged_locations
addresses
puroks
barangays
evacuation_centers
rescue_teams
responders
responder_assignments
responder_field_reports
responder_location_logs
responder_routes
route_coordinates
resource_requests
resource_request_status
request_validations
urgency_levels
situation_reports
incident_archives
audit_logs
```

SQL proposal and readiness files:

```text
docs/RESQPERATION_G10_DB_READINESS_CHECK.md
docs/sql_proposals/2026_06_01_g10_all_sql_additive_update.sql
docs/sql_proposals/2026_06_02_g10_temporary_mobile_login_accounts.sql
docs/sql_proposals/initial/2026_06_03_g10_barangay_profile_review.sql
docs/sql_proposals/initial/2026_06_03_g10_disaster_broadcast_metadata_review.sql
```

## Authentication and Roles

Authentication:

- Laravel Sanctum token authentication.
- Web and mobile both call Laravel API.
- Mobile stores token in Expo SecureStore.
- Web stores token through the frontend token helper.

Known role keys from the shared DB:

```text
super_admin
evac_admin
evac_personnel
admin
rescuer
household_resident
```

Current web access:

- `super_admin`
- `admin`

Current mobile access:

- `household_resident`
- `rescuer`

Temporary development logins:

```text
HQ/Admin:           2024035500 / password
Household Mobile:   2024035501 / password
Rescuer Mobile:     BDRRM-SAR-001 / password
```

New HQ-created rescuer account format:

```text
BDRRM-{TEAM_CODE}-###
Example: BDRRM-SAR-001
```

The sequence is counted per team code. Example: first MED is `BDRRM-MED-001`, first SAR is `BDRRM-SAR-001`, second SAR is `BDRRM-SAR-002`.

Rescuer profile usernames are separate human-readable handles stored in `users.username`, such as `vinzon.arellano`. The BDRRM value stays in `responders.responder_code` and `responders.username`.

New internal rescuer user ID format:

```text
USR-RESCUER-BDRRM-{TEAM_CODE}-###
```

## External APIs, Data Sources, and Providers

### Open-Meteo Forecast API

Status:

```text
Active / implemented
```

Purpose:

- Structured numeric weather forecast.
- Current temperature, humidity, apparent temperature, rainfall, weather code, wind speed, wind direction, and wind gusts.
- Hourly precipitation and wind values.
- Three-day daily forecast.

Implementation:

- Called by Laravel `WeatherSnapshotService`.
- Endpoint used: `https://api.open-meteo.com/v1/forecast`
- Data is saved to `weather_logs`.
- React displays saved logs only.

Reason:

- No API key required.
- Safer for capstone because provider logic stays in Laravel.
- Weather snapshots can be archived and used in situation reports.

Official docs:

- https://open-meteo.com/en/docs

### PAGASA / DOST-PAGASA

Status:

```text
Partly implemented as official links and warning-confirmation source.
PAGASA Ten-Day API is planned after token approval.
```

Purpose:

- Official Philippine weather advisory confirmation.
- Broadcasts should not claim official warnings unless confirmed through PAGASA.
- Weather page includes the note: "Confirm official warnings through PAGASA before broadcasting."

Current implementation:

- Weather page displays PAGASA advisory links.
- Situation report weather summaries also remind HQ/Admin to confirm official PAGASA warnings.
- Backend `.env.example` includes PAGASA Ten-Day API variables:

```text
PAGASA_TENDAY_TOKEN=
PAGASA_TENDAY_BASE_URL=https://tenday.pagasa.dost.gov.ph/api/v1
PAGASA_TENDAY_LOCATION=
```

Current source links in backend:

```text
PAGASA Daily Weather Forecast
PAGASA Tropical Cyclone Bulletin
PAGASA Products and Services
```

Official links:

- https://pagasa.dost.gov.ph/
- https://pagasa.dost.gov.ph/products-and-services
- https://www.pagasa.dost.gov.ph/weather
- https://www.pagasa.dost.gov.ph/tropical-cyclone/severe-weather-bulletin

PAGASA Ten-Day API request draft:

```text
docs/RESQPERATION_PAGASA_TENDAY_API_REQUEST_LETTER.md
```

### OpenStreetMap Tiles

Status:

```text
Active / implemented
```

Purpose:

- Base map tiles for the Mapping page.
- Barangay-level zoom for household geotags, evacuation sites, rescue team markers, and route overlays.

Implementation:

- Leaflet tile URL from `BarangayProfileService`:

```text
https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

Attribution:

- The Leaflet map includes OpenStreetMap contributor attribution.

Official policy:

- https://operations.osmfoundation.org/policies/tiles/

### Leaflet and React Leaflet

Status:

```text
Active / implemented
```

Purpose:

- Interactive map rendering.
- Household status dots.
- Evacuation site pins.
- Rescue team markers.
- Route polylines.
- Layer toggles.

Official docs:

- https://leafletjs.com/
- https://leafletjs.com/examples/quick-start/
- https://react-leaflet.js.org/

### OSRM Public Routing Server

Status:

```text
Active for on-demand route line requests in frontend mapping API.
```

Purpose:

- Route from a team/current point to an evacuation site.
- Used only on demand when HQ/Admin requests a route.

Implementation:

```text
https://router.project-osrm.org/route/v1/driving/{start};{end}?overview=full&geometries=geojson
```

Current file:

```text
frontend-web/src/api/mappingApi.js
```

Operational caution:

- This is a public demo routing service.
- It should not be spammed.
- For production, use a managed routing provider or self-hosted OSRM if routing becomes mission-critical.

### OpenRouteService

Status:

```text
Referenced / optional, not the current active routing implementation.
```

Purpose:

- Optional route provider for evacuation routes and more advanced routing.
- Requires API key.

Reference file:

```text
FOR DEV/API_REFERENCE.md
```

Official docs:

- https://openrouteservice.org/dev/#/api-docs

### Expo Push Notifications

Status:

```text
Planned / packages installed, full push workflow deferred.
```

Purpose:

- Future disaster broadcast push notifications to household and rescuer devices.

Current state:

- `expo-notifications` is installed in `frontend-mobile`.
- Database already has notification-related tables.
- Current version stores notification/broadcast records and displays web notification feed.
- Push sending/receiving is not final yet.

Official docs:

- https://docs.expo.dev/versions/latest/sdk/notifications/

### SafeTrack

Status:

```text
Conceptual/shared-DB integration.
```

Purpose:

- Household accounts are expected to come from the other household registration system.
- RESQPERATION should authenticate/read household users from shared DB records.
- RESQPERATION should not create public household registration.

Current implementation:

- Household users authenticate through `users`.
- Household status and household detail use shared DB household/device tables.

### EvaTrack

Status:

```text
Conceptual/shared-DB integration.
```

Purpose:

- External source of resource/personnel requests.

Current implementation:

- Resource request source options include `evatrack`.
- Web page has "Sync EvaTrack", which reloads latest shared DB requests.
- No separate external API connector exists yet because systems are using one shared DB.

### TrackingAid

Status:

```text
Conceptual/shared-DB handoff and mirror.
```

Purpose:

- TrackingAid is expected to handle delivery/release tracking after RESQPERATION validates a resource request.

Current implementation:

- RESQPERATION validates requests only.
- Forward action marks a request ready for TrackingAid handoff.
- Tracking reference is stored.
- TrackingAid mirror panel displays request availability/queue summary.

Important scope rule:

- RESQPERATION validates and forwards.
- RESQPERATION does not own delivery fulfillment.

## Environment Variables Used

Backend examples:

```text
DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD

SYSTEM_BARANGAY_NAME
SYSTEM_BARANGAY_LATITUDE
SYSTEM_BARANGAY_LONGITUDE
SYSTEM_CITY_NAME
SYSTEM_PROVINCE_NAME
SYSTEM_MAP_ZOOM

WEATHER_LOCATION_NAME
WEATHER_LATITUDE
WEATHER_LONGITUDE
WEATHER_SSL_VERIFY

PAGASA_TENDAY_TOKEN
PAGASA_TENDAY_BASE_URL
PAGASA_TENDAY_LOCATION
```

Web examples:

```text
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Mobile examples:

```text
EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Security rule:

- `.env` files must not be committed.
- PAGASA token, future routing keys, and DB credentials should stay private.

## Legal and Government Frameworks

### Republic Act No. 10121

Name:

```text
Philippine Disaster Risk Reduction and Management Act of 2010
```

System relevance:

- Supports the system purpose: disaster preparedness, response, monitoring, coordination, and reporting.
- Supports local disaster risk reduction and management operations.
- Supports barangay-level disaster risk reduction and management through BDRRMC/LDRRMC functions.
- Supports warning, alerting, information sharing, operations centers, resource mobilization, and volunteer participation.

System design affected:

- Active disaster event lifecycle.
- Disaster broadcast records.
- Household status monitoring.
- Rescue dispatch monitoring.
- Resource request validation.
- Situation reports.
- Archive and audit trail.
- Barangay-focused deployment profile.

Official reference:

- https://elibrary.judiciary.gov.ph//thebookshelf//showdocs/2/21121

### Republic Act No. 10173

Name:

```text
Data Privacy Act of 2012
```

System relevance:

- RESQPERATION processes personal data, household data, device data, contact numbers, emergency contacts, GPS/location records, and account credentials.

System design affected:

- Role-based access control.
- No public rescuer registration.
- No public household registration in RESQPERATION.
- Authenticated APIs.
- Secure token storage in mobile.
- API keys/tokens kept out of frontend.
- Audit logs for user actions.
- Minimal map payloads instead of full household profiles.
- Need for clear data retention and authorized access rules before deployment.

Official reference:

- https://privacy.gov.ph/data-privacy-act/

### Republic Act No. 9418

Name:

```text
Volunteer Act of 2007
```

System relevance:

- Relevant to verified responders/rescuers and volunteer participation in barangay disaster response.

System design affected:

- HQ/Admin creates rescuer accounts manually.
- Rescuers are assigned teams and roles.
- Rescuer profile includes contact, ICE, training, skills, certification reference, equipment notes, and duty availability.
- No pending public rescuer registration is used.

Official reference:

- https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/7802

### Republic Act No. 10175

Name:

```text
Cybercrime Prevention Act of 2012
```

System relevance:

- Relevant to unauthorized access, account misuse, and protection of computer systems.

System design affected:

- Login required for protected routes.
- Role middleware prevents unauthorized module access.
- Sanctum token authentication.
- Password update flow.
- Audit logs for sensitive actions.
- No exposed database credentials or provider tokens in frontend.

Official reference:

- https://elibrary.judiciary.gov.ph//thebookshelf//showdocs/2/50264

### NDRRMP / NDRRMC Disaster Planning References

Name:

```text
National Disaster Risk Reduction and Management Plan / NDRRMC references
```

System relevance:

- Provides the wider national planning context for disaster prevention, mitigation, preparedness, response, rehabilitation, monitoring, and reporting.

System design affected:

- Situation report structure.
- Archive and monitoring records.
- Dashboard summary.
- Disaster event lifecycle.
- Dispatch and resources workflow.

Reference:

- https://ndrrmc.gov.ph/
- https://ndrrmc.gov.ph/attachments/article/4147/NDRRMP-Pre-Publication-Copy-v2.pdf

### PAGASA Official Weather Context

Name:

```text
DOST-PAGASA official weather products and services
```

System relevance:

- Weather warnings and advisories should be confirmed through official PAGASA sources before public broadcasting.

System design affected:

- Weather Updates page includes official PAGASA advisory links.
- Broadcast workflow should treat Open-Meteo as numeric forecast support, not official warning authority.
- Situation reports include weather source labels.

Reference:

- https://pagasa.dost.gov.ph/products-and-services
- https://www.pagasa.dost.gov.ph/tropical-cyclone/severe-weather-bulletin

## Research and Development References

Local planning references:

```text
docs/RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md
docs/RESQPERATION_REQUIREMENTS_BREAKDOWN.md
docs/RESQPERATION_RESOURCES.txt
docs/RESQPERATION_UPDATED_STEP_GUIDE.md
docs/RESQPERATION_G10_BEGINNER_MODULE_PROMPTS.md
docs/RESQPERATION_G10_STEP_BY_STEP_CHECKLIST.md
docs/RESQPERATION_BARANGAY_PROFILE_SCOPE.md
docs/RESQPERATION_PAGASA_TENDAY_API_REQUEST_LETTER.md
FOR DEV/API_REFERENCE.md
FOR DEV/PUSH_NOTIFICATIONS.md
FOR DEV/GEOTAGGING_DISPATCH_ROUTING.md
FOR DEV/RESQPERATION_DEVELOPMENT_PROMPTS_BY_MODULE.md
```

Official technical references:

```text
Laravel installation:
https://laravel.com/docs/13.x/installation

Laravel Sanctum:
https://laravel.com/docs/sanctum

Laravel HTTP Client:
https://laravel.com/docs/http-client

Laravel Scheduler:
https://laravel.com/docs/scheduling

Laravel Validation:
https://laravel.com/docs/validation

Laravel streamed downloads:
https://laravel.com/docs/responses#streamed-downloads

React:
https://react.dev/learn

Vite:
https://vite.dev/guide/

React Router:
https://reactrouter.com/

Axios:
https://axios-http.com/

Lucide React:
https://lucide.dev/guide/packages/lucide-react

Expo:
https://docs.expo.dev/

Expo Router:
https://docs.expo.dev/router/introduction/

Expo SecureStore:
https://docs.expo.dev/versions/latest/sdk/securestore/

Expo Location:
https://docs.expo.dev/versions/latest/sdk/location/

Expo Notifications:
https://docs.expo.dev/versions/latest/sdk/notifications/

Leaflet:
https://leafletjs.com/

React Leaflet:
https://react-leaflet.js.org/

OpenStreetMap:
https://www.openstreetmap.org/

OpenStreetMap tile policy:
https://operations.osmfoundation.org/policies/tiles/

Open-Meteo:
https://open-meteo.com/en/docs

PAGASA:
https://pagasa.dost.gov.ph/
```

## Security and Privacy Design Notes

Current security controls:

- Sanctum token authentication.
- Role-based middleware.
- Separate web and mobile role handling.
- Password hashing through Laravel.
- API validation messages.
- Mobile token saved through Expo SecureStore.
- Notification actions stored through `audit_logs`.
- Sensitive `.env` files excluded from Git.

Privacy-sensitive data handled:

- Household members.
- Household account references.
- Mobile device tokens.
- Device battery level.
- Last known GPS location.
- Rescuer emergency contacts.
- Rescuer training/certification details.
- Disaster reports and status history.
- Resource request contact details.

Recommended future controls before real deployment:

- Add clear data retention rules for old disaster events and location logs.
- Add privacy notice and consent text for mobile geotagging.
- Limit exported CSV access to authorized HQ/Admin only.
- Add audit logs for all export actions.
- Review whether exact household GPS should be hidden or blurred for non-essential roles.
- Add production HTTPS before using mobile login outside local network.
- Add secure password reset or admin reset flow.
- Add backup/restore procedure for shared database.

## Current Implementation Status by Module

| Module | Current status |
| --- | --- |
| Auth and role routing | Implemented |
| HQ/Admin dashboard | Implemented |
| Shared web shell/components | Implemented |
| Barangay profile foundation | Implemented with `.env` fallback; DB profile table still proposal-only |
| Household status web/API | Implemented v1 |
| Rescue dispatch web/API | Implemented v1 |
| Disaster broadcasting web/API | Implemented v1 |
| Weather updates web/API | Implemented v1 with Open-Meteo snapshots and PAGASA links |
| Mapping web/API | Implemented v1 with Leaflet/OSM and OSRM route-on-demand |
| Rescuer accounts web/API | Implemented v1 |
| Resources and requests web/API | Implemented v1 |
| Situation reporting web/API | Implemented v1, PDF pending |
| Archive web/API | Implemented v1, CSV supported, PDF pending |
| Notifications and profile | Implemented v1 |
| Household mobile | Login and placeholder only |
| Rescuer mobile | Login and placeholder only |
| Push notifications | Planned |
| PDF exports | Planned |
| Final QA | Pending |

## Useful Commands

Backend:

```bash
cd backend-laravel
php artisan serve --host=127.0.0.1 --port=8000
php artisan test
php artisan route:list
php artisan config:clear
php artisan weather:refresh
php artisan schedule:list
php artisan schedule:run
```

Web:

```bash
cd frontend-web
npm install
npm run dev
npm run lint
npm run build
```

Mobile:

```bash
cd frontend-mobile
npm install
npx expo start
npx tsc --noEmit
npm run lint
```

## Defensible System Explanation

RESQPERATION uses a standard full-stack architecture:

```text
React web app / Expo mobile app
        -> Axios HTTP requests
        -> Laravel JSON API
        -> Shared MySQL database
        -> Weather/map/provider integrations through controlled services
```

The strongest technical justifications are:

- Laravel is kept as API only.
- React handles web UI.
- Expo handles mobile UI.
- Sanctum protects all private routes.
- Role middleware limits user actions.
- Weather snapshots are stored before display.
- PAGASA remains the official warning confirmation source.
- Mapping payloads are intentionally small.
- Resource requests are validated before TrackingAid handoff.
- Household status cannot be manually edited by HQ/Admin.
- Large tables use pagination/search/filtering.
- Shared DB changes are handled through proposals and additive updates.

## Items Still Needed Before Defense or Deployment

- Final QA checklist execution.
- PDF export implementation or clear "planned" explanation.
- Mobile household status submission.
- Mobile rescuer dispatch update workflow.
- Push notification workflow.
- Barangay Profile settings UI.
- Production deployment plan.
- Privacy notice and consent text for location/device data.
- Backup procedure for shared MySQL.
- User manual or operations guide for HQ/Admin.
