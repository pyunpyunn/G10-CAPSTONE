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
- [ ] SQL gap proposal reviewed by team/instructor
- [ ] Additive DB update proposal tested on a local database copy
- [ ] Laravel migrations adjusted to existing DB style
- [ ] Default Laravel `create_users_table` migration handled safely
- [ ] No new migration has been run against the shared DB yet

Important database warning:

Do not run `php artisan migrate` yet. The shared DB already has a custom `users` table using `user_id`, while the default Laravel migration expects `id`.

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

- [ ] Review latest additive DB update SQL
- [ ] Test latest additive DB update SQL on a local copy first
- [ ] Decide which changes are needed for version 1
- [ ] Convert approved SQL changes to Laravel migrations
- [ ] Avoid duplicate tables
- [ ] Use existing custom primary keys
- [ ] Add missing household device fields
- [ ] Add household status history table or align with existing logs
- [ ] Add weather logs if approved
- [ ] Add resource validation fields/tables if approved
- [ ] Add integration logs if approved

Status: blocked until review/approval.

### 4. Seeders

- [ ] Create safe seeders only after auth models match DB
- [ ] Seed missing roles only if they do not already exist
- [ ] Seed admin/HQ/rescuer/household test users only with approval
- [ ] Seed sample active disaster event
- [ ] Seed sample household/status data only in a safe test plan

Status: not started.

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

- [ ] `Button`
- [ ] `IconButton`
- [ ] `Badge`
- [ ] `StatCard`
- [ ] `DataTable`
- [ ] `FilterBar`
- [ ] `Modal`
- [ ] `ActionMenu`
- [ ] `EmptyState`
- [ ] `LoadingState`

Status: not started.

### 7. Dashboard

- [ ] Backend summary endpoint
- [ ] Backend recent activity endpoint
- [ ] Dashboard page
- [ ] Summary cards
- [ ] Recent activity list
- [ ] Weather summary placeholder
- [ ] Map preview placeholder

Status: not started.

### 8. Household Status

- [ ] Backend household list endpoint with pagination
- [ ] Backend household detail endpoint
- [ ] Backend household status history endpoint
- [ ] Backend status report endpoint for household/rescuer only
- [ ] Prevent HQ/admin from manually changing household status
- [ ] Web summary cards:
  - unchecked
  - safe total
  - safe only
  - evacuated
  - unsafe
- [ ] Household table
- [ ] Members table in detail modal
- [ ] Device battery and location display
- [ ] Search/filter by household, purok, status, and device risk

Status: not started.

### 9. Rescue Dispatch

- [ ] Rescue team API
- [ ] Dispatch list API
- [ ] Create dispatch API
- [ ] Update dispatch status API
- [ ] Complete dispatch API
- [ ] Rescuer location update API
- [ ] Dispatch web page
- [ ] Create/update modals

Status: not started.

### 10. Disaster Broadcasting

- [ ] Disaster event API
- [ ] Broadcast log API
- [ ] Broadcast page
- [ ] Save broadcast logs first
- [ ] Add Expo push later after mobile works

Status: not started.

### 11. Weather Updates

- [ ] Weather log table/DB alignment approved
- [ ] Laravel weather refresh endpoint
- [ ] Open-Meteo fetch through Laravel
- [ ] PAGASA advisory support
- [ ] Weather page
- [ ] Store weather snapshots for archive and SitRep

Status: not started.

### 12. Mapping

- [ ] Map household geotag endpoint
- [ ] Evacuation site endpoint
- [ ] Dispatch route/team endpoint
- [ ] Leaflet page
- [ ] Household markers
- [ ] Evacuation site markers
- [ ] Rescue team markers
- [ ] Route display on demand

Status: not started.

### 13. Rescuer Accounts

- [ ] Align responder/rescuer tables with auth
- [ ] Create rescuer API
- [ ] Edit rescuer API
- [ ] Deactivate rescuer API
- [ ] Rescuer accounts page
- [ ] Create account modal
- [ ] View/edit/deactivate actions

Status: not started.

### 14. Resources and Requests

- [ ] Align existing `resource_requests` table
- [ ] Add request validation workflow after approval
- [ ] Add EvaTrack inbound reference fields after approval
- [ ] Add TrackingAid forwarding reference fields after approval
- [ ] Request queue page
- [ ] Validation modal
- [ ] Forward/return actions

Status: not started.

### 15. Situation Reporting

- [ ] Event dropdown
- [ ] Situation summary endpoint
- [ ] Generate report endpoint
- [ ] Saved report page
- [ ] PDF export later

Status: not started.

### 16. Archive

- [ ] Archive tabs
- [ ] Disaster event archive endpoint
- [ ] Household status archive endpoint
- [ ] Dispatch archive endpoint
- [ ] Resource request archive endpoint
- [ ] Situation report archive endpoint
- [ ] CSV export first
- [ ] PDF export later

Status: not started.

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
Start Dashboard API/page.
```

Completed:

- `User` model now uses `user_id`.
- `Role` model now uses `role_id`.
- Login accepts username or email.
- Auth endpoints exist:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```
- Web login and HQ/Admin app shell are built.
- Mobile login routes household/rescuer users to their role folders only.
- Auth was verified through real HTTP login, `/auth/me`, and logout using the HQ Admin test account.

Still needed:

1. Keep migrations paused while the DB member works.
2. Manually try the web login screen in the browser.
3. Start real module development with Dashboard API/page.
