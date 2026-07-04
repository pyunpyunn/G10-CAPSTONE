# RESQPERATION: Barangay Rescue Operations and Disaster Response Management System

RESQPERATION is a capstone project for barangay disaster response operations. The system helps HQ/Admin users monitor disaster events, broadcast alerts, review household safety reports, monitor rescue dispatch, validate resource requests, and keep situation reports and archives. It also includes Expo mobile access for household residents and rescuers through the same Laravel API.

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
- Mapping: Leaflet / React Leaflet for web maps, React Native Maps / external map links for mobile maps
- Push Notifications: OneSignal
- Testing: PHPUnit for Laravel, ESLint and Vite build checks for React

## Main Roles or Users

- HQ/Admin: Uses the web dashboard to manage operations, review reports, create rescuer/HQ accounts, validate requests, and generate reports.
- Super Admin: Uses the web dashboard to access system-wide administration and inquiries where enabled.
- Rescuer: Uses the mobile app to receive assignments, update route/status, submit field reports, request resources, and use radio/PTT voice clips.
- Household Resident: Uses the mobile app to complete first-time geotag setup, update household status, manage family/trusted household views, and access evacuation QR.
- Shared Database Modules:
  - Household account records may come from the shared SafeTrack tables.
  - Resource/personnel requests may be read from shared request tables.
  - EvaTrack can send resource/personnel requests into RESQPERATION through the external request intake endpoint.
  - Validated requests are forwarded to TrackingAid through the configured second database handoff table.
  - Weather providers such as PAGASA/OpenMeteo are treated as data sources, not separate capstone systems.
  - OneSignal is the active mobile push notification provider.

## Default Login Credentials

For local development testing:

| Role | Account ID | Password | App |
| --- | --- | --- | --- |
| HQ/Admin | `2024035500` | `password` | Web |
| Household Resident | `2024035501` | `password` | Mobile |
| Household Resident Setup Test | `2024035503` | `password` | Mobile |
| Rescuer Temp SAR Test | `BDRRM-SAR-001` | `password` | Mobile |
| Rescuer Medical Test | `BDRRM-MED-001` | `password` | Mobile |
| Rescuer SAR Test | `BDRRM-SAR-002` | `password` | Mobile |

ID note:

- Login credentials are not hardcoded in the app. The backend checks the shared MySQL `users` table password hash, then falls back to the linked `responders` account ID for rescuer login.
- Shared SafeTrack household accounts can log in when their account exists in `users` with `role_key = household_resident`, a valid `password`, and a linked `household_id`.
- The login field accepts DB-backed identifiers: `username`, `email`, `user_id`, linked `household_id`, or rescuer `BDRRM` account ID.
- Household and HQ/Admin temporary test accounts still use the old 10-digit numeric `username` pattern.
- Rescuer accounts follow one shared DB format only: `BDRRM-{TEAM_CODE}-###`, such as `BDRRM-SAR-001`.
- The sequence is per team code. Example: the first Medical / First Aid account is `BDRRM-MED-001`; the second SAR account is `BDRRM-SAR-002`.
- Rescuer mobile profile usernames are human-readable handles, such as `vinzon.arellano`. The BDRRM account ID remains the official account/login ID.
- New rescuer internal `user_id` values use the shared readable prefix:
  - `USR-RESCUER-BDRRM-SAR-001`
- Rescuer mobile login uses the BDRRM account ID:
  - `BDRRM-SAR-001`
  - `BDRRM-MED-001`
  - `BDRRM-SAR-002`
- Older temporary internal `user_id` values remain valid for development testing:
  - `USR-HQ-2024035500`
  - `USR-HH-2024035501`
  - `USR-HH-2024035503`
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
- [x] Latest additive DB update proposal reviewed by DB member
- [x] Shared DB gap review approved for current version 1 scope
- [x] Additive DB update applied to the shared database
- [x] Laravel migrations adjusted to the existing shared DB structure
- [x] Temporary HQ/Admin, household, and rescuer login accounts seeded
- [x] Shared React web components created
- [x] HQ/Admin dashboard API connected to live database data
- [x] Barangay Profile foundation for shared dashboard/weather/map focus
- [x] Disaster Broadcasting module backend and frontend
- [x] Weather Updates module with Open-Meteo auto snapshots and PAGASA advisory links
- [x] Mapping module with actual map and geotagged records
- [x] Household Status module backend and frontend
- [x] Rescue Dispatch module backend and frontend
- [x] Rescuer Accounts module backend and frontend
- [x] Resources & Requests module backend and frontend
- [x] Situation Reporting module backend and frontend
- [x] Archive module backend and frontend
- [x] Push notification sending and receiving foundation through OneSignal

