# API routes and database switching

Release APKs require HTTPS for every API route. The current production route is:

1. Ngrok: `https://sturdily-morse-sitter.ngrok-free.dev/api/v1`

The app checks the primary URL, then each comma-separated URL in
`EXPO_PUBLIC_API_FALLBACK_URLS`. A URL saved under **Server settings** is tried
first; the configured routes remain available as fallbacks. Read-only requests
can switch to another ready route after a connection loss. All routes must
reach the same Laravel backend and database, or users could see different data
after failover.
Login requests may retry only after a connection failure without an HTTP
response. Other writes are never automatically replayed.

To add the other routes for a release build, set the build environment variable
`EXPO_PUBLIC_API_FALLBACK_URLS` to a comma-separated list such as:

```text
https://<server-tailnet-fqdn>/api/v1,https://<lan-api-hostname>/api/v1
```

Only add endpoints after testing that each returns
`{"status":"ready"}` from `/health/ready` on the phones. The readiness check
also verifies that the API server can access its selected database.

- **Tailscale:** use an HTTPS endpoint provided by Tailscale Serve or an HTTPS
  reverse proxy with a phone-trusted certificate. Tailscale Serve is currently
  disabled by this tailnet's administrator, so the existing Tailscale hostname
  is not a working fallback until the administrator enables it and the endpoint
  passes the readiness check.
- **Same Wi-Fi/LAN:** use a stable hostname and HTTPS reverse proxy with a
  certificate trusted by Android. A changing private IP and plain HTTP are not
  suitable release routes. Do not weaken the APK's cleartext-traffic policy.
- **Ngrok:** keep the existing HTTPS tunnel running on the backend computer.

For every added hostname, add its hostname (not the full URL) to
`API_ALLOWED_HOSTS` in the backend `.env`, then refresh Laravel's cached
configuration and restart the API service. If a LAN route uses an IP address,
allow that exact reserved IP. Do not use wildcard host entries.

Current endpoint discovery found `https://mc15a.tail1e9a11.ts.net/api/v1` as
the machine's possible Tailscale URL, but there is no Serve configuration and
its HTTPS health check is refused. The current Wi-Fi IP is `10.159.187.107`,
but Laravel listens only on `127.0.0.1:8000` and there is no local HTTPS
listener. Neither address is a working release fallback today.

Do not expose MySQL or any database port through ngrok, Tailscale Serve, or the
LAN reverse proxy. Only publish the Laravel API.

The Laravel API is already managed by the Windows `RESQPERATION Mobile Api`
scheduled task. The ngrok tunnel is a separate process and must run on the
backend computer too. Install its per-user startup entry once from PowerShell
as the Windows account that has signed in to ngrok:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\install-ngrok-tunnel.ps1
```

This starts ngrok immediately, restarts it if it exits, and starts it again
when that Windows account signs in. The backend computer must be powered on,
connected to the internet, and signed in to Windows; the startup entry uses that
account's existing ngrok configuration. The Laravel API and its database must
also be running. The ngrok address is the project's configured domain; it works
only while the tunnel is running and the account allows it. It is not a substitute for an
organization-controlled production API domain. Never put secrets in public
Expo build variables.

## Select or switch the backend database

The mobile app and the ngrok/Tailscale/LAN URLs do not choose a database. The
Laravel server's `backend-laravel/.env` selects exactly one operational
database with `DB_CONNECTION`:

- `DB_CONNECTION=resq_local` uses the local database settings
  `RESQ_LOCAL_DB_HOST`, `RESQ_LOCAL_DB_DATABASE`, `RESQ_LOCAL_DB_USERNAME`,
  and `RESQ_LOCAL_DB_PASSWORD`.
- `DB_CONNECTION=shared_resq` uses `SHARED_DB_HOST`, `SHARED_DB_PORT`,
  `SHARED_DB_DATABASE`, `SHARED_DB_USERNAME`, and `SHARED_DB_PASSWORD`.

Keep the credentials in the backend `.env` only. To switch, update
`DB_CONNECTION` and the selected connection's settings, then run these commands
from the backend directory:

```powershell
php artisan config:clear
php artisan config:cache
php artisan db:show
```

Confirm `db:show` reports the intended database before restarting the Laravel
API service, queue workers, and scheduler. The `/health/ready` endpoint should
then return ready. Do not run migrations or data-merges as part of a connection
switch unless separately planned and backed up. Every API route configured in
the APK must point to a server using this same selected database.

The backend computer, database, and API must remain running. The current API
service binds to loopback for the ngrok tunnel. Start Laravel with:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
$env:APP_DEBUG = 'false'
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Changing a manually saved server address requires ending/syncing pending trips
first and signing in again. To check an HTTPS route from a phone, append
`/health/ready` to its base address. It should show `{"status":"ready"}`. The
workspace cannot verify a phone's network path or certificate trust.

OneSignal remains configured and independent of the API transport. The phone
and backend need internet access for push delivery.
