# RESQPERATION APK: First-Run Server Setup

Use the standalone APK for household and rescuer accounts. Expo Go, QR scanning,
and Metro are not required.

## 1. Start the server and database

The backend computer must be awake, MySQL must be running, and Laravel must be
able to connect to the selected database. Keep the existing
`backend-laravel/.env`; do not overwrite it or recreate the database.

The backend's `DB_CONNECTION` setting selects `resq_local` or `shared_resq`.
For a database change, update that setting and its corresponding credentials
in the backend `.env`, then run:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan config:clear
php artisan config:cache
php artisan db:show
```

Verify the intended database is shown before restarting Laravel, queue
workers, and the scheduler. Do not run migrations as part of switching
connections. Database credentials belong only in the backend `.env`.

The project manages Laravel API startup with the Windows
`RESQPERATION Mobile Api` scheduled task. Ngrok is separate; install its
per-user startup entry once from PowerShell under the Windows account that is
signed in to ngrok:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\install-ngrok-tunnel.ps1
```

The server computer, Laravel API, database, and ngrok tunnel must all be
running for the ngrok route to work.

## 2. Configure automatic API routes

New builds can probe the primary URL and each comma-separated URL configured
in `EXPO_PUBLIC_API_FALLBACK_URLS`. A saved **Server settings** URL is tried
first; configured routes are still used as fallbacks. For release builds, all
routes must use HTTPS and end in `/api/v1`.

Set these build-time values before creating the APK:

```text
EXPO_PUBLIC_API_BASE_URL=https://sturdily-morse-sitter.ngrok-free.dev/api/v1
EXPO_PUBLIC_API_FALLBACK_URLS=https://<server-tailnet-fqdn>/api/v1,https://<lan-api-hostname>/api/v1
```

Only configure a fallback after it has a valid HTTPS certificate trusted by
Android and its `/health/ready` endpoint returns `{"status":"ready"}` on the
phones. Tailscale Serve is currently disabled by the tailnet administrator,
so the existing Tailscale hostname is not a working fallback until that
administrator enables and verifies it. A same-Wi-Fi route needs a stable
hostname and trusted HTTPS reverse proxy; plain LAN HTTP is rejected by release
APKs. See [TAILSCALE_LAN.md](./TAILSCALE_LAN.md) for route requirements and
database selection details.

Every configured API route must reach the same Laravel backend and database.
The API URLs do not change databases, and MySQL must never be exposed through
ngrok, Tailscale, or the LAN proxy. The current APK may contain only the routes
that were configured when it was built; rebuild after changing build-time
URLs.

## 3. Verify from the phone

For each configured HTTPS route, open this in the phone browser:

```text
https://<api-host>/api/v1/health/ready
```

Expected response:

```json
{"status":"ready"}
```

This verifies the phone can reach that API route and that Laravel can reach its
selected database. A health check does not verify login credentials or
household/rescuer overview access. Open the APK and sign in to confirm those
separately.

## OneSignal requirements

- The phone must allow notifications and have the required Google Play
  Services.
- The phone and backend need internet access for push delivery, even when API
  access uses a private network.
- Keep the notification worker and scheduler running for queued and scheduled
  work.
- Validate notification delivery on a test phone before broad distribution.

## Troubleshooting

| Problem | Check |
| --- | --- |
| No API route is reachable | Check each route's HTTPS certificate, phone connectivity, API process, and `/health/ready` response. |
| Health endpoint reports a database error | Verify `DB_CONNECTION` and its host/database credentials in the backend `.env`; run `php artisan db:show`. |
| Tailscale hostname does not work | The tailnet administrator must enable Tailscale Serve or provide another tested HTTPS endpoint. |
| A local Wi-Fi route does not work | Use a stable LAN hostname with trusted HTTPS; release APKs do not allow plain HTTP. |
| A saved server does not work | End and sync active trips first, save a working HTTPS URL under **Server settings**, then sign in again. |
| Notifications do not arrive | Check Android permission, internet access, OneSignal/FCM configuration, worker, and scheduler. |
| API stops working when the computer sleeps | Keep the backend computer awake while it acts as the server. |
