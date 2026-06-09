# RESQPERATION G10 Step-by-Step Development Checklist

Date updated: 2026-06-01  
Project folder: `C:\backend\G10CAPSTONE\resqperation-system`

Reference source:

- `FOR DEV/RESQPERATION_UPDATED_STEP_GUIDE.md`
- `FOR DEV/RESQPERATION_REQUIREMENTS_BREAKDOWN.md`
- `FOR DEV/RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md`
- `FOR DEV/API_REFERENCE.md`
- `FOR DEV/PUSH_NOTIFICATIONS.md`
- `FOR DEV/GEOTAGGING_DISPATCH_ROUTING.md`

Rule:

Do not use the full `prototype/resqperation-2 (1).html` for development. If a UI reference is needed, use only the split module files under `FOR DEV/RESQPERATION_PROTOTYPE_MODULES/`.

## Current Setup Checklist

- [x] Main project folder exists: `resqperation-system`
- [x] Backend folder exists: `backend-laravel`
- [x] Web frontend folder exists: `frontend-web`
- [x] Mobile frontend folder exists: `frontend-mobile`
- [x] Docs folder exists: `docs`
- [x] `FOR DEV` exists locally for private development reference
- [x] `prototype` exists locally for private reference only
- [x] Backend dependencies installed: `backend-laravel/vendor`
- [x] Web dependencies installed: `frontend-web/node_modules`
- [x] Mobile dependencies installed: `frontend-mobile/node_modules`
- [x] Git repository initialized
- [x] GitHub remote connected: `https://github.com/pyunpyunn/G10-CAPSTONE.git`
- [x] Initial commit pushed to GitHub
- [x] Root `.gitignore` excludes private/dev-only folders and files
- [x] `FOR DEV/` excluded from GitHub
- [x] `prototype/` excluded from GitHub
- [x] `.env` files excluded from GitHub
- [x] `.sixth/`, `.dist/`, `.claude/`, `CLAUDE.md`, and `AGENTS.md` excluded from GitHub
- [x] `node_modules/` and `vendor/` excluded from GitHub
- [x] Mobile has role folders inside one Expo app:
  - `frontend-mobile/app/household`
  - `frontend-mobile/app/rescuer`

## Database Readiness Checklist

- [x] Shared MySQL database is reachable
- [x] Laravel `.env` has `DB_CONNECTION=mysql`
- [x] Laravel `.env` uses `DB_DATABASE=klint`
- [x] `php artisan config:clear` was run after fixing `.env`
- [x] `php artisan migrate:status` can now read MySQL
- [x] Existing DB structure was checked
- [x] Existing DB uses custom primary keys such as:
  - `users.user_id`
  - `roles.role_id`
  - `disaster_events.event_id`
  - `households.household_id`
  - `resource_requests.request_id`
- [x] DB readiness note created: `docs/RESQPERATION_G10_DB_READINESS_CHECK.md`
- [x] Existing DB gap SQL proposal created: `docs/sql_proposals/2026_06_01_g10_existing_db_gap_review.sql`
- [x] Latest DB member `all.sql` export reviewed
- [x] Additive DB update proposal created: `docs/sql_proposals/2026_06_01_g10_all_sql_additive_update.sql`
- [x] Older DB gap proposal marked as superseded
- [x] SQL gap proposal reviewed by DB member/team for shared DB use
- [x] Live DB schema safety check completed before applying additive SQL
- [x] Additive DB update applied to the shared DB
- [x] Laravel migrations adjusted to existing DB style
- [x] Default Laravel `create_users_table` migration handled safely
- [x] Laravel migration tracker fixed and current backend migrations recorded

Important database warning:

Do not restore the default Laravel `users` migration. The shared DB already has a custom `users` table using `user_id`, `first_name`, and `last_name`.

## Master Build Order Checklist

### 1. Folder Setup

