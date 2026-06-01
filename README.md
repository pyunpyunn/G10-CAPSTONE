# RESQPERATION: Barangay Rescue Operations and Disaster Response Management System

RESQPERATION is a capstone project for barangay disaster response operations. The system helps HQ/Admin users monitor disaster events, broadcast alerts, review household safety reports, monitor rescue dispatch, validate resource requests, and keep situation reports and archives. Mobile access is planned for household and rescuer users, but the current development focus is the HQ/Admin web system.

## Team Members

- Barro, Nina Kathleen
- Eria, Ereca Joy
- Arellano, Vinzon
- Pacillan, Vince

## Tech Stack

- Backend: Laravel API
- Authentication: Laravel Sanctum token authentication
- Database: MySQL
- Web Frontend: React + Vite
- Mobile Frontend: Expo React Native
- HTTP Client: Axios
- UI Icons: Lucide React
- Mapping: Leaflet / React Leaflet planned for web mapping modules
- Testing: PHPUnit for Laravel, ESLint and Vite build checks for React

## Main Roles or Users

- HQ/Admin: Uses the web dashboard to manage operations, review reports, create rescuer/HQ accounts, validate requests, and generate reports.
- Rescuer: Uses the mobile app login. Full rescuer mobile operations are planned after HQ/Admin backend modules.
- Household Resident: Uses the mobile app login. Household accounts are expected to come from the external SafeTrack system.
- Shared Database Modules:
  - Household account records may come from the shared SafeTrack tables.
  - Resource/personnel requests may be read from shared request tables.
  - Validated requests are marked ready for tracking in the same database.
  - Weather providers such as PAGASA/OpenMeteo are treated as data sources, not separate capstone systems.
  - ExpoPush is treated as a notification provider, not a separate capstone system.

## Default Login Credentials

For local development testing:

| Role | Account ID | Password | App |
| --- | --- | --- | --- |
| HQ/Admin | `2024035500` | `password` | Web |
| Household Resident | `2024035501` | `password` | Mobile |
| Rescuer | `2024035502` | `password` | Mobile |

ID note:

- The login ID uses the existing 10-digit numeric `username` pattern.
- Internal `user_id` values use readable prefixes:
  - `USR-HQ-2024035500`
  - `USR-HH-2024035501`
  - `USR-RSC-2024035502`
- Temporary mobile account SQL proposal: `docs/sql_proposals/2026_06_02_g10_temporary_mobile_login_accounts.sql`

## Set Up Feature Checklist

- [x] Laravel backend folder created
- [x] React web frontend folder created
- [x] Expo mobile frontend folder created
- [x] Root Git repository connected to GitHub
- [x] Private folders ignored from GitHub
- [x] Laravel API authentication created
- [x] Role-based login response created
- [x] HQ/Admin web login connected to backend
- [x] Mobile login created for household and rescuer roles
- [x] Web dashboard shell aligned with the HTML prototype header and sidebar behavior
- [x] API unauthenticated errors return JSON
- [ ] Latest additive DB update proposal reviewed by DB member
- [ ] Shared DB gap review approved
- [ ] Laravel migrations adjusted to the existing shared DB structure
- [ ] HQ/Admin dashboard API connected to live database data
- [ ] Disaster Broadcasting module backend and frontend
- [ ] Weather Updates module with API source integration
- [ ] Mapping module with actual map and geotagged records
- [ ] Household Status module backend and frontend
- [ ] Rescue Dispatch module backend and frontend
- [ ] Rescuer Accounts module backend and frontend
- [ ] Resources & Requests module backend and frontend
- [ ] Situation Reporting module backend and frontend
- [ ] Archive module backend and frontend
- [ ] Push notification sending and receiving

## Current Structure Notes

```text
resqperation-system/
  backend-laravel/      Laravel API backend
  frontend-web/         React web app for HQ/Admin users
  frontend-mobile/      Expo mobile app with household and rescuer login routes
  docs/                 Development plans, DB readiness notes, and SQL proposals
```

Local-only reference folders are intentionally ignored from GitHub:

```text
FOR DEV/
prototype/
.sixth/
.dist/
.claude/
```

Important development rule:

- Do not use HTML files as the backend.
- The HTML prototype is only a UI/UX reference.
- Real development should use Laravel API routes, React pages/components, Expo screens, proper request validation, and JSON responses.
- Do not run shared database migrations until the DB proposal is reviewed and approved.

## Key Documentation

- `docs/RESQPERATION_G10_STEP_BY_STEP_CHECKLIST.md`
- `docs/RESQPERATION_G10_DB_READINESS_CHECK.md`
- `docs/RESQPERATION_G10_DEVELOPMENT_READINESS_AUDIT.md`
- `docs/RESQPERATION_G10_BEGINNER_MODULE_PROMPTS.md`
- `docs/sql_proposals/2026_06_01_g10_all_sql_additive_update.sql`
- `docs/sql_proposals/2026_06_01_g10_existing_db_gap_review.sql` - superseded historical proposal

Private local references:

- `FOR DEV/RESQPERATION_UPDATED_STEP_GUIDE.md`
- `FOR DEV/RESQPERATION_REQUIREMENTS_BREAKDOWN.md`
- `FOR DEV/RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md`
- `FOR DEV/API_REFERENCE.md`
- `FOR DEV/RESQPERATION_PROTOTYPE_MODULES/README.md`

## Useful Commands

Backend:

```bash
cd backend-laravel
php artisan serve --host=127.0.0.1 --port=8000
php artisan test
php artisan route:list
php artisan config:clear
```

Web frontend:

```bash
cd frontend-web
npm install
npm run dev
npm run build
npm run lint
```

Mobile frontend:

```bash
cd frontend-mobile
npm install
npx expo start
npx tsc --noEmit
npm run lint
```

Local development URLs:

```text
Web app: http://127.0.0.1:5173/login
Backend API: http://127.0.0.1:8000/api/v1
```