Checklist note: checked module items mean the backend routes, frontend pages, and current build/lint/test checks are present. OneSignal is implemented for device registration and Laravel push sending; final push QA still requires a real Android/iOS development or production build with real OneSignal credentials.

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
- Do not restore default Laravel migrations that conflict with the shared DB structure.

## Key Documentation

- `docs/RESQPERATION_G10_STEP_BY_STEP_CHECKLIST.md`
- `docs/RESQPERATION_G10_DB_READINESS_CHECK.md`
- `docs/RESQPERATION_G10_SAFE_SAMPLE_SEED_PLAN.md`
- `docs/RESQPERATION_G10_DEVELOPMENT_READINESS_AUDIT.md`
- `docs/RESQPERATION_SYSTEM_TEST_AND_IMPROVEMENT_NOTES.md`
- `docs/RESQPERATION_G10_BEGINNER_MODULE_PROMPTS.md`
- `docs/RESQPERATION_BARANGAY_PROFILE_SCOPE.md`
- `docs/sql_proposals/2026_06_01_g10_all_sql_additive_update.sql`
- `docs/sql_proposals/2026_06_01_g10_existing_db_gap_review.sql` - superseded historical proposal
- `docs/sql_proposals/initial/2026_06_03_g10_barangay_profile_review.sql` - review-only Barangay Profile proposal

- `docs/DOCUMENTATION_INDEX.md` - start here for all project documentation.
- `docs/RESQPERATION_FINAL_DEFENSE_STUDY_GUIDE_AND_DIAGRAMS.md` - final-defense study guide and system diagrams.
- `docs/RESQPERATION_EXTERNAL_SYSTEM_INTEGRATION_DRAFT.md` - SafeTrack, EvaTrack, and TrackingAid/MappingAid integration draft.
- `docs/RESQPERATION_TRACKINGAID_REQUEST_INTEGRATION_GUIDE.md` - complete EvaTrack intake and TrackingAid request handoff guide.
- `docs/RESQPERATION_ONESIGNAL_MOBILE_SETUP.md` - OneSignal mobile setup and token registration flow.
- `docs/RESQPERATION_SYSTEM_CLEANUP_AND_CURRENT_STATE.md` - latest cleanup notes and current system status.
- `docs/RESQPERATION_G10_STEP_BY_STEP_CHECKLIST.md` - current build checklist.
- `docs/RESQPERATION_DB_CONNECTION_SWITCH_GUIDE.md` - shared MySQL connection guide using only `backend-laravel/.env`.
- `docs/sql_proposals/` - review-only SQL proposals.

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
php artisan serve --host=0.0.0.0 --port=8000
php artisan test
php artisan route:list
php artisan config:clear
php artisan weather:refresh
php artisan schedule:list
php artisan schedule:run
```

Weather update note:

- `weather:refresh` saves the latest Open-Meteo snapshot to `weather_logs`.
- Laravel Scheduler runs `weather:refresh` every 3 hours.
- On a real deployment machine, configure the server scheduler or Windows Task Scheduler to run `php artisan schedule:run` every minute.
- PAGASA remains the official warning confirmation source before HQ/Admin broadcasts.

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
npm run start:go
npm run start:dev
npx tsc --noEmit
npm run lint
```

Mobile command notes:

- `npm run start:go` runs ordinary Expo Go UI testing.
- `npm run start:dev` runs Metro for an installed development build.
- OneSignal and native maps require a development or production build, not plain Expo Go.

Mobile API connection:

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.112.109:8000/api/v1
```

For Expo Go on Android/iPhone, the Laravel backend must run with `--host=0.0.0.0` so the phone can reach the API through the laptop Wi-Fi IP.

Use the QR code shown by `npm start` and scan it with Expo Go on the actual phone. Emulator commands are optional and are not required for normal mobile testing.

Local development URLs:

```text
Web app: http://127.0.0.1:5175/login
Backend API: http://127.0.0.1:8000/api/v1
Mobile API: http://192.168.112.109:8000/api/v1
```


Local:   http://localhost:5175/
Local:   http://127.0.0.1:5175/
Network: http://192.168.112.109:5175/
VPN:     http://100.113.172.151:5175/

Local:   http://localhost:5175/login
Network: http://192.168.112.109:5175/login
