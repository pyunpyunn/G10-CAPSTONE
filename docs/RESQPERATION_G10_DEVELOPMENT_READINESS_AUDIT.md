# RESQPERATION G10 Development Readiness Audit

Date reviewed: 2026-06-01  
Correct project folder: `C:\backend\G10CAPSTONE\resqperation-system`

## Scope

This audit is for the actual development project in `G10CAPSTONE`.

I read the planning, API, notification, mapping, resource, and setup documents in `FOR DEV`. I did not read the huge HTML prototype or the prototype module files because they are only UI references and can drain context quickly.

Files reviewed:

- `FOR DEV/RESQPERATION_UPDATED_STEP_GUIDE.md`
- `FOR DEV/RESQPERATION_REQUIREMENTS_BREAKDOWN.md`
- `FOR DEV/RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md`
- `FOR DEV/RESQPERATION_DEVELOPMENT_PROMPTS_BY_MODULE.md`
- `FOR DEV/RESQPERATION_RESOURCES.txt`
- `FOR DEV/API_REFERENCE.md`
- `FOR DEV/PUSH_NOTIFICATIONS.md`
- `FOR DEV/GEOTAGGING_DISPATCH_ROUTING.md`
- `FOR DEV/tools/split-resqperation-prototype.js`
- current backend, web, and mobile skeleton files

No database migration was run. No shared MySQL table was edited.

## Current Project State

### Backend

Folder: `backend-laravel`

Current state:

- Laravel 13 project.
- PHP requirement is `^8.3`.
- Sanctum is installed.
- `routes/api.php` only has the default authenticated `/user` route.
- `users`, `cache`, `jobs`, and `personal_access_tokens` migrations exist.
- No RESQPERATION domain tables, controllers, requests, resources, or seeders are implemented yet.

Backend decision:

Laravel must be an API server only. Do not put dashboard HTML, mobile HTML, or React pages inside Laravel Blade. Backend returns JSON only.

### Web Frontend

Folder: `frontend-web`

Current state:

- React + Vite project exists.
- Installed packages already include Axios, React Router, Lucide React, Leaflet, and React Leaflet.
- The app is still mostly the default Vite starter page.

Web decision:

Use React pages and shared components. Match the prototype look, but rebuild the UI using arrays, components, API calls, and pagination.

### Mobile Frontend

Folder: `frontend-mobile`

Current state:

- Expo app exists.
- It currently uses Expo Router and TypeScript starter files.
- It already has many required mobile packages, including Axios, React Navigation packages, Expo SecureStore, Expo Location, and Expo Notifications.

Mobile decision:

Use one mobile app for both household and rescuer roles. Do not make two mobile projects. After login, route the user based on role.

Recommended beginner folder idea:

```text
frontend-mobile/
  src/
    api/
    screens/
      household/
      rescuer/
    navigation/
    components/
```

If the team keeps Expo Router, use role folders such as:

```text
app/
  household/
  rescuer/
```

Do not create a separate backend for mobile. Both web and mobile call the same Laravel API.

## Main Architecture

Use this final architecture for development:

```text
React web app       -> Laravel API -> MySQL
Expo mobile app     -> Laravel API -> MySQL
Laravel scheduler   -> Weather APIs -> weather_logs
Laravel broadcast   -> Expo push service -> mobile devices
Leaflet web map     -> Laravel map endpoints + OSM/OpenFreeMap tiles
```

The backend owns:

- authentication
- role checks
- validation
- database writes
- weather fetch and storage
- notification dispatch
- archive and export generation
- integration logs for external systems

The frontend owns:

- UI rendering
- forms
- modals
- tables
- filters
- maps
- calling Laravel endpoints

## Important Development Decisions

### 1. One Backend for Web and Mobile

There should only be one Laravel backend.

Reason:

- avoids duplicated APIs
- keeps validation in one place
- makes defense easier to explain
- lets web and mobile share the same auth/token system

### 2. One Mobile App, Two Role Flows

Keep one Expo app.

After login:

- `household` goes to household screens
- `rescuer` goes to rescuer screens

This is easier than maintaining two apps and still matches the system roles.

### 3. Weather Must Go Through Laravel

`API_REFERENCE.md` shows that Open-Meteo can be called directly from React. For this capstone, use the safer requirement from the main docs:

```text
Laravel fetches weather data -> saves weather_logs -> React displays saved logs
```

Reason:

- weather logs are needed for archive and situation reports
- API keys stay out of the frontend
- the system has one trusted place for weather source logic
- this is easier to defend technically

Use:

- PAGASA RSS or PAGASA website advisories for official context
- Open-Meteo for structured numeric weather data
- Laravel scheduler later, after manual refresh works

### 4. Household Status Is Not Manually Edited by HQ

HQ/admin should review household status, not manually set it.

Allowed household status sources:

- household mobile report
- authenticated rescuer field report
- approved external import, if added later

This avoids pretending HQ personally checked a household on-site.

HQ can:

- view latest status
- open household details
- review history
- filter unsafe or unchecked households
- use status data for dispatch decisions

HQ should not:

- directly change a household from unsafe to safe
- overwrite mobile or responder field reports

### 5. Household Devices Must Be Tracked

Many family members may share one household account. The system must still track devices.

Required device data:

- device label or owner/member name
- platform
- battery level
- location permission status
- last known latitude and longitude
- last location time
- last seen time
- active/inactive status

This helps HQ know whether a household is silent because they are safe, unreachable, offline, or low battery.

### 6. Mapping Is Important, But Keep It Simple First

Version 1 map:

- Leaflet and React Leaflet
- OpenStreetMap or OpenFreeMap tiles
- normal household markers
- evacuation site markers
- rescue team markers
- route line on demand
- filters by status and purok

Avoid in version 1:

- advanced GIS
- mandatory clustering
- route optimization
- WebSockets

### 7. Location Tracking Is Foreground Only

For rescuer live tracking, use Expo Location while the app is open.

This is enough for capstone and easier to explain. Background tracking is more complex and not needed for the first version.

### 8. Notifications Should Be Built in Two Stages

Version 1:

- save broadcast logs in the database
- mobile app fetches alerts after login or refresh

Version 2:

- register Expo push tokens
- send push notifications through Expo Push Service
- still save every broadcast in the database

This makes the core system work before adding mobile push complexity.

### 9. Resources and Requests Scope

The system validates requests. It does not deliver resources.

Correct flow:

```text
EvaTrack or manual request -> RESQPERATION validation -> forward to TrackingAid/HQ -> record outcome/reference
```

The Resources and Requests page should show:

- incoming requests
- source system
- validation status
- urgency
- missing info or duplicate flags
- who validated it
- forwarding status
- TrackingAid reference if available

### 10. Shared Database Safety

The shared MySQL database must not be changed casually.

Correct process:

1. prepare SQL review script
2. review names and columns with groupmates/instructor
3. back up shared data if needed
4. convert approved SQL to Laravel migrations
5. run migrations only after approval

## Beginner Code Rules

Use these rules in every coding task:

- JavaScript first, not TypeScript, unless the team intentionally keeps the Expo Router scaffold.
- Simple React function components.
- `useState` and `useEffect`.
- Axios API files.
- React Router for web.
- One Laravel controller per module.
- Form Request classes for validation.
- API Resource classes for JSON output.
- Eloquent relationships.
- Pagination for large lists.
- Summary endpoints for dashboard counts.
- No backend HTML dashboard.
- No Redux/Zustand in version 1.
- No WebSockets in version 1.
- No repository pattern everywhere.

Defense explanation:

```text
The prototype repeated UI blocks manually. The actual system uses reusable React components and arrays to render repeated cards, filters, tabs, rows, and actions. This keeps the code shorter while preserving the requirements.
```

## Recommended Build Order

1. Review database schema proposal.
2. Implement backend auth and roles.
3. Add role middleware and seed users.
4. Add domain migrations after approval.
5. Build web login and app shell.
6. Build shared web components.
7. Build dashboard summary API/page.
8. Build household status API/page.
9. Build rescue dispatch API/page.
10. Build disaster broadcast logs.
11. Build weather refresh and logs.
12. Build mapping endpoints and page.
13. Build rescuer accounts.
14. Build resources and requests validation.
15. Build situation reports and archive.
16. Build mobile login.
17. Build household mobile screens.
18. Build rescuer mobile screens.
19. Add CSV/PDF exports.
20. Add Expo push notifications after core flows work.

## Immediate Next Development Task

Start with backend auth and roles only.

Do not start all modules at once. The clean first milestone is:

- login
- logout
- `/api/v1/auth/me`
- roles
- protected routes
- seed accounts

After that, build the approved tables.