- [x] Project folder created
- [x] Backend Laravel folder created
- [x] React web folder created
- [x] Expo mobile folder created
- [x] Docs folder created
- [x] Private reference folders kept local and ignored from GitHub

Status: done.

### 2. Backend Auth and Roles

- [x] Update `User` model for existing `users.user_id`
- [x] Add `Role` model for existing `roles.role_id`
- [x] Confirm existing users/roles column names
- [x] Decide final login identifier: username or email
- [x] Create `AuthController`
- [x] Create `LoginRequest`
- [x] Create `UserResource`
- [x] Add login endpoint: `POST /api/v1/auth/login`
- [x] Add logout endpoint: `POST /api/v1/auth/logout`
- [x] Add current user endpoint: `GET /api/v1/auth/me`
- [x] Add role middleware
- [x] Protect API routes using Sanctum
- [x] Test login with existing DB user

Existing DB role keys:

```text
super_admin
evac_admin
evac_personnel
admin
rescuer
household_resident
```

Status: done for backend foundation. A test HQ Admin login now exists in the shared DB.

### 3. Database Migrations / DB Alignment

- [x] Review latest additive DB update SQL
- [x] Run live schema safety check before applying SQL
- [x] Decide which changes are needed for version 1
- [x] Apply approved additive SQL to shared DB
- [x] Avoid duplicate tables
- [x] Use existing custom primary keys and table names
- [x] Add missing household device fields
- [x] Add household status history table
- [x] Add weather logs table
- [x] Add resource validation fields/table
- [x] Adjust Laravel default migrations to avoid breaking the shared DB
- [x] Confirm `php artisan migrate:status` shows current backend migrations as ran

Status: done for current DB alignment. Future module migrations should follow the shared DB naming and key style.

### 4. Seeders

- [x] Create safe seeders only after auth models match DB
- [x] Seed missing roles only if they do not already exist
- [x] Seed admin/HQ/rescuer/household test users only with approval
- [x] Seed sample active disaster event
- [x] Seed sample household/status data only in a safe test plan

Status: temporary login seeders and safe sample disaster/status seeders are done.

### 5. Web App Shell

- [x] Replace default Vite starter UI
- [x] Create React Router setup
- [x] Create `AppShell`
- [x] Create `Sidebar`
- [x] Create `Topbar`
- [x] Create `ProtectedRoute`
- [x] Create `LoginPage`
- [x] Add page placeholders for all modules
- [x] Use API base URL from `frontend-web/.env`

Status: mostly done. Web shell and login are built; real module APIs/pages will be built one by one.

### 6. Shared Web Components

- [x] `Button`
- [x] `IconButton`
- [x] `Badge`
- [x] `StatCard`
- [x] `DataTable`
- [x] `FilterBar`
- [x] `Modal`
- [x] `ActionMenu`
- [x] `EmptyState`
- [x] `LoadingState`

Status: done for the beginner shared component set. Add specialized controls only when a module really needs them.

### 6.1 Barangay Profile / Deployment Settings

- [x] Confirm existing shared DB has `barangays` and `addresses.barangay_id`
- [x] Create review-only SQL proposal for `barangay_profiles`
- [x] Add backend Barangay Profile service with `.env` fallback
- [x] Connect Dashboard, Weather, and Mapping to the shared profile service
- [ ] Apply `barangay_profiles` table only after DB member approval
- [ ] Build HQ/Admin Barangay Profile settings UI
- [ ] Filter Household, Broadcast, Dispatch, Resources, and Reports by active barangay

Status: foundation started. No DB schema was changed. Current proposal: `docs/sql_proposals/initial/2026_06_03_g10_barangay_profile_review.sql`. Design note: `docs/RESQPERATION_BARANGAY_PROFILE_SCOPE.md`.

### 7. Dashboard

- [x] Backend summary endpoint
- [x] Backend recent activity endpoint
- [x] Dashboard page
- [x] Summary cards
- [x] Recent activity list
- [x] Weather summary empty/live state
- [x] Map preview empty/live state
- [x] No hardcoded Typhoon Carina/sample prototype banner

