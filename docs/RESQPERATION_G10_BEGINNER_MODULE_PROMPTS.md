# RESQPERATION G10 Beginner Module Prompts

Use these prompts one at a time during actual development. Do not ask Codex to build the whole system in one prompt.

Always include this starter line:

```text
Write beginner-friendly code. Do not over-engineer. Use simple Laravel controllers, Eloquent, Form Requests, API Resources, React function components, useState/useEffect, Axios, and reusable components. Render repeated cards, filters, tabs, and table rows from arrays using .map(). Laravel must return JSON only. Do not put dashboard HTML in Blade.
```

## 1. Project Safety Prompt

```text
You are working in C:\backend\G10CAPSTONE\resqperation-system.

Before editing, inspect the current folder structure, package files, routes, migrations, and existing components. Do not read the full HTML prototype. If a prototype reference is needed, ask me which split module file to use.

Do not run migrations against the shared MySQL database unless I explicitly say the schema is approved. If database changes are needed, write a review SQL file or Laravel migration draft first.
```

## 2. Backend Auth and Roles Prompt

```text
Build the Laravel API authentication foundation for RESQPERATION.

Requirements:
- Laravel is API only.
- Use Sanctum tokens.
- Disable public registration.
- Roles are admin, hq_dispatcher, rescuer, household.
- Use one backend for web and mobile.
- Create AuthController with login, logout, and me.
- Create Form Request validation for login.
- Create UserResource for the logged-in user response.
- Add protected /api/v1 routes.
- Add simple role middleware.
- Seed test users for each role.

Keep code beginner-friendly and explain what files changed.
Do not build web pages in Laravel Blade.
Do not touch unrelated modules.
```

## 3. Database Review Prompt

```text
Analyze the current Laravel migrations and the review SQL script in docs/sql_proposals.

Do not run php artisan migrate.
Check whether the proposed schema follows:
- snake_case plural table names
- singular _id foreign keys
- existing users table structure
- shared database safety
- household device tracking
- no HQ manual household status changes
- EvaTrack inbound and TrackingAid forwarding fields

Give me a beginner-friendly review with corrections before any migration is generated.
```

## 4. Database Migration Prompt

```text
Create Laravel migrations from the approved SQL proposal.

Rules:
- Do not run migrations unless I explicitly approve.
- Keep migrations simple and readable.
- Use foreign keys where clear.
- Add indexes for event_id, status, purok, timestamps, and search fields.
- Use string status columns with validation in Form Requests instead of complex logic.
- Update User model fillable/casts.
- Add Eloquent relationships only where needed for the first module.

After creating migrations, show the list of files and explain how to run them later.
```

## 5. Web App Shell Prompt

```text
Build the React web app shell for RESQPERATION.

Requirements:
- Use React + Vite + JavaScript.
- Use React Router.
- Use Axios.
- Use Lucide React icons.
- Create AppShell, Sidebar, Topbar, ProtectedRoute, and LoginPage.
- Add route placeholders for Dashboard, Broadcast, Weather, Mapping, Household Status, Rescue Dispatch, Rescuer Accounts, Resources and Requests, Situation Reporting, and Archive.
- Store token simply for version 1.
- Redirect users based on role.
- Use shared CSS, not Tailwind.

Keep UI minimal and operational. No landing page unless needed.
```

## 6. Shared Components Prompt

```text
Create shared React components for the web app.

Components:
- Button
- IconButton
- Badge
- StatCard
- DataTable
- FilterBar
- Modal
- ActionMenu
- EmptyState
- LoadingState

Rules:
- Use Lucide icons where appropriate.
- Use arrays for columns, filters, tabs, and actions.
- Keep components small.
- Do not create repeated card/table markup in every page.
- Make the styling consistent with the prototype design direction.
```

## 7. Dashboard Module Prompt

```text
Build the Dashboard API and page.

Backend:
- GET /api/v1/dashboard/summary
- GET /api/v1/dashboard/recent-activity
- Use aggregate counts, not full-table loading.
- Return compact JSON only.

Frontend:
- DashboardPage.jsx
- dashboardApi.js
- Stat cards for active event, households, dispatch, requests, weather.
- Recent activity list.
- Minimal map preview placeholder if mapping is not ready yet.

Use pagination and summary endpoints. Keep code beginner-friendly.
```

## 8. Household Status Module Prompt

```text
Build the Household Status API and web page.

Important rule:
HQ/admin must not manually update household status. Status can only come from household mobile reports, authenticated rescuer field reports, or approved external import.

Backend:
- GET /api/v1/households?search=&purok=&status=&device_risk=&page=1
- GET /api/v1/households/{id}
- GET /api/v1/households/{id}/status-logs
- POST /api/v1/households/{id}/status-logs for household/rescuer roles only

Data to show:
- unchecked
- safe total
- safe only
- evacuated
- unsafe
- household members
- household devices
- battery level
- last known device location
- location permission status
- last report time

Frontend:
- Minimal summary cards.
- Progress bar by status.
- Paginated household table.
- Detail modal loads members, devices, and history only when opened.
- Filters for search, purok, status, and device risk.

Keep the page simple enough for 1000+ households.
```

## 9. Rescue Dispatch Module Prompt

```text
Build the Rescue Dispatch API and web page.

Backend:
- GET /api/v1/rescue-teams
- GET /api/v1/dispatches?event_id=&status=&page=1
- POST /api/v1/dispatches
- GET /api/v1/dispatches/{id}
- PATCH /api/v1/dispatches/{id}
- POST /api/v1/dispatches/{id}/complete
- PATCH /api/v1/dispatches/{id}/location for rescuer mobile location updates

Frontend:
- Team status cards.
- Dispatch table.
- Create dispatch modal.
- Update status modal.
- Outcome fields: safe, evacuated, unsafe, injured, missing.

No advanced route optimizer in version 1.
```

