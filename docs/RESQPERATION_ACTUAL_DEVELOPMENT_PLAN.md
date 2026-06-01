# RESQPERATION Actual Development Plan and Prompt Pack

Source prototype scanned: `resqperation-2 (1).html`

Prototype size: about 8,132 lines.

Goal: rebuild the prototype as a real system with a Laravel API backend, a separated web frontend, and a separated mobile frontend while keeping the same UI/UX flow, page behavior, role-based access, and visual design.

This file has been updated after checking the Claude-generated `ResQperation_DevPlan.docx` and `ResQperation_StepGuide.docx`.

Important update:

- Do not use Laravel 11 as the target unless your instructor specifically requires it.
- Use the current Laravel release if the lab machines support the required PHP version.
- Use Laravel 12 as the fallback stable choice if the school computers cannot support the newest Laravel version.
- Do not build the first version with Laravel Reverb/WebSockets. Use refresh buttons, pagination, and simple polling first. Add real-time only after the required workflows already work.
- Do not copy the 8,000+ line prototype into React. Rebuild it with small pages, shared components, arrays, and `.map()` rendering.
- See `RESQPERATION_REQUIREMENTS_BREAKDOWN.md` for the detailed requirement-by-requirement implementation plan.
- See `RESQPERATION_RESOURCES.txt` for the separate resource list, links, APIs, package commands, and study references.

## 1. Recommended Tech Stack

Use this stack because it is professional enough for the project, but still beginner-friendly for an associate degree capstone.

### Backend

- Laravel 13 current stable if your school environment supports PHP 8.3+
- Laravel 12 if the lab computers or hosting cannot support Laravel 13 yet
- Avoid Laravel 11 for new planning unless your instructor requires it for compatibility
- PHP 8.3 or newer if available
- MySQL shared database
- Laravel Sanctum for authentication
- Laravel migrations, seeders, controllers, models, requests, resources
- No Blade UI pages for the backend
- Backend returns JSON only
- Laravel HTTP Client for weather API calls
- Laravel Scheduler for periodic weather snapshots after the manual refresh flow works

Backend rule:

```text
Laravel is only the API server. Do not put dashboard HTML, mobile HTML, or React pages inside Laravel Blade.
```

### Web Frontend

- React with Vite
- JavaScript first, not TypeScript, to keep the code beginner-level
- React Router for page navigation
- Axios for API requests
- Lucide React for icons
- Shared plain CSS design system based on the prototype
- Optional later: Recharts for charts after the first version works

Frontend rule:

```text
Copy the prototype's look, not the prototype's 8,000+ lines. Use reusable components and arrays to render repeated cards, rows, tabs, and buttons.
```

Web role access:

- Admin
- HQ dispatcher
- Rescuer web account

### Mobile Frontend

- Expo React Native
- JavaScript first, not TypeScript
- React Navigation
- Axios
- Expo SecureStore for login token storage
- Expo Location for household/rescuer geotagging
- Expo Notifications for broadcast alerts after the core mobile status flow works
- Mobile role access:
  - Household
  - Rescuer

### What Not to Use in Version 1

Avoid these in the first school-build version:

- Laravel Reverb/WebSockets as a required feature
- Redux, Zustand, or other global state libraries
- TypeScript
- Next.js
- Inertia
- Microservices
- Repository pattern everywhere
- Complex map clustering before normal markers work
- Direct frontend calls to weather APIs

Use simple beginner-level alternatives first:

- Refresh buttons and paginated API calls instead of WebSockets
- `useState`, `useEffect`, and small API files instead of global state libraries
- Laravel controllers + Eloquent + validation requests instead of layered architecture
- Backend weather fetch endpoint instead of calling weather providers from React

### Database

Use the shared MySQL database only in the backend `.env` file.

```env
DB_CONNECTION=mysql
DB_HOST=10.150.111.114
DB_PORT=3306
DB_DATABASE=klint
DB_USERNAME=groupmate
DB_PASSWORD=password123
```

Important:

- Do not commit `.env`.
- Put these values only in `backend-laravel/.env`.
- If more than one group is using the same database, coordinate before running migrations.
- Run migrations only after all table names are approved.

## 2. Final Folder Delegation

Create one main project folder:

```text
resqperation-system/
  backend-laravel/
  frontend-web/
  frontend-mobile/
  docs/
  prototype/
    resqperation-2 (1).html
```

