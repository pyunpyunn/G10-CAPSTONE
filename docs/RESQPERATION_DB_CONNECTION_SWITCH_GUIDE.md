# RESQPERATION DB Connection Switch Guide

Use this guide when the shared MySQL database laptop is offline, then switch back when it is available again.

## Current Mode: Shared MySQL Connected

The backend is currently connected to the shared MySQL database.

Current backend settings in `backend-laravel/.env`:

```env
DB_CONNECTION=mysql
DB_HOST=192.168.112.68
DB_PORT=3306
DB_DATABASE=klint
DB_USERNAME=groupmate
DB_PASSWORD=check backend-laravel/.env
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

Why `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, and `CACHE_STORE=file` stay like this:

- The app can use shared MySQL for system data.
- Laravel sessions/cache/queue do not depend on database support tables during development.
- If the shared DB becomes unreachable again, the app is less likely to timeout on session/cache operations.

## Run The System In Offline Mode

Backend:

```bash
cd backend-laravel
php artisan config:clear
php artisan cache:clear
php artisan serve --host=0.0.0.0 --port=8000
```

Frontend:

```bash
cd frontend-web
npm run dev -- --host 0.0.0.0 --port 5175
```

Open:

```text
http://127.0.0.1:5175/login
```

Temporary login:

```text
Account ID: 2024035500
Password: password
```

## If The Backend Still Shows A DB Timeout

Do this after editing `.env`:

```bash
cd backend-laravel
php artisan config:clear
php artisan cache:clear
```

Then stop and restart `php artisan serve`.

If the error still shows `mysql:host=192...`, Laravel is still using an old cached config or a server process that was started before the `.env` change.

## How To Reconnect To The Shared DB

Only do this when the DB member's laptop/server is online and reachable on the same network.

1. Open `backend-laravel/.env`.
2. Change the DB section back to MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=192.168.112.68
DB_PORT=3306
DB_DATABASE=klint
DB_USERNAME=groupmate
DB_PASSWORD=check backend-laravel/.env
```

3. If the shared database has Laravel support tables, switch these back:

```env
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

For safer local testing, you may keep:

```env
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

4. Clear config:

```bash
cd backend-laravel
php artisan config:clear
```

5. Restart the Laravel backend server.

## Check If The Shared DB Is Reachable

Run this in PowerShell:

```powershell
Test-NetConnection 192.168.112.68 -Port 3306
```

Expected result before reconnecting:

```text
TcpTestSucceeded : True
```

If it is `False`, do not reconnect yet. Laravel will timeout again.

## Important Notes

- Do not commit `.env`.
- Do not run `migrate:fresh` on the shared MySQL database.
- The offline SQLite database is only for local UI testing.
- Real shared data will only appear after reconnecting to the shared MySQL database.
