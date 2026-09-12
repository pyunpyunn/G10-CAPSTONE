# RESQPERATION Shared DB Connection Guide

Use this guide when connecting RESQPERATION to the shared MySQL database.

## Main Rule

Only `backend-laravel/.env` should change when connecting to the shared database.

Do not edit PHP files, React files, controllers, services, or frontend API files just to change databases.

The web app must show only records from the database currently selected in `.env`.
Do not use old local records, prototype rows, or generated seed data as visible fallback data.

Related rule document:

```text
docs/RESQPERATION_SHARED_DB_DATA_SOURCE_RULES.md
```

After editing `.env`, always clear Laravel config and restart the backend:

```bash
cd backend-laravel
php artisan config:clear
php artisan cache:clear
php artisan serve --host=0.0.0.0 --port=8000
```

## Required Shared DB Settings

Keep these development-safe settings:

```env
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

This prevents Laravel sessions, queue jobs, and cache from depending on database support tables during development.

## Connecting To The Shared DB

When the DB member's laptop/server is online:

1. Open `backend-laravel/.env`.
2. Replace only the DB section:

```env
DB_CONNECTION=mysql
DB_HOST=shared-db-ip-address
DB_PORT=3306
DB_DATABASE=shared-db-name
DB_USERNAME=shared-db-username
DB_PASSWORD=shared-db-password
DB_CONNECTION_TIMEOUT=5
```

3. Keep:

```env
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

4. Clear config and restart Laravel:

```bash
cd backend-laravel
php artisan config:clear
php artisan cache:clear
php artisan serve --host=0.0.0.0 --port=8000
```

5. Open:

```text
http://127.0.0.1:5175/login
```

6. Log in again after changing the database connection.

Laravel Sanctum tokens are stored in the selected database. If `.env` changes, the old browser/mobile token may no longer match the active database.

If the browser keeps using an old token, open DevTools Console and run:

```js
localStorage.removeItem('resqperation_web_token')
```

Then refresh the page and log in again.

## Important Schema Requirement

Changing `.env` only changes which database Laravel connects to.

For all pages to load, the target database must also contain the RESQPERATION app-compatible tables used by the backend, such as:

- `users`
- `roles`
- `disaster_events`
- `disaster_broadcasts`
- `weather_logs`
- `households`
- `household_members`
- `household_status_logs`
- `rescue_teams`
- `responders`
- `responder_assignments`
- `resource_requests`
- `situation_reports`
- `incident_archives`
- `notifications`

If the target DB only has imported raw tables like `disasterevent`, `household`, `responder`, or `rescueteam`, document the missing table or column first and coordinate with the DB member before applying any additive SQL.

## Check If The DB Server Is Reachable

PowerShell:

```powershell
Test-NetConnection shared-db-ip-address -Port 3306
```

Expected:

```text
TcpTestSucceeded : True
```

If it is `False`, the issue is network/server access, not Laravel code.

Also check that both laptops are on the same reachable network.

```powershell
ipconfig
ping shared-db-ip-address
```

Example diagnosis:

- Your laptop Wi-Fi IPv4: `192.168.1.44`
- Entered DB host: `192.168.112.68`
- Result: unreachable unless there is a router/VPN route between `192.168.1.x` and `192.168.112.x`

In that case, ask the DB member to run `ipconfig` again and give the current Wi-Fi IPv4 address. If your laptop is `192.168.1.x`, the DB host will usually also be `192.168.1.x`.

The shared DB computer must also allow MySQL remote connections:

- MySQL service is running.
- MySQL is listening on port `3306`.
- Windows Firewall allows inbound TCP `3306`.
- The MySQL user is allowed to connect from another laptop, not only `localhost`.

## Check If Laravel Can Connect

After updating `.env`:

```bash
cd backend-laravel
php artisan tinker --execute="DB::connection()->getPdo(); echo DB::connection()->getDatabaseName();"
```

Expected output should be the database name you placed in `.env`.

## Check If Main Pages Can Load

Use the web app after starting both servers:

```bash
cd frontend-web
npm run dev -- --host 0.0.0.0 --port 5175
```

Then open:

```text
http://127.0.0.1:5175/login
```

If login works but pages show "cannot be loaded", the DB connection is working but the target DB is missing app-compatible tables or columns.

## Do Not Do These

- Do not commit `.env`.
- Do not run `php artisan migrate:fresh` on the shared DB.
- Do not delete DB member tables.
- Do not change backend/frontend code just to switch DB connection.
- Do not paste the shared DB password in public documentation.
- Do not run broad seeders on the shared DB. Use explicit seeder classes only after checking the active database name.