### Backend Folder

```text
backend-laravel/
  app/
    Http/
      Controllers/
        Api/
          AuthController.php
          DashboardController.php
          DisasterEventController.php
          BroadcastController.php
          WeatherLogController.php
          HouseholdController.php
          HouseholdStatusLogController.php
          GeotagController.php
          RescueDispatchController.php
          RescuerController.php
          ResourceRequestController.php
          SituationReportController.php
          ArchiveController.php
      Requests/
        LoginRequest.php
        DisasterEventRequest.php
        HouseholdStatusRequest.php
        DispatchRequest.php
        ResourceRequestFormRequest.php
        SituationReportRequest.php
      Resources/
        UserResource.php
        DisasterEventResource.php
        HouseholdResource.php
        DispatchResource.php
        ResourceRequestResource.php
        SituationReportResource.php
    Models/
      User.php
      Role.php
      DisasterEvent.php
      BroadcastLog.php
      WeatherLog.php
      Household.php
      HouseholdMember.php
      HouseholdDevice.php
      HouseholdGeotag.php
      HouseholdStatusLog.php
      EvacuationSite.php
      RescueTeam.php
      RescueDispatch.php
      DispatchRoute.php
      RescuerProfile.php
      ResourceItem.php
      ResourceRequest.php
      RequestValidation.php
      SituationReport.php
      AuditLog.php
  database/
    migrations/
    seeders/
  routes/
    api.php
  tests/
```

### Web Frontend Folder

```text
frontend-web/
  src/
    api/
      apiClient.js
      authApi.js
      dashboardApi.js
      disasterEventApi.js
      broadcastApi.js
      weatherApi.js
      householdApi.js
      mappingApi.js
      dispatchApi.js
      rescuerApi.js
      resourceRequestApi.js
      situationReportApi.js
      archiveApi.js
    assets/
    components/
      layout/
        AppShell.jsx
        Topbar.jsx
        Sidebar.jsx
      common/
        Button.jsx
        Badge.jsx
        Modal.jsx
        DataTable.jsx
        FilterBar.jsx
        ActionMenu.jsx
        StatCard.jsx
      map/
        GeotagMap.jsx
      forms/
    pages/
      LoginPage.jsx
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
    routes/
      AppRouter.jsx
      ProtectedRoute.jsx
    styles/
      variables.css
      base.css
      layout.css
      components.css
      pages.css
    utils/
      formatDate.js
      statusColors.js
      roleAccess.js
    App.jsx
    main.jsx
```

### Mobile Frontend Folder

```text
frontend-mobile/
  src/
    api/
      apiClient.js
      authApi.js
      householdMobileApi.js
      rescuerMobileApi.js
    components/
      AppButton.jsx
      StatusCard.jsx
      ScreenHeader.jsx
      FieldCard.jsx
    navigation/
      AppNavigator.jsx
      AuthNavigator.jsx
      HouseholdNavigator.jsx
      RescuerNavigator.jsx
    screens/
      auth/
        LoginScreen.jsx
      household/
        HouseholdHomeScreen.jsx
        SubmitStatusScreen.jsx
        HouseholdHistoryScreen.jsx
        HouseholdProfileScreen.jsx
      rescuer/
        RescuerHomeScreen.jsx
        AssignedDispatchScreen.jsx
        UpdateHouseholdStatusScreen.jsx
        RescuerRouteScreen.jsx
        RequestResourceScreen.jsx
    storage/
      tokenStorage.js
    styles/
      colors.js
      spacing.js
      typography.js
    App.js
```

## 3. Prototype Modules That Must Be Preserved

The prototype contains these real system modules:

1. Login and role-based entry
2. Dashboard command overview
3. Disaster Broadcasting
4. Weather Updates
5. Geotagging Map
6. Household Status
7. Rescue Dispatch
8. Rescuer Accounts
9. Resources and Requests
10. Situation Reporting
11. Archive

Legacy sections found in the prototype:

- `households-legacy`
- `dispatch-legacy`

Do not rebuild these as separate production pages. Use them only as reference for data fields and table examples.

## 4. Role-Based Requirements

### Web: Admin

Can access:

- Dashboard
- Disaster Broadcasting
- Weather Updates
- Geotagging Map
- Household Status
- Rescue Dispatch
- Rescuer Accounts
- Resources and Requests
- Situation Reporting
- Archive
- User/account management