## 10. Broadcast and Notification Prompt

```text
Build disaster broadcast logs first, then prepare for push notifications.

Backend version 1:
- disaster_events table/API
- broadcast_logs table/API
- Save all broadcasts.
- Filter recipient scope by all, rescuers_only, or purok_specific.
- Do not require Expo push yet.

Backend version 2:
- Register mobile device push tokens.
- Send Expo push notifications in batches.
- Keep database broadcast logs as the official record.

Frontend:
- BroadcastPage.jsx
- Create event form.
- Send broadcast modal.
- Affected purok selector.
- Priority selector.
- Broadcast history table.
```

## 11. Weather Module Prompt

```text
Build the Weather API and page.

Important:
Do not call weather APIs directly from React. Laravel fetches weather, saves weather_logs, and React displays saved logs.

Backend:
- GET /api/v1/disaster-events/{id}/weather-logs
- POST /api/v1/disaster-events/{id}/weather-logs/refresh
- Use Laravel Http client.
- Use Open-Meteo for numeric weather.
- Add PAGASA advisory support after the basic refresh works.
- Store observed_at, source, condition, temperature, rainfall, wind speed, wind direction, and advisory text if available.

Frontend:
- WeatherPage.jsx
- WeatherMetricCard
- AdvisoryList
- Refresh Weather button
- Show source attribution.
```

## 12. Mapping and Geotagging Prompt

```text
Build the Mapping API and page.

Backend:
- GET /api/v1/map/household-geotags?event_id=&purok=&status=
- GET /api/v1/map/evacuation-sites?event_id=
- GET /api/v1/map/dispatch-routes?event_id=
- Return only fields needed by the map.

Frontend:
- Leaflet and React Leaflet.
- OpenStreetMap or OpenFreeMap tile layer.
- Household markers colored by status.
- Evacuation site markers.
- Rescue team markers.
- Layer toggles.
- Purok/status filters.
- Route line on demand using OSRM or OpenRouteService.

Mobile:
- Household geotag is one-time or profile update.
- Rescuer live tracking is foreground only while on mission.
```

## 13. Rescuer Accounts Prompt

```text
Build the Rescuer Accounts API and page.

Business rule:
No pending public verification. HQ/admin creates verified rescuer accounts manually.

Backend:
- GET /api/v1/rescuers
- POST /api/v1/rescuers
- GET /api/v1/rescuers/{id}
- PATCH /api/v1/rescuers/{id}
- POST /api/v1/rescuers/{id}/deactivate

Required details:
- name
- username/email
- mobile number
- team
- position or duty role
- skills/training
- license or certification reference if available
- emergency contact
- duty status
- account status

Frontend:
- Create Account modal.
- View modal.
- Edit modal.
- Deactivate action.
- Search/filter by team, duty status, and account status.
- Use correct CRUD icons.
```

## 14. Resources and Requests Prompt

```text
Build the Resources and Requests API and page.

Scope:
RESQPERATION receives requests, validates them, and forwards verified requests. It does not deliver resources.

External systems:
- EvaTrack can send incoming resource/personnel requests.
- TrackingAid receives forwarded resource requests.

Backend:
- GET /api/v1/resource-requests?event_id=&status=&source_system=&page=1
- POST /api/v1/resource-requests
- GET /api/v1/resource-requests/{id}
- POST /api/v1/resource-requests/{id}/validate
- POST /api/v1/resource-requests/{id}/forward
- POST /api/v1/resource-requests/{id}/return

Statuses:
- needs_validation
- verified
- returned
- forwarded
- fulfilled
- cancelled

Frontend:
- Request queue table.
- Validation modal.
- Forward action.
- Return for missing info action.
- TrackingAid mirror panel.
- Show source system and external reference.
```

## 15. Situation Report and Archive Prompt

```text
Build Situation Reporting and Archive after core modules have data.

Situation Reporting:
- HQ selects disaster event.
- Load summary endpoint.
- Generate saved report from current snapshot.
- Export PDF from saved report, not live changing data.

Archive:
- Tabs for disaster events, household logs, dispatch logs, resource requests, and situation reports.
- Use existing historical records.
- Do not duplicate archive tables unless required.
- Add CSV export first.
- Add PDF export after CSV works.
```

## 16. Mobile Household Prompt

```text
Build the household mobile flow.

Screens:
- login
- household home
- submit status
- status history
- profile/device location

Requirements:
- Show active broadcast.
- Submit status: safe, evacuated, unsafe, injured, missing.
- Send location if permission is granted.
- Send battery level if available.
- Register/update household device record.
- Show last submitted status.
- Store token securely.

Use one mobile app. Do not create a second backend.
```

## 17. Mobile Rescuer Prompt

```text
Build the rescuer mobile flow.

Screens:
- login
- rescuer home
- assigned dispatch
- update household status
- update dispatch status
- request resource/personnel support

Requirements:
- Show active assignment.
- Update dispatch status.
- Submit authenticated field report for household status.
- Send foreground location while on mission.
- Stop location tracking when mission is completed or screen closes.
- Allow resource/personnel request from the field.
```

## 18. Final QA Prompt

```text
Review the RESQPERATION implementation like a technical defense panel.

Check:
- Laravel backend returns JSON only.
- No dashboard HTML exists in Blade.
- Input validation exists for every write endpoint.
- Role protection exists.
- Household status cannot be manually edited by HQ.
- Pagination exists for large lists.
- Dashboard uses summary endpoints.
- Weather is fetched by Laravel and stored.
- Maps return small marker payloads.
- Mobile role routing works.
- Shared database changes are documented.
- Errors are user-friendly.

List bugs first with file and line references, then suggest small beginner-friendly fixes.
```

