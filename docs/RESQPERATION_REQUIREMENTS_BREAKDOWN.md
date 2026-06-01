# RESQPERATION Requirements Breakdown

Purpose: this document explains what must be built from the prototype and how to build it with simple beginner-friendly code.

Prototype scanned: `resqperation-2 (1).html`

Claude docs checked:

- `ResQperation_DevPlan.docx`
- `ResQperation_StepGuide.docx`

## 1. Main Development Rule

The prototype is a visual and workflow reference, not the codebase to copy.

Do not copy the full HTML into React. Rebuild it using:

- one page component per module
- shared components for repeated UI
- arrays for cards, tabs, filters, rows, and options
- API files for backend calls
- Laravel JSON endpoints for all data

This is how the system can meet all requirements without becoming a 9,000-line frontend file.

## 2. Updated Stack Decision

Use this stack:

```text
backend-laravel  = Laravel current stable API
frontend-web     = React + Vite + plain shared CSS
frontend-mobile  = Expo React Native
database         = shared MySQL
auth             = Laravel Sanctum
maps             = Leaflet / React Leaflet with OpenStreetMap tiles
weather          = Laravel backend fetches trusted weather source data
exports          = CSV streamed from Laravel, PDF generated from Laravel
```

Use Laravel 13 if the computers support PHP 8.3+. Use Laravel 12 if Laravel 13 does not run in the lab or hosting environment. Avoid Laravel 11 for new planning unless the instructor requires it.

## 3. Why the Code Can Be Shorter

Your instructor probably meant the prototype repeats many blocks manually:

- repeated stat cards
- repeated table rows
- repeated badges
- repeated filters
- repeated modals
- repeated buttons
- repeated map marker elements

In real development, repeated UI should be rendered from data.

Example:

```jsx
const tabs = [
  { key: "events", label: "Disaster Events" },
  { key: "households", label: "Household Logs" },
  { key: "dispatch", label: "Dispatch Logs" },
];

return tabs.map((tab) => (
  <button key={tab.key} onClick={() => setActiveTab(tab.key)}>
    {tab.label}
  </button>
));
```

This is beginner code. It is not advanced. It is just avoiding copy-paste.

## 4. Required Roles

### Admin Web

Required access:

- all dashboard modules
- all user and rescuer account management
- archive exports
- situation report generation

### HQ / Dispatcher Web

Required access:

- dashboard
- disaster broadcast
- weather monitoring
- mapping
- household status
- rescue dispatch
- resources and requests
- situation reporting
- archive

### Rescuer Web

Required access:

- limited dashboard
- own dispatch assignments
- assigned household records
- request resource support

### Household Mobile

Required access:

- active broadcast alert
- submit household status
- view own household status history
- update/check location permission

### Rescuer Mobile

Required access:

- active dispatch assignment
- update household status in the field
- update dispatch progress
- request resources or personnel
- submit geotagged reports

## 5. Module Breakdown

## 5.1 Auth and Role-Based Routing

### Requirements

- Users log in with assigned accounts.
- Public registration is disabled.
- Web redirects based on role.
- Mobile redirects household and rescuer users to their own screens.
- Backend protects endpoints by role.

### Backend

Tables:

- users
- roles
- role_user
- personal_access_tokens from Sanctum

Endpoints:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

Beginner implementation:

- `AuthController`
- `LoginRequest`
- `UserResource`
- simple `role` middleware

### Web

Files:

```text
src/pages/LoginPage.jsx
src/routes/ProtectedRoute.jsx
src/utils/roleAccess.js
```

### Mobile

Files:

```text
src/screens/auth/LoginScreen.jsx
src/storage/tokenStorage.js
src/navigation/AppNavigator.jsx
```

## 5.2 Dashboard

### Requirements

Shows command overview:

- active disaster
- household counts
- reporting progress
- dispatch status chart
- recent activity log
- weather summary
- map preview
- resource request summary

