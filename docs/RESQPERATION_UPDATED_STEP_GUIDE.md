# RESQPERATION Updated Step Guide

Use this instead of the Claude-generated `ResQperation_StepGuide.docx` when starting development.

Status: updated for the current stack and beginner-friendly implementation.

Related files:

- `RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md`
- `RESQPERATION_REQUIREMENTS_BREAKDOWN.md`
- `RESQPERATION_RESOURCES.txt`
- `resqperation-2 (1).html`

## 0. Non-Negotiable Development Rules

1. Laravel is API only.
2. Do not put dashboard HTML in Laravel Blade.
3. React web app handles HQ/admin/rescuer web screens.
4. Expo mobile app handles household and rescuer mobile screens.
5. Use the prototype for UI/UX reference, not as code to paste.
6. Use beginner-friendly code.
7. Render repeated UI from arrays with `.map()`.
8. Use pagination for large lists.
9. Use summary endpoints for dashboard counters.
10. Add real-time/WebSockets only after the basic system works.

AI prompt line to paste into every coding prompt:

```text
Write beginner-friendly code. Do not over-engineer. Use simple Laravel controllers, Eloquent, Form Requests, API Resources, React function components, useState/useEffect, Axios, and reusable components. Render repeated cards, filters, tabs, and table rows from arrays using .map(). Match the prototype UI/UX but do not copy it as one huge file.
```

## 1. Updated Stack

Backend:

- Laravel 13 if PHP 8.3+ is supported
- Laravel 12 fallback if the school computers/hosting cannot run Laravel 13
- Sanctum for auth
- MySQL shared database
- Laravel HTTP Client for weather API requests

Web:

- React + Vite
- JavaScript
- React Router
- Axios
- Lucide React
- Plain CSS design system based on prototype

Mobile:

- Expo React Native
- JavaScript
- React Navigation
- Axios
- Expo SecureStore
- Expo Location
- Expo Notifications later

Avoid in version 1:

- Laravel 11 unless required by instructor
- Laravel Reverb as a required feature
- Redux/Zustand
- TypeScript
- Next.js
- Inertia
- Tailwind rewrite of all prototype styles
- NativeWind as a requirement

## 2. Project Folder Setup

Create:

```text
resqperation-system/
  backend-laravel/
  frontend-web/
  frontend-mobile/
  docs/
  prototype/
```

Copy:

```text
prototype/resqperation-2 (1).html
docs/RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md
docs/RESQPERATION_REQUIREMENTS_BREAKDOWN.md
docs/RESQPERATION_RESOURCES.txt
docs/RESQPERATION_UPDATED_STEP_GUIDE.md
```

## 3. Setup Commands

### Backend

```bash
composer create-project laravel/laravel backend-laravel
cd backend-laravel
php artisan install:api
php artisan serve
```

Backend `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=10.150.111.114
DB_PORT=3306
DB_DATABASE=klint
DB_USERNAME=groupmate
DB_PASSWORD=password123
```

### Web

```bash
npm create vite@latest frontend-web -- --template react
cd frontend-web
npm install
npm install axios react-router-dom lucide-react leaflet react-leaflet
npm run dev
```

Create `frontend-web/.env`:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

### Mobile

```bash
npx create-expo-app@latest frontend-mobile
cd frontend-mobile
npm install axios
npm install @react-navigation/native @react-navigation/native-stack
npx expo install expo-secure-store expo-location expo-notifications react-native-screens react-native-safe-area-context
npx expo start
```

## 4. Master Build Order

Build in this exact order:

1. Folder setup
2. Backend auth and roles
3. Database migrations
4. Seeders
5. Web login
6. Web app shell
7. Dashboard API
8. Dashboard page
9. Household API
10. Household page
11. Dispatch API
12. Dispatch page
13. Broadcast API
14. Broadcast page
15. Weather API
16. Weather page
17. Mapping API
18. Mapping page
19. Rescuer accounts API/page
20. Resources and requests API/page
21. Situation reporting API/page
22. Archive API/page
23. Mobile login
24. Household mobile screens
25. Rescuer mobile screens
26. CSV/PDF exports
27. Final UI polish

## 5. Step-by-Step Build Guide

## Step 1: Backend Auth and Roles

Files:

```text
backend-laravel/routes/api.php
backend-laravel/app/Models/User.php
backend-laravel/app/Models/Role.php
backend-laravel/app/Http/Controllers/Api/AuthController.php
backend-laravel/app/Http/Requests/LoginRequest.php
backend-laravel/app/Http/Resources/UserResource.php
```

Create roles:

```text
admin
hq_dispatcher
rescuer
household
```

Endpoints:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

Checklist:

- Login returns token and role.
- Wrong password returns validation error.
- `/me` returns current user.
- Logout removes token.

## Step 2: Database Migrations

Create these tables:

```text
users
roles
role_user
disaster_events
broadcast_logs
weather_logs
households
household_members
household_devices
household_geotags
household_status_logs
evacuation_sites
rescue_teams
rescuer_profiles
rescue_dispatches
dispatch_routes
resource_items
resource_requests
request_validations
situation_reports
audit_logs
```

Beginner rule:

- Use `status` columns instead of many booleans.
- Use foreign keys.
- Add indexes to event, status, date, and purok columns.

Checklist:

- `php artisan migrate` runs.
- Shared DB does not conflict with groupmates.
- Tables use snake_case plural names.

## Step 3: Seeders

Seed:

- roles
- admin/HQ/rescuer/household users
- Typhoon Carina event
- sample households from prototype
- rescue teams from prototype
- sample dispatch logs
- sample resource requests
- sample weather logs
- sample situation report

Checklist:

- Login test accounts exist.
- Dashboard has data.
- Household list has at least 7 sample rows.

## Step 4: Web App Shell

Files:

```text
frontend-web/src/components/layout/AppShell.jsx
frontend-web/src/components/layout/Sidebar.jsx
frontend-web/src/components/layout/Topbar.jsx
frontend-web/src/routes/AppRouter.jsx
frontend-web/src/routes/ProtectedRoute.jsx
frontend-web/src/styles/base.css
frontend-web/src/styles/layout.css
frontend-web/src/styles/components.css
```

Pages:

```text
DashboardPage.jsx
BroadcastPage.jsx
WeatherPage.jsx
MappingPage.jsx
HouseholdStatusPage.jsx
RescueDispatchPage.jsx
RescuerAccountsPage.jsx
ResourcesRequestsPage.jsx
SituationReportingPage.jsx
ArchivePage.jsx
```

Checklist:

- Sidebar matches prototype.
- Topbar matches prototype.
- Role-based page access works.
- Page switch closes modals.

## Step 5: Shared Web Components

Create:

```text
Button.jsx
Badge.jsx
StatCard.jsx
DataTable.jsx
FilterBar.jsx
Modal.jsx
ActionMenu.jsx
DownloadDropdown.jsx
```

Use these everywhere instead of repeating code.

Checklist:

- All page buttons look consistent.
- All search/filter rows look consistent.
- All modals open centered.
- All row actions use one action menu.

## Step 6: Dashboard

Backend:

```text
GET /api/v1/dashboard/summary
GET /api/v1/dashboard/recent-activity
```

Frontend:

```text
frontend-web/src/pages/DashboardPage.jsx
frontend-web/src/api/dashboardApi.js
```

Do not load all households for dashboard.

Use:

- summary counts
- recent logs only
- compact request/weather/map preview data

Checklist:

- Active disaster appears.
- Household status cards appear.
- Dispatch chart/summary appears.
- Recent activity appears.

## Step 7: Household Status

Backend:

```text
GET  /api/v1/households?search=&purok=&status=&page=1
GET  /api/v1/households/{id}
GET  /api/v1/households/{id}/status-logs
POST /api/v1/households/{id}/status-logs
```

Frontend:

```text
HouseholdStatusPage.jsx
householdApi.js
```

Beginner performance:

- Paginate 20 rows.
- Use search and filters as API query params.
- Load household history only when modal opens.

Checklist:

- Search works.
- Purok filter works.
- Status filter works.
- Review modal opens.
- Latest status and history are separate.

## Step 8: Rescue Dispatch

Backend:

```text
GET   /api/v1/rescue-teams
GET   /api/v1/dispatches?event_id=1&status=
POST  /api/v1/dispatches
GET   /api/v1/dispatches/{id}
PATCH /api/v1/dispatches/{id}
POST  /api/v1/dispatches/{id}/complete
```

Frontend:

```text
RescueDispatchPage.jsx
dispatchApi.js
```

Checklist:

- Team cards show.
- New dispatch modal works.
- Update dispatch status works.
- Outcomes are saved.

## Step 9: Disaster Broadcasting

Backend:

```text
GET  /api/v1/disaster-events
POST /api/v1/disaster-events
POST /api/v1/disaster-events/{id}/broadcasts
GET  /api/v1/disaster-events/{id}/broadcasts
```

Frontend:

```text
BroadcastPage.jsx
broadcastApi.js
```

Version 1:

- Save broadcast logs.
- Show them in dashboard/archive.

Version 2:

- Add mobile push notifications.

Checklist:

- Event can be created.
- Broadcast message is saved.
- Affected puroks are saved.
- Household mobile status toggles are saved.

## Step 10: Weather Updates

Backend:

```text
GET  /api/v1/disaster-events/{id}/weather-logs
POST /api/v1/disaster-events/{id}/weather-logs/refresh
```

Frontend:

```text
WeatherPage.jsx
weatherApi.js
```

Important:

- Laravel calls weather APIs.
- React only displays saved weather logs.
- Store logs for archive and situation reports.

Checklist:

- Temperature appears.
- Rainfall appears.
- Wind speed/direction appears.
- Condition appears.
- Refresh saves new weather log.

