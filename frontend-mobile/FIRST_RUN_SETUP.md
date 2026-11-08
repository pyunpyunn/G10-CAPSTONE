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

The Windows `RESQPERATION Mobile Api` and `RESQPERATION Mobile Queue` tasks
start the backend automatically. The `RESQPERATION Ngrok Tunnel` task starts
and supervises Ngrok when the configured Windows account signs in. It uses
that account's existing Ngrok configuration; no terminal needs to stay open.
Keep the laptop awake, online, and signed in to Windows (locking the screen is
fine). The one-time Ngrok task setup is:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\install-ngrok-tunnel.ps1
```

The server computer, Laravel API, database, and ngrok tunnel must all be
running for the ngrok route to work.

This laptop already has the Ngrok task installed. Re-run the setup command only
if the task is missing or the Ngrok account/domain changes.

## 2. API route in the current APK

Build 68 tries the Ngrok HTTPS API route below first, followed by any other
configured release routes. A validated address saved under **Server settings**
is used only if those routes are unreachable. It does not need the phone to be
on the laptop's Wi-Fi or Tailscale. Android resolves Ngrok through
Cloudflare DNS-over-HTTPS, then Google DNS-over-HTTPS, using bootstrap IPs when
normal DNS cannot resolve the host. API TLS certificate checks remain enabled;
there is no need to change Private DNS:

```text
EXPO_PUBLIC_API_BASE_URL=https://sturdily-morse-sitter.ngrok-free.dev/api/v1
EXPO_PUBLIC_API_FALLBACK_URLS=
```

If the Ngrok domain changes, update the build configuration and create/install
a new APK. All API traffic goes through Laravel on the laptop; MySQL itself
must never be exposed through Ngrok, Tailscale, or the LAN.

## 3. Verify from the phone

Open this exact URL in the phone browser using mobile data or another internet
connection:

```text
https://sturdily-morse-sitter.ngrok-free.dev/api/v1/health/ready
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
| The phone browser cannot find the Ngrok hostname | Browser DNS is controlled by the phone's network; the app's HTTPS DNS fallback does not change browser behavior. Use build 68 or newer to use the fallback inside RESQPERATION. |
| The APK still cannot reach the API | Confirm build 68 or newer is installed, then retry after closing/reopening it. Make sure the laptop is awake, online, and signed in to Windows. You can save another reachable API URL under **Server settings** as a fallback. The health check proves API reachability, not the account password. |
| Health endpoint reports a database error | Verify `DB_CONNECTION` and its host/database credentials in the backend `.env`; run `php artisan db:show`. |
| Tailscale hostname does not work | The tailnet administrator must enable Tailscale Serve or provide another tested HTTPS endpoint. |
| A local Wi-Fi route does not work | Use a stable LAN hostname with trusted HTTPS; release APKs do not allow plain HTTP. |
| A saved server does not work | End and sync active trips first, save a working HTTPS URL under **Server settings**, then sign in again. |
| Notifications do not arrive | Check Android permission, internet access, OneSignal/FCM configuration, worker, and scheduler. |
| API stops working when the computer sleeps | Keep the backend computer awake while it acts as the server. |