### Backend

Endpoint:

```text
GET /api/v1/dashboard/summary
GET /api/v1/dashboard/recent-activity
```

Important:

- Do not load all households just to show counters.
- Use aggregate counts in SQL/Eloquent.
- Return compact JSON.

Example response shape:

```json
{
  "active_event": {},
  "household_summary": {},
  "dispatch_summary": {},
  "request_summary": {},
  "recent_logs": []
}
```

### Frontend

Use reusable components:

- `StatCard`
- `ProgressBar`
- `DataTable`
- `MiniMapPreview`
- `ActivityList`

## 5.3 Disaster Broadcasting

### Requirements

- HQ creates a disaster event/broadcast.
- Select disaster type.
- Select affected areas/puroks.
- Select mobile status buttons that households can use.
- Send message to households and rescuers.
- Store broadcast logs for archive and situation reports.

### Backend

Tables:

- disaster_events
- broadcast_logs

Endpoints:

```text
GET  /api/v1/disaster-events
POST /api/v1/disaster-events
POST /api/v1/disaster-events/{id}/broadcasts
GET  /api/v1/disaster-events/{id}/broadcasts
```

Beginner code:

- Start by saving broadcast logs in DB.
- Add push notifications later.

### Frontend

Use:

- `BroadcastPage.jsx`
- controlled form fields
- array of status toggle options
- submit to API

## 5.4 Weather Updates

### Requirements

- Live condition updates
- Temperature monitoring
- Rainfall monitoring
- Wind speed and direction monitoring
- Weather condition display: sunny, rainy, stormy, cloudy, storm
- Trusted online weather sources
- Weather logs must be stored for archive and situation reporting

### Recommended Approach

Do not call weather APIs directly from React.

Correct flow:

```text
Laravel backend -> fetches weather API/advisory -> stores weather_logs -> React displays weather_logs
```

This keeps API keys/server logic out of the frontend.

### Sources

Use:

- PAGASA website/bulletins for official advisory context
- Open-Meteo API for structured weather JSON

### Backend

Tables:

- weather_logs

Endpoints:

```text
GET  /api/v1/disaster-events/{id}/weather-logs
POST /api/v1/disaster-events/{id}/weather-logs/refresh
```

Beginner code:

- First make a manual "Refresh Weather" endpoint.
- Later add Laravel Scheduler every 30 minutes.

### Frontend

Use:

- `WeatherPage.jsx`
- `WeatherMetricCard`
- `WeatherConditionPill`
- `AdvisoryList`

## 5.5 Geotagging Map

### Requirements

- Plot household geotag locations.
- Green means safe/evacuated/checked.
- Red means unsafe/missing/injured.
- Grey means unchecked.
- Show evacuation site pins.
- Show vacancy.
- Show route to vacant sites.
- Show rescue dispatched team routes.

### Recommended Approach

Use Leaflet/React Leaflet.

Start simple:

- household markers
- evacuation site markers
- polyline routes
- filter by status/purok

Add clustering later only if performance becomes a problem.

### Backend

Tables:

- household_geotags
- evacuation_sites
- dispatch_routes

Endpoints:

```text
GET /api/v1/map/household-geotags?event_id=1&purok=all&status=all
GET /api/v1/map/evacuation-sites?event_id=1
GET /api/v1/map/dispatch-routes?event_id=1
```

Return only fields needed by the map:

```json
{
  "id": 1,
  "label": "Dela Cruz Family",
  "status": "safe",
  "latitude": 14.123,
  "longitude": 121.123
}
```

Do not send full household profiles to the map.

## 5.6 Household Status

### Requirements

- HQ sees latest household status.
- Household statuses include unchecked, safe, evacuated, unsafe, missing, injured.
- HQ can review household details in a modal.
- Household history logs are available.
- Search/filter by household, purok, status, device risk.
- Mobile household users submit their own status.
- Rescuers can submit field status updates.