### Web: HQ Dispatcher

Can access:

- Dashboard
- Disaster Broadcasting
- Weather Updates
- Geotagging Map
- Household Status
- Rescue Dispatch
- Resources and Requests
- Situation Reporting
- Archive

Can create:

- Broadcasts
- Dispatch records
- Resource request validations
- Situation reports

### Web: Rescuer

Can access:

- Assigned dispatches
- Household status records relevant to assignment
- Resource requests submitted by field teams
- Limited map view

### Mobile: Household

Can access:

- Active disaster alert
- Household status submission
- Household members
- Device/location permission status
- Status history

Can submit:

- Safe
- Evacuated
- Unsafe
- Missing contact
- Injured or medical need if enabled by the event

### Mobile: Rescuer

Can access:

- Assigned dispatch route
- Assigned households or purok area
- Household status update form
- Resource/personnel request form
- Dispatch status update

Can submit:

- Household status update
- Dispatch progress update
- Rescue outcome
- Resource request

## 5. Database Tables

Use snake_case plural table names. Use singular `_id` foreign keys.

Minimum tables:

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

### Important Fields

#### users

- id
- role_id
- name
- username
- email
- password
- mobile_number
- status: active, inactive

#### disaster_events

- id
- event_code
- disaster_type
- name
- alert_level
- status: draft, active, closed, archived
- declared_at
- finished_at
- declared_by
- notes

#### broadcast_logs

- id
- disaster_event_id
- broadcast_type
- message
- recipient_scope
- affected_puroks
- priority
- sent_at
- sent_by

#### weather_logs

- id
- disaster_event_id
- condition_name
- temperature
- rainfall_mm
- wind_speed
- wind_direction
- advisory_source
- observed_at

#### households

- id
- household_code
- account_user_id
- household_name
- purok
- address
- member_count
- status_latest
- risk_level

#### household_status_logs

- id
- disaster_event_id
- household_id
- status
- source_type: household_mobile, rescuer_mobile, hq_manual
- submitted_by
- submitted_at
- latitude
- longitude
- note

#### rescue_dispatches

- id
- disaster_event_id
- rescue_team_id
- assigned_area
- assigned_households_count
- status: standby, dispatched, on_scene, completed, cancelled
- dispatched_at
- arrived_at
- completed_at
- safe_count
- evacuated_count
- unsafe_count
- injured_count
- missing_count
- notes

#### resource_requests

- id
- disaster_event_id
- request_code
- source_type
- request_type: resource, personnel, vehicle
- category
- quantity
- unit
- requester_name
- requester_contact
- area
- urgency
- status: needs_validation, verified, forwarded, returned, fulfilled, cancelled
- created_by
- created_at

#### situation_reports

- id
- disaster_event_id
- report_code
- generated_by
- generated_at
- summary
- household_summary_json
- dispatch_summary_json
- resources_summary_json
- weather_summary_json
- casualties_count
- injured_count
- missing_count
- property_damage
- pdf_path

#### audit_logs

- id
- user_id
- module
- action
- record_type
- record_id
- old_values
- new_values
- ip_address
- created_at

## 6. API Route Plan

Use `/api/v1`.

### Auth

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
```

### Dashboard

```text
GET    /api/v1/dashboard/summary
GET    /api/v1/dashboard/recent-activity
```

### Disaster Events and Broadcasts

```text
GET    /api/v1/disaster-events
POST   /api/v1/disaster-events
GET    /api/v1/disaster-events/{id}
PATCH  /api/v1/disaster-events/{id}
POST   /api/v1/disaster-events/{id}/close

