# Automatic API connection fallback

New APK builds try these API routes automatically, in order:

1. Ngrok: `https://sturdily-morse-sitter.ngrok-free.dev/api/v1`
2. Tailscale: `http://100.121.155.55:8000/api/v1`
3. Current Wi-Fi LAN: `http://10.90.159.87:8000/api/v1`

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
also be running. The app probes each
route's `/health/ready` endpoint, which checks that Laravel can reach its
configured database. The working route is selected for subsequent API calls.
If a safe read request loses its connection, the app checks the next route and
retries it once. Writes are not automatically replayed after a connection loss,
to prevent duplicate reports, status changes, or requests; the next app request
will use the newly selected route.

All routes **must reach the same Laravel backend and database**. These URLs do
not switch or select the database. Laravel's `backend-laravel/.env` determines
the database connection. For group members without Tailscale access, ngrok can
forward to the backend computer; that backend computer must itself have access
to the shared database (for example through its own authorized Tailscale
connection). Never expose the database port through ngrok.

The ngrok address above is the project's previously configured static domain;
it works only while the ngrok tunnel is started and the account allows it.
If the ngrok domain, Tailscale IP, or LAN IP changes, update
`EXPO_PUBLIC_API_FALLBACK_URLS` in `frontend-mobile/eas.json` and build a new
APK. The saved **Server settings** address remains an additional route for an
existing APK.

Tailscale: install Tailscale on the phone and sign in to the same authorized
tailnet as this computer (or have its administrator grant access). Keep it
connected. The devices can use different Wi-Fi/mobile networks. Access policies
must permit this server's TCP port 8000.

LAN: connect both devices to the same network. Tailscale is not required. The
LAN address can change when the computer moves to another Wi-Fi; update Server
settings to `http://<computer-current-LAN-IP>:8000/api/v1` when that happens.
Do not use `localhost`, `127.0.0.1`, or `0.0.0.0` as the address on the phone.

The backend computer, database, and API must remain running. Start Laravel with:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
$env:APP_DEBUG = 'false'
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

Laravel should bind to `0.0.0.0` to accept the Tailscale and LAN interfaces.
Changing a manually saved server address requires ending/syncing pending trips
first and signing in again.

To check any API route from the phone browser, append `/health/ready` to its
base address. It should show `{"status":"ready"}`. If the LAN route times out,
check Wi-Fi client isolation and Windows Firewall's inbound rules for PHP/TCP
8000. The workspace cannot verify a phone's network or firewall path.

OneSignal remains configured and independent of the API transport. The phone
and backend need internet access for push delivery, even when API access is LAN.
Tailscale encrypts its network path; plain LAN HTTP should be used only on a
trusted local network.