Status: done for the first live HQ/Admin dashboard. The page now uses `/api/v1/dashboard` and shows standby/empty states when there is no active event.

### 8. Household Status

- [x] Backend household list endpoint with pagination
- [x] Backend household detail endpoint
- [x] Backend household status history endpoint
- [x] Backend status report endpoint for household/rescuer only
- [x] Prevent HQ/admin from manually changing household status
- [x] Web summary cards:
  - unchecked
  - safe total
  - safe only
  - evacuated
  - unsafe
- [x] Household table
- [x] Members table in detail modal
- [x] Device battery and location display
- [x] Search/filter by household, purok, status, and device risk

Status: done for the first live HQ/Admin Household Status module. The web page uses `/api/v1/households`, loads details/history only when a household is opened, and does not expose any HQ/Admin manual status edit action. Status updates are accepted only through the household/rescuer API route.

### 9. Rescue Dispatch

- [x] Rescue team API
- [x] Dispatch list API
- [x] Create dispatch API
- [x] Update dispatch status API
- [x] Complete dispatch API
- [x] Rescuer location update API
- [x] Dispatch web page
- [x] Create/update modals

Status: done for the first live HQ/Admin Rescue Dispatch module. The backend uses the approved shared DB mapping: `rescue_teams`, `responders`, `responder_assignments`, `responder_routes`, `route_coordinates`, and `responder_location_logs`. No migrations were run and no schema was changed. Current shared DB note: `rescue_teams` has no formal team rows yet, so the page displays the existing temporary responder as an unassigned responder pool until HQ/Admin registers actual teams.

### 10. Disaster Broadcasting

- [x] Disaster event API
- [x] Broadcast log API
- [x] Broadcast page
- [x] Save broadcast logs first
- [x] Apply approved broadcast metadata columns to shared DB
- [ ] Add Expo push later after mobile works

Status: v1 done for the live HQ/Admin Disaster Broadcasting module. The web page now uses the Laravel API instead of the placeholder prototype, supports active-event lifecycle display, declaration/update compose flow, recipient scope notes, direct-impact purok selection, the 4-status mobile button rule, and active-event broadcast logs. On 2026-06-03, the approved nullable metadata columns from `docs/sql_proposals/initial/2026_06_03_g10_disaster_broadcast_metadata_review.sql` were applied to the shared DB with guarded column checks. No Laravel migrations were run. Expo push is still deferred until the mobile notification workflow is ready.

### 11. Weather Updates

- [x] Weather log table/DB alignment approved
- [x] Laravel weather refresh endpoint
- [x] Open-Meteo fetch through Laravel
- [x] PAGASA advisory support
- [x] Weather page
- [x] Store weather snapshots for archive and SitRep
- [x] Automatic Open-Meteo refresh command and scheduler
- [x] PAGASA official-warning confirmation note

Status: v1 done for the live HQ/Admin Weather Updates module. React displays saved Laravel weather logs only; it does not call weather providers directly. Laravel now provides `/api/v1/weather`, `/api/v1/weather/refresh`, `/api/v1/disaster-events/{eventId}/weather-logs`, and `/api/v1/disaster-events/{eventId}/weather-logs/refresh`. The `weather:refresh` Artisan command fetches structured Open-Meteo data, stores it in `weather_logs`, and is scheduled every 3 hours through Laravel Scheduler. Snapshots are linked to the active disaster event when one exists; otherwise they are saved as general monitoring snapshots. PAGASA is included through official advisory links and the page states: "Confirm official warnings through PAGASA before broadcasting." No migrations were run. If PAGASA approves the Ten-Day API token later, Laravel can add it as another backend source without changing the frontend workflow.

### 12. Mapping

- [x] Map household geotag endpoint
- [x] Evacuation site endpoint
- [x] Dispatch route/team endpoint
- [x] Leaflet page
- [x] Household markers
- [x] Evacuation site markers
- [x] Rescue team markers
- [x] Route display on demand