GET    /api/v1/disaster-events/{id}/broadcasts
POST   /api/v1/disaster-events/{id}/broadcasts
```

### Weather

```text
GET    /api/v1/disaster-events/{id}/weather-logs
POST   /api/v1/disaster-events/{id}/weather-logs
```

### Mapping

```text
GET    /api/v1/map/household-geotags
GET    /api/v1/map/evacuation-sites
GET    /api/v1/map/dispatch-routes
```

### Households

```text
GET    /api/v1/households
GET    /api/v1/households/{id}
GET    /api/v1/households/{id}/status-logs
POST   /api/v1/households/{id}/status-logs
```

### Rescue Dispatch

```text
GET    /api/v1/rescue-teams
GET    /api/v1/dispatches
POST   /api/v1/dispatches
GET    /api/v1/dispatches/{id}
PATCH  /api/v1/dispatches/{id}
POST   /api/v1/dispatches/{id}/complete
```

### Rescuer Accounts

```text
GET    /api/v1/rescuers
POST   /api/v1/rescuers
GET    /api/v1/rescuers/{id}
PATCH  /api/v1/rescuers/{id}
POST   /api/v1/rescuers/{id}/deactivate
```

### Resources and Requests

```text
GET    /api/v1/resource-requests
POST   /api/v1/resource-requests
GET    /api/v1/resource-requests/{id}
PATCH  /api/v1/resource-requests/{id}
POST   /api/v1/resource-requests/{id}/validate
POST   /api/v1/resource-requests/{id}/forward
POST   /api/v1/resource-requests/{id}/return
```

### Situation Reporting

```text
GET    /api/v1/situation-reports
POST   /api/v1/situation-reports
GET    /api/v1/situation-reports/{id}
GET    /api/v1/disaster-events/{id}/situation-summary
GET    /api/v1/situation-reports/{id}/pdf
```

### Archive

```text
GET    /api/v1/archive/disaster-events
GET    /api/v1/archive/household-status-logs
GET    /api/v1/archive/dispatch-logs
GET    /api/v1/archive/resource-requests
GET    /api/v1/archive/situation-reports
GET    /api/v1/archive/export?category=disaster-events&type=csv
GET    /api/v1/archive/export?category=disaster-events&type=pdf
```

## 7. Frontend Page Mapping

Each React page should match the prototype page.

```text
DashboardPage.jsx              -> prototype data-page="dashboard"
BroadcastPage.jsx              -> prototype data-page="broadcast"
WeatherPage.jsx                -> prototype data-page="weather"
MappingPage.jsx                -> prototype data-page="mapping"
HouseholdStatusPage.jsx        -> prototype data-page="households"
RescueDispatchPage.jsx         -> prototype data-page="dispatch"
RescuerAccountsPage.jsx        -> prototype data-page="rescuers"
ResourcesRequestsPage.jsx      -> prototype data-page="resources-requests"
SituationReportingPage.jsx     -> prototype data-page="situation"
ArchivePage.jsx                -> prototype data-page="archive"
```

Keep the same:

- Page titles
- Page subtitles
- Sidebar navigation
- Topbar style
- Buttons
- Modals
- Tables
- Badges
- Search/filter layout
- Row action menu behavior
- Archive category tabs
- Situation report event dropdown

## 8. Archive Page Production Design

The archive page should be tab-based. Each tab replaces the full archive content area.

Tabs:

1. Disaster Events
2. Household Status Logs
3. Rescue Dispatch Logs
4. Resources and Requests
5. Situation Reports

Download control:

```text
[Download dropdown]
  - Current tab as CSV
  - Current tab as PDF
```

Archive data should be based on history, dates, event code, user action, status, and record reference.

Minimal display rule:

- Show only important records in tables.
- Put long explanations in a document/download, not on the page.
- Each tab should have one short context line and one compact table.

## 9. Situation Report Production Flow

Situation Reporting must start with a disaster event dropdown.

Flow:

1. HQ selects a disaster event.
2. System loads the disaster summary.
3. System displays:
   - Disaster type
   - Date declared
   - Date finished
   - Weather summary
   - Household status summary
   - Dispatch summary
   - Resource/request summary
4. HQ clicks Generate SitRep.
5. System saves the situation report.
6. HQ can download PDF.

## 10. Beginner-Friendly Coding Rules

Follow these rules so the project stays understandable:

- Use JavaScript, not TypeScript, for first version.
- Use simple React function components.
- Use `useState` and `useEffect`.
- Do not use Redux.
- Do not use advanced state machines.
- Do not use microservices.
- Do not use event sourcing.
- Do not build real-time WebSockets in version 1.
- Use simple polling/refresh buttons first.
- Use Laravel controllers directly.
- Do not create repository/service layers unless the controller becomes too long.
- Use Eloquent relationships.
- Use Form Request classes for validation.
- Use API Resource classes for consistent JSON output.
- Use clear file names.
- Use short functions.
- Keep one feature per file.
- Keep CSS class names close to the prototype.

### How to Keep the Code Short Without Losing Requirements

Your instructor's comment about "too many lines" does not mean removing requirements. It means the same UI should be built with reusable patterns instead of repeating HTML over and over.

Use this rule:

```text
If a card, table row, status pill, modal, or button appears more than twice, make it reusable or render it from an array.
```

Examples:

```jsx
const statusCards = [
  { label: "Unchecked", value: 215, tone: "gray" },
  { label: "Safe total", value: 230, tone: "green" },
  { label: "Evacuated", value: 55, tone: "blue" },
  { label: "Unsafe", value: 55, tone: "red" },
];