## Step 11: Mapping

Backend:

```text
GET /api/v1/map/household-geotags?event_id=1&purok=all&status=all
GET /api/v1/map/evacuation-sites?event_id=1
GET /api/v1/map/dispatch-routes?event_id=1
```

Frontend:

```text
MappingPage.jsx
GeotagMap.jsx
mappingApi.js
```

Use:

- Leaflet
- React Leaflet
- OpenStreetMap tiles

Marker rules:

- green = safe / evacuated / checked
- red = unsafe / missing / injured
- grey = unchecked

Checklist:

- Household markers appear.
- Evacuation pins appear.
- Dispatch routes appear.
- Purok/status filters work.

## Step 12: Rescuer Accounts

Backend:

```text
GET   /api/v1/rescuers
POST  /api/v1/rescuers
GET   /api/v1/rescuers/{id}
PATCH /api/v1/rescuers/{id}
POST  /api/v1/rescuers/{id}/deactivate
```

Frontend:

```text
RescuerAccountsPage.jsx
rescuerApi.js
```

Checklist:

- Create verified account modal works.
- Edit works.
- View works.
- Deactivate works.
- No public registration exists.

## Step 13: Resources and Requests

Backend:

```text
GET  /api/v1/resource-requests?event_id=1&status=
POST /api/v1/resource-requests
GET  /api/v1/resource-requests/{id}
POST /api/v1/resource-requests/{id}/validate
POST /api/v1/resource-requests/{id}/forward
POST /api/v1/resource-requests/{id}/return
```

Frontend:

```text
ResourcesRequestsPage.jsx
resourceRequestApi.js
```

Checklist:

- Request list shows.
- Validation modal works.
- Forward works.
- Return works.
- TrackingAid-style resource mirror displays.

## Step 14: Situation Reporting

Backend:

```text
GET  /api/v1/disaster-events/{id}/situation-summary
GET  /api/v1/situation-reports
POST /api/v1/situation-reports
GET  /api/v1/situation-reports/{id}
GET  /api/v1/situation-reports/{id}/pdf
```

Frontend:

```text
SituationReportingPage.jsx
situationReportApi.js
```

Required flow:

1. HQ selects disaster event from dropdown.
2. Summary loads.
3. HQ reviews household, dispatch, weather, request summaries.
4. HQ clicks Generate SitRep.
5. Saved SitRep can be downloaded.

Checklist:

- Event dropdown appears first.
- Summary changes based on selected event.
- Generate saves report.
- PDF download works.

## Step 15: Archive

Backend:

```text
GET /api/v1/archive/disaster-events
GET /api/v1/archive/household-status-logs
GET /api/v1/archive/dispatch-logs
GET /api/v1/archive/resource-requests
GET /api/v1/archive/situation-reports
GET /api/v1/archive/export?category=disaster-events&type=csv
GET /api/v1/archive/export?category=disaster-events&type=pdf
```

Frontend:

```text
ArchivePage.jsx
archiveApi.js
```

Tabs:

1. Disaster Events
2. Household Status Logs
3. Rescue Dispatch Logs
4. Resources and Requests
5. Situation Reports

Checklist:

- Tabs switch content.
- Each tab has only relevant columns.
- Download dropdown has CSV and PDF.
- Search/date filters work.

## Step 16: Mobile Household

Screens:

```text
HouseholdHomeScreen.jsx
SubmitStatusScreen.jsx
HouseholdHistoryScreen.jsx
HouseholdProfileScreen.jsx
```

Checklist:

- Household sees active disaster.
- Household submits status.
- Location is sent if permission is granted.
- History displays.

## Step 17: Mobile Rescuer

Screens:

```text
RescuerHomeScreen.jsx
AssignedDispatchScreen.jsx
UpdateHouseholdStatusScreen.jsx
RescuerRouteScreen.jsx
RequestResourceScreen.jsx
```

Checklist:

- Rescuer sees assignment.
- Rescuer updates household status.
- Rescuer updates dispatch status.
- Rescuer requests resources.

## Step 18: Exports

CSV:

- Start with Laravel streamed downloads.

PDF:

- Situation reports first.
- Archive summaries second.

Checklist:

- CSV downloads current filter/tab.
- PDF downloads readable report.
- Export endpoint checks role access.

## Step 19: Final QA

Check:

- login by role
- sidebar routing
- dashboard data
- all modals centered
- page switch closes modals
- filters are consistent
- buttons are clickable
- archive tabs work
- situation report event dropdown works
- household mobile submission works
- rescuer mobile update works
- CSV/PDF downloads work
- backend returns JSON only

## 20. What To Tell The Instructor

If asked why the real system has fewer lines than the prototype:

```text
The prototype repeated UI blocks manually. The actual system uses reusable React components and arrays to render repeated cards, filters, tabs, table rows, and actions. This keeps the code shorter while preserving the same requirements and UI/UX.
```