### Backend

Tables:

- households
- household_members
- household_devices
- household_status_logs

Endpoints:

```text
GET  /api/v1/households?search=&purok=&status=&page=1
GET  /api/v1/households/{id}
GET  /api/v1/households/{id}/status-logs
POST /api/v1/households/{id}/status-logs
```

Beginner performance rule:

- Use pagination.
- Use latest status column on households for fast table display.
- Store full history in household_status_logs.

## 5.7 Rescue Dispatch

### Requirements

- View rescue teams.
- Dispatch team to assigned area.
- Show team status: standby, dispatched, on-scene, completed.
- Show households assigned.
- Show outcomes: safe, evacuated, unsafe, injured, missing.
- Rescuer mobile updates progress.

### Backend

Tables:

- rescue_teams
- rescuer_profiles
- rescue_dispatches
- dispatch_routes

Endpoints:

```text
GET   /api/v1/rescue-teams
GET   /api/v1/dispatches?event_id=1&status=
POST  /api/v1/dispatches
GET   /api/v1/dispatches/{id}
PATCH /api/v1/dispatches/{id}
POST  /api/v1/dispatches/{id}/complete
```

Beginner code:

- One dispatch form.
- One update form.
- Use select dropdowns for statuses.
- No complex route optimizer in version 1.

## 5.8 Rescuer Accounts

### Requirements

- Admin/HQ creates verified rescuer accounts.
- No public rescuer registration.
- Store team, role, contact, ICE, credentials, equipment, duty status.
- Row action menu: view, edit, deactivate.

### Backend

Tables:

- users
- rescuer_profiles
- rescue_teams

Endpoints:

```text
GET   /api/v1/rescuers
POST  /api/v1/rescuers
GET   /api/v1/rescuers/{id}
PATCH /api/v1/rescuers/{id}
POST  /api/v1/rescuers/{id}/deactivate
```

## 5.9 Resources and Requests

### Requirements

- Accept resource/personnel/vehicle requests.
- Validate request before handoff.
- Return for missing info or duplicate.
- Forward verified requests.
- Display TrackingAid-style availability mirror.
- Store request validation record in a modal.

### Backend

Tables:

- resource_items
- resource_requests
- request_validations

Endpoints:

```text
GET  /api/v1/resource-requests?event_id=1&status=
POST /api/v1/resource-requests
GET  /api/v1/resource-requests/{id}
POST /api/v1/resource-requests/{id}/validate
POST /api/v1/resource-requests/{id}/forward
POST /api/v1/resource-requests/{id}/return
```

Beginner code:

- Keep request validation as a simple form.
- Use status values instead of many boolean columns.

## 5.10 Situation Reporting

### Requirements

- HQ selects a disaster event from a dropdown.
- System loads the event summary before generating SitRep.
- Summary includes:
  - disaster type
  - event name
  - date declared
  - date finished
  - household status summary
  - dispatch summary
  - resource/request summary
  - weather summary
  - casualties/injured/missing
  - property damage if available
- HQ generates and saves SitRep.
- SitRep can be exported as PDF.

### Backend

Tables:

- situation_reports

Endpoints:

```text
GET  /api/v1/disaster-events/{id}/situation-summary
GET  /api/v1/situation-reports
POST /api/v1/situation-reports
GET  /api/v1/situation-reports/{id}
GET  /api/v1/situation-reports/{id}/pdf
```

Beginner code:

- Use one summary endpoint that collects counts.
- Store summary JSON snapshots in the situation_reports table.
- Generate PDF from the saved report, not from live changing data.

## 5.11 Archive

### Requirements

Archive page must have tabs:

1. Disaster Events
2. Household Status Logs
3. Rescue Dispatch Logs
4. Resources and Requests
5. Situation Reports

Archive must include:

- dates
- event codes
- reference numbers
- status
- submitted/generated by
- key summary values
- download dropdown for CSV/PDF

### Backend

Endpoints:

```text
GET /api/v1/archive/disaster-events
GET /api/v1/archive/household-status-logs
GET /api/v1/archive/dispatch-logs
GET /api/v1/archive/resource-requests
GET /api/v1/archive/situation-reports
GET /api/v1/archive/export?category=disaster-events&type=csv
GET /api/v1/archive/export?category=disaster-events&type=pdf
```

Beginner code:

- Archive is a view over existing historical records.
- Do not create duplicate archive copies unless required.
- Use tabs and one table per tab.
- Use one download dropdown.

## 6. Data Loading and Performance Requirements

### Requirement: load many records quickly

Use:

- pagination
- search query params
- filter query params
- summary endpoints
- indexes on foreign keys and status/date columns

Do not:

- load all 500+ households into every page
- send full member/device history in list endpoints
- do frontend filtering on thousands of rows

### Recommended indexes

```text
households: purok, status_latest
household_status_logs: disaster_event_id, household_id, status, submitted_at
rescue_dispatches: disaster_event_id, rescue_team_id, status
resource_requests: disaster_event_id, status, request_type
weather_logs: disaster_event_id, observed_at
broadcast_logs: disaster_event_id, sent_at
situation_reports: disaster_event_id, generated_at
```

## 7. API Design Requirements

All frontend apps use the API.

API response shape:

```json
{
  "success": true,
  "message": "Loaded successfully.",
  "data": {},
  "errors": null
}
```

Validation error shape:

```json
{
  "success": false,
  "message": "Please check the form.",
  "data": null,
  "errors": {
    "field_name": ["The field is required."]
  }
}
```

## 8. Search and Filter Standard

All searchable pages should use the same layout:

```text
[ Search input                    ][ All puroks ][ Status ][ Date range ]
```

Applicable pages:

- Household Status
- Mapping
- Rescue Dispatch
- Rescuer Accounts
- Resources and Requests
- Archive

Frontend component:

```text
FilterBar.jsx
```

Backend pattern:

```php
$query->when($request->search, function ($query, $search) {
    $query->where('name', 'like', "%{$search}%");
});
```

## 9. Export Requirements

### CSV

Use Laravel streamed download first.

Reason:

- beginner-friendly
- no heavy package required
- good for archive tables

### PDF

Use PDF only for:

- situation reports
- archive summaries
- disaster event report documents

Keep PDF templates simple and readable.

## 10. Mobile Requirements

### Household Mobile

Screens:

- Login
- Active Event
- Submit Status
- Status History
- Household Profile

Submit status fields:

- disaster_event_id
- household_id
- status
- note
- latitude
- longitude
- submitted_at

### Rescuer Mobile

Screens:

- Login
- Assigned Dispatch
- Update Household Status
- Update Dispatch
- Request Resource

Submit field update fields:

- dispatch_id
- household_id if applicable
- status
- note
- latitude
- longitude
- submitted_at

## 11. Suggested Build Order

Use this order:

1. Folder setup
2. Laravel auth and roles
3. Database migrations
4. Seeders
5. React login and app shell
6. Dashboard summary API and page
7. Household API and page
8. Dispatch API and page
9. Broadcast API and page
10. Weather API and page
11. Mapping API and page
12. Rescuer accounts
13. Resources and requests
14. Situation reporting
15. Archive
16. Mobile login
17. Household mobile
18. Rescuer mobile
19. CSV/PDF exports
20. Final UI polish

## 12. Study Checklist

Before coding, each team member should understand:

- how Laravel routes point to controllers
- how controllers return JSON
- how migrations create tables
- how Eloquent relationships work
- how React Router changes pages
- how Axios calls the API
- how React renders arrays with `.map()`
- how pagination works
- how Leaflet displays markers
- how Expo stores auth tokens
- how CSV/PDF exports work

