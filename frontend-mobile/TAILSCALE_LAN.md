# Secure API connection

Release APKs require HTTPS for every API route. The configured route is:

1. Ngrok: `https://sturdily-morse-sitter.ngrok-free.dev/api/v1`

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
also be running. The app probes configured `/health/ready` endpoints over
HTTPS, which checks that Laravel can reach its configured database. The working
route is selected for subsequent API calls. If a safe read request loses its
connection, the app checks the next configured route and retries it once.
Writes are not automatically replayed after a connection loss, to prevent
duplicate reports, status changes, or requests; the next app request will use
the newly selected route.

All routes **must reach the same Laravel backend and database**. These URLs do
not switch or select the database. Laravel's `backend-laravel/.env` determines
the database connection. For group members without Tailscale access, ngrok can
forward to the backend computer; that backend computer must itself have access
to the shared database (for example through its own authorized Tailscale
connection). Never expose the database port through ngrok.

The ngrok address above is the project's configured domain; it works only
while the tunnel is running and the account allows it. It is not a substitute
for an organization-controlled production API domain. Before public
deployment, configure `EXPO_PUBLIC_API_BASE_URL` in the EAS production
environment to a stable HTTPS API origin. Optional fallback URLs must also use
HTTPS and be configured in `EXPO_PUBLIC_API_FALLBACK_URLS`. Never put secrets
in these public build variables.

Saved **Server settings** addresses must use HTTPS in release APKs. HTTP is
accepted only in local development builds. A Tailscale or LAN API endpoint may
be used only when TLS is correctly configured for a certificate trusted by
the phone; network encryption alone does not secure HTTP against
application-layer interception or a compromised device.

The backend computer, database, and API must remain running. Start Laravel with:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
$env:APP_DEBUG = 'false'
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

The local API service binds to loopback so it is reachable by the local TLS
tunnel without exposing its HTTP listener on other network interfaces.
Changing a manually saved server address requires ending/syncing pending trips
first and signing in again.

To check an HTTPS API route from the phone browser, append `/health/ready` to
its base address. It should show `{"status":"ready"}`. The workspace cannot
verify the phone's network path or production TLS configuration.

OneSignal remains configured and independent of the API transport. The phone
and backend need internet access for push delivery.