return statusCards.map((item) => <StatCard key={item.label} {...item} />);
```

Do this instead of writing four separate copied card blocks.

For tables:

```jsx
const columns = ["Household", "Purok", "Status", "Last update"];
const rows = households.map((household) => [
  household.name,
  household.purok,
  <Badge tone={household.status_tone}>{household.status}</Badge>,
  household.last_update,
]);
```

For archive tabs:

```jsx
const archiveTabs = [
  { key: "disaster-events", label: "Disaster Events" },
  { key: "household-status", label: "Household Status Logs" },
  { key: "dispatch", label: "Dispatch Logs" },
  { key: "requests", label: "Resources & Requests" },
  { key: "sitreps", label: "Situation Reports" },
];
```

For backend:

```php
return HouseholdResource::collection(
    Household::query()
        ->when($request->search, fn ($q, $search) => $q->where('household_name', 'like', "%{$search}%"))
        ->paginate(20)
);
```

That one query replaces manual loops and long conditional blocks.

### Beginner Code Limits

Keep each file small:

- React page file: target 150 to 250 lines
- React shared component: target 30 to 90 lines
- Laravel controller: target 80 to 180 lines
- Laravel model: target 30 to 80 lines
- CSS file per section: target reusable classes, not duplicated page styles

If a file grows too long:

- Move repeated UI to `components/common`.
- Move API logic to `src/api`.
- Move dropdown choices/status labels to `src/utils`.
- Move table columns into a small array.
- Move validation to Laravel Form Request classes.

### Heavy Feature Strategy in Beginner Code

The system has heavy features, but each can still be simple:

- Many data rows: use backend pagination, search query params, and `paginate(20)`.
- Dashboard counters: create summary endpoints instead of loading every row.
- Mapping: load only geotag marker fields: id, name, status, latitude, longitude.
- Archive: use existing logs filtered by date/event; do not duplicate archive tables unless required.
- Weather: backend fetches and saves weather snapshots; frontend only displays saved logs.
- CSV export: use Laravel streamed response first; add packages only if needed.
- PDF export: use a simple PDF package only for situation reports and archive summaries.
- Push notifications: make database broadcast logs first, add actual mobile push after the mobile app works.

## 11. Setup Steps

### Step 1: Create Main Folder

```bash
mkdir resqperation-system
cd resqperation-system
mkdir docs prototype
```

Copy the prototype:

```text
prototype/resqperation-2 (1).html
```

### Step 2: Create Laravel Backend

```bash
composer create-project laravel/laravel backend-laravel
cd backend-laravel
php artisan install:api
```

Update `.env` with the shared MySQL database.

Then:

```bash
php artisan migrate
php artisan serve
```

### Step 3: Create React Web Frontend

```bash
cd ..
npm create vite@latest frontend-web -- --template react
cd frontend-web
npm install
npm install axios react-router-dom lucide-react
npm run dev
```

Create:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

### Step 4: Create Expo Mobile Frontend

```bash
cd ..
npx create-expo-app@latest frontend-mobile
cd frontend-mobile
npm install axios
npm install @react-navigation/native @react-navigation/native-stack
npx expo install expo-secure-store react-native-screens react-native-safe-area-context
npx expo start
```

## 12. Development Phases

### Phase 1: Backend Skeleton

Deliverables:

- Laravel API project
- Database connection
- Auth login/logout/me
- User roles
- Seed users
- Protected routes

### Phase 2: Core Tables and Seed Data

Deliverables:

- Migrations
- Seeders based on prototype data
- Disaster event seed
- Household seed
- Rescue team seed
- Resource request seed

### Phase 3: Web Layout

Deliverables:

- Login page
- Sidebar
- Topbar
- Dashboard page
- Shared buttons, badges, cards, tables, modals
- Prototype CSS migrated into organized CSS files

### Phase 4: Web Modules

Build in this order:

1. Dashboard
2. Broadcast
3. Weather
4. Household Status
5. Dispatch
6. Mapping
7. Rescuer Accounts
8. Resources and Requests
9. Situation Reporting
10. Archive

### Phase 5: Mobile App

Build in this order:

1. Login
2. Household home
3. Household status submission
4. Rescuer assignments
5. Rescuer household update
6. Rescuer resource request

### Phase 6: Exports and Polish

Deliverables:

- CSV exports
- PDF exports
- Filters
- Search
- Empty states
- Loading states
- Error states
- Audit logs

## 13. Effective Prompt for Backend Development

Copy and use this prompt when starting the backend.

```text
You are a senior but beginner-friendly Laravel API developer.