Status: v1 done for the live HQ/Admin Mapping module. The backend uses existing shared DB tables only: `geotagged_locations`, `households`, `household_disasters`, `household_statuses`, `evacuation_centers`, `responder_location_logs`, `responders`, `rescue_teams`, `responder_assignments`, `responder_routes`, and `route_coordinates`. No migrations were run and no schema was changed. React now uses Leaflet/OpenStreetMap tiles, automatically focuses on Barangay Guadalupe, hides operational status layers when there is no active disaster event, and shows household GPS markers, evacuation pins, rescue team markers, stored route lines, and on-demand route display during an active event. Live DB payload testing is still pending because the shared DB server was offline during this step.

### 13. Rescuer Accounts

- [x] Align responder/rescuer tables with auth
- [x] Create rescuer API
- [x] Edit rescuer API
- [x] Deactivate rescuer API
- [x] Rescuer accounts page
- [x] Create account modal
- [x] View/edit/deactivate actions

Status: v1 done for the live HQ/Admin Rescuer Accounts module. The backend reuses existing shared DB tables only: `users`, `roles`, `responders`, `rescue_teams`, and `audit_logs`. No migrations were run and no schema was changed. HQ/Admin can load the verified roster, create a rescuer login, assign a team, update account/contact/training/equipment details, and deactivate the account while keeping the responder record for audit and dispatch history. New HQ-created rescuer login IDs follow one BDRRM team-code format only: `BDRRM-{TEAM_CODE}-###`, such as `BDRRM-SAR-001`. The sequence is counted per team code, so the first MED account is `BDRRM-MED-001` and the second SAR account is `BDRRM-SAR-002`. The frontend uses feature-based component splitting for the notice, stats, filters, roster table, team grid, and account modal. Rollback-only CRUD testing passed, so no temporary rescuer test record was left in the shared DB.

### 14. Resources and Requests

- [x] Align existing `resource_requests` table
- [x] Add request validation workflow after approval
- [x] Add shared DB request source/reference fields after approval
- [x] Add tracking-ready fields after approval
- [x] Request queue page
- [x] Validation modal
- [x] Mark ready for tracking / return actions

Status: v1 done for the live HQ/Admin Resources & Requests module. The backend reuses the existing shared DB tables only: `resource_requests`, `request_validations`, `resource_request_status`, `urgency_levels`, and `evacuation_centers`. No migrations were run and no schema was changed. RESQPERATION can load the live validation queue, create a manual HQ request, save validation decisions, return incomplete or duplicate requests, and forward verified requests with a TrackingAid handoff reference. The frontend uses feature-based component splitting for the notice, stats, filters, queue table, validation modal, and TrackingAid mirror. Rollback-only API testing passed for list, create, validate, and forward, so no temporary resource request was left in the shared DB.

### 15. Situation Reporting

- [x] Event dropdown
- [x] Situation summary endpoint
- [x] Generate report endpoint
- [x] Saved report page
- [ ] PDF export later

Status: v1 done for live HQ/Admin Situation Reporting. The backend reuses the existing shared DB tables only: `situation_reports`, `disaster_events`, `household_disasters`, `household_status_logs`, `responder_assignments`, `weather_logs`, `resource_requests`, and `evacuation_centers`. No migrations were run and no schema was changed. HQ/Admin must select a disaster event before the SitRep preview appears. The summary endpoint collects event, household, casualty, evacuation, dispatch, weather, and resource/request data from the shared DB. The generate endpoint saves a locked JSON snapshot in `situation_reports.summary`. The frontend uses feature-based component splitting for the event selector, action menu, SitRep preview, generation modal, and saved report list. Rollback-only API testing passed for workspace load, event summary, and generated report save, so no temporary SitRep was left in the shared DB. PDF export is intentionally left for the later PDF package step.

### 16. Archive