Build the backend for RESQPERATION using Laravel only as a JSON API. Do not create Blade pages or backend HTML. The source UI prototype is prototype/resqperation-2 (1).html. Use it only to understand modules, fields, workflows, statuses, buttons, modals, and page behavior.

Project rules:
- Backend folder: backend-laravel
- Use Laravel API routes in routes/api.php
- Use /api/v1 route prefix
- Use MySQL with the shared database values from .env
- Use Laravel Sanctum for authentication
- Use SPA cookie authentication for the React web app if possible
- Use Sanctum API tokens for mobile app login
- Use simple beginner-friendly code
- Use controllers, models, migrations, seeders, form requests, and API resources
- Do not add repository pattern
- Do not add microservices
- Do not add complex architecture
- Keep controller methods readable

Create these roles:
- admin
- hq_dispatcher
- rescuer
- household

Create migrations and models for:
users, roles, role_user, disaster_events, broadcast_logs, weather_logs, households, household_members, household_devices, household_geotags, household_status_logs, evacuation_sites, rescue_teams, rescuer_profiles, rescue_dispatches, dispatch_routes, resource_items, resource_requests, request_validations, situation_reports, audit_logs.

Create API endpoints:
auth, dashboard, disaster events, broadcasts, weather logs, mapping geotags, households, household status logs, rescue dispatches, rescuers, resources and requests, situation reports, archive, CSV/PDF exports.

Create seeders that match the prototype sample data:
- Typhoon Carina active event
- 500 household scope summary
- sample households: Dela Cruz, Reyes, Santos, Garcia, Bautista, Torres, Flores
- rescue teams: SAR, Evacuation, Medical, Relief, Communication, Fire Brigade, DANA, Security
- sample resource requests from the prototype
- sample weather logs and situation report data

Response format:
Return JSON like:
{
  "success": true,
  "message": "Readable message",
  "data": {},
  "errors": null
}

First deliver:
1. Laravel setup commands
2. .env example
3. migration list
4. models and relationships
5. api.php routes
6. AuthController
7. seeders
8. sample API responses
```

## 14. Effective Prompt for Web Frontend Development

Copy and use this prompt when starting the web frontend.

```text
You are a senior but beginner-friendly React frontend developer.

Build the RESQPERATION web dashboard using React + Vite. The source UI prototype is prototype/resqperation-2 (1).html. Recreate the same UI/UX, layout, colors, typography, sidebar, topbar, cards, tables, buttons, modals, filters, row action menus, archive tabs, and situation report flow.

Project rules:
- Frontend folder: frontend-web
- Use React with JavaScript
- Use React Router
- Use Axios
- Use lucide-react for icons
- Use plain CSS files, not Tailwind
- Keep code beginner-friendly
- Do not use Redux
- Do not use complex state management
- Do not use Next.js
- Do not put backend code here
- All data must come from Laravel API endpoints
- Use VITE_API_BASE_URL for API base URL

Pages to build:
- LoginPage
- DashboardPage
- BroadcastPage
- WeatherPage
- MappingPage
- HouseholdStatusPage
- RescueDispatchPage
- RescuerAccountsPage
- ResourcesRequestsPage
- SituationReportingPage
- ArchivePage

Shared components:
- AppShell
- Sidebar
- Topbar
- Button
- Badge
- StatCard
- DataTable
- FilterBar
- Modal
- ActionMenu
- DownloadDropdown

Important UI behavior:
- Page-owned modals must close when changing pages.
- Modals must open in the middle of the screen.
- Tables must use consistent row spacing and actions.
- Search and filters must stretch properly from left to right.
- Top-right page buttons must have consistent design.
- Archive page must use tabs:
  1. Disaster Events
  2. Household Status Logs
  3. Rescue Dispatch Logs
  4. Resources and Requests
  5. Situation Reports
- Archive download must be a dropdown with CSV and PDF options.
- Situation Reporting must start with a disaster event dropdown before showing the summary.

First deliver:
1. folder structure
2. route setup
3. API client
4. layout components
5. styles copied and cleaned from prototype
6. one completed page first: DashboardPage
7. then continue page by page
```

## 15. Effective Prompt for Mobile Frontend Development

Copy and use this prompt when starting the mobile app.

```text
You are a senior but beginner-friendly Expo React Native developer.

Build the RESQPERATION mobile app for two roles: household and rescuer. The Laravel backend already provides /api/v1 endpoints. The source web prototype is prototype/resqperation-2 (1).html. Use it to understand the status names, event flow, household reporting flow, rescuer dispatch flow, colors, and labels.

Project rules:
- Mobile folder: frontend-mobile
- Use Expo React Native
- Use JavaScript
- Use React Navigation
- Use Axios
- Use Expo SecureStore for auth token
- Keep code beginner-friendly
- Do not use Redux
- Do not build unnecessary screens

Household mobile screens:
- LoginScreen
- HouseholdHomeScreen
- SubmitStatusScreen
- HouseholdHistoryScreen
- HouseholdProfileScreen

Rescuer mobile screens:
- LoginScreen
- RescuerHomeScreen
- AssignedDispatchScreen
- UpdateHouseholdStatusScreen
- RescuerRouteScreen
- RequestResourceScreen

Mobile behavior:
- Household can view active event.
- Household can submit safe, evacuated, unsafe, missing contact, injured if enabled.
- Rescuer can view assigned dispatch.
- Rescuer can update household status.
- Rescuer can submit resource/personnel request.
- Mobile app sends latitude/longitude when permission is granted.

First deliver:
1. Expo setup commands
2. navigation setup
3. auth storage
4. API client
5. login screen
6. role redirect logic
7. household status submission screen
8. rescuer assigned dispatch screen
```

## 16. Effective Prompt for Integration and QA

Copy and use this prompt after backend and frontend modules exist.

```text
You are a full-stack QA developer for RESQPERATION.

Check the Laravel API, React web dashboard, and Expo mobile app against the original prototype and requirements.

Verify:
- every sidebar route opens the correct page
- role-based access works
- web modals close on page switch
- all buttons are clickable or disabled intentionally
- all search/filter bars are visually consistent
- archive tabs switch correctly
- archive CSV/PDF dropdown works
- situation report event dropdown loads the correct summary
- household mobile can submit status
- rescuer mobile can update dispatch and household status
- API validation errors are readable
- database records are created correctly
- no backend Blade HTML is used for app pages

Create a simple QA checklist with pass/fail and file/route references.
```

## 17. Definition of Done

The system is done when:

- Laravel backend runs with `php artisan serve`
- React web runs with `npm run dev`
- Expo mobile runs with `npx expo start`
- Login works by role
- Web dashboard matches the prototype UI
- Mobile household role can submit status
- Mobile rescuer role can update assignments
- HQ can create disaster broadcasts
- HQ can monitor weather, households, geotags, dispatch, requests, and archives
- HQ can generate a situation report based on selected disaster event
- Archive page has tab categories and CSV/PDF dropdown
- API endpoints are documented
- Seed data is included
- `.env` is not committed

## 18. Official References Used

- Laravel installation and MySQL env configuration: https://laravel.com/docs/12.x/installation
- Laravel current installation guidance: https://laravel.com/docs/13.x/installation
- Laravel Sanctum for SPA, mobile, and API auth, currently redirected to Laravel 13 docs: https://laravel.com/docs/sanctum
- Laravel HTTP Client for backend weather API calls: https://laravel.com/docs/http-client
- Laravel Scheduler for later automated weather snapshots: https://laravel.com/docs/scheduling
- Vite React project scaffolding: https://vite.dev/guide/
- React app guidance: https://react.dev/learn/start-a-new-react-project
- Expo project creation: https://docs.expo.dev/get-started/create-a-project/
- Open-Meteo structured weather API: https://open-meteo.com/en/docs
- PAGASA official products and services: https://pagasa.dost.gov.ph/products-and-services
- Leaflet map quick start: https://leafletjs.com/examples/quick-start/