- [x] Archive tabs
- [x] Disaster event archive endpoint
- [x] Household status archive endpoint
- [x] Dispatch archive endpoint
- [x] Resource request archive endpoint
- [x] Situation report archive endpoint
- [x] CSV export first
- [ ] PDF export later

Status: v1 done for live HQ/Admin Archive. The backend reuses existing shared DB records only: `disaster_events`, `disaster_broadcasts`, `weather_logs`, `household_status_logs`, `household_disasters`, `responder_assignments`, `resource_requests`, `request_validations`, `situation_reports`, `incident_archives`, and related lookup tables. No migrations were run and no schema was changed. Archive endpoints now support tab-specific loading, search, event, purok, status filters, pagination, and CSV export. The frontend uses feature-based component splitting for tabs, filters, download menu, archive table, and record modal. Live DB read-only testing passed for all five archive categories and CSV export. PDF export is intentionally left for the later PDF package step.

### 16.1 Notifications and HQ Profile

- [x] Proper shared page loading screen
- [x] Notification bell preview
- [x] Notifications page with all/unread/read filter
- [x] Mark all read, delete selected, and clear current HQ view actions
- [x] Profile page
- [x] Edit profile modal
- [x] Change password modal
- [x] Feature-based component split

Status: v1 done for live HQ/Admin Notifications and Profile. The backend reuses existing shared DB tables only: `notifications`, `notification_logs`, `notification_recipients`, `household_status_logs`, `responder_assignments`, `resource_requests`, `weather_logs`, `disaster_broadcasts`, `audit_logs`, `users`, and `personal_access_tokens`. No migrations were run and no schema was changed. Notification actions are intentionally audit-logged only and do not delete delivery logs or broadcast records. Profile editing updates the authenticated HQ/Admin user in `users`, while barangay deployment settings still depend on the separate `barangay_profiles` proposal.

### 17. Mobile Household

- [x] Decide whether to keep Expo Router/TypeScript or simplify to JavaScript screens
- [x] Login flow
- [x] Household home placeholder
- [ ] Submit status screen
- [ ] Status history screen
- [ ] Household profile/device location screen
- [ ] Send location if permission granted
- [ ] Send battery level if available
- [ ] Register/update device record

Status: login and role routing only. Other household mobile features wait until the Household Status module.

### 18. Mobile Rescuer

- [x] Login flow
- [x] Rescuer home placeholder
- [ ] Assigned dispatch screen
- [ ] Update household status screen
- [ ] Update dispatch status screen
- [ ] Request resources/personnel screen
- [ ] Foreground location tracking while on mission

Status: login and role routing only. Other rescuer mobile features wait until Dispatch/Household Status modules.

### 19. Exports

- [ ] CSV exports
- [ ] Situation report PDF
- [ ] Archive PDF summaries
- [ ] Role protection for export endpoints

Status: not started.

### 20. Final QA

- [ ] Login by role works
- [ ] Backend returns JSON only
- [ ] No dashboard HTML in Blade
- [ ] Validation exists for all write endpoints
- [ ] Role protection works
- [ ] Household status cannot be manually edited by HQ
- [ ] Pagination works for large lists
- [ ] Dashboard uses summary endpoints
- [ ] Weather is fetched by Laravel and stored
- [ ] Maps use small marker payloads
- [ ] Mobile household status submission works
- [ ] Mobile rescuer update works
- [ ] CSV/PDF downloads work
- [ ] GitHub does not contain private/dev-only folders

Status: not started.

## Next Thing To Do

The current development task is:

```text
Start Resources and Requests API/page.
```

Latest completed module:

- Rescuer Accounts v1 is connected to existing `users`, `roles`, `responders`, `rescue_teams`, and `audit_logs`.
- No migration or schema change was run for Rescuer Accounts.
- Rollback-only create/update/deactivate testing passed without leaving a test record in the shared DB.

Still needed:

1. Start section 14: Resources and Requests.
2. Keep any DB changes review-only first.
3. Continue using feature-based component splitting for large pages.
