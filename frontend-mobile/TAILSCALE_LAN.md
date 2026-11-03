# API routes and database switching

## Web dashboard access

The Windows setup runs the Laravel API and web dashboard automatically. The web
dashboard uses port `5175` and can be opened from the server PC, the same
private LAN, or Tailscale after its Windows startup installer is repaired:

- This PC: `http://localhost:5175`
- Same private LAN: `http://<server-lan-ip>:5175`
- Tailscale: `http://<server-tailscale-ip>:5175`

The web server proxies API requests through Laravel on the same PC. Its
firewall rules allow Tailscale addresses and, separately, the local subnet only
when the Windows network profile is Private. This is HTTP, so use it only on a
trusted private LAN or Tailscale network; browser push notifications require a
secure origin.

## Mobile API route

The current standalone APK (build 61) uses only this public HTTPS API:

`https://sturdily-morse-sitter.ngrok-free.dev/api/v1`

Phones can use it over mobile data or other internet access; they do not need
to join the laptop's Wi-Fi or Tailscale network. Android in the app uses
Cloudflare or Google DNS over HTTPS to resolve this host if the phone's normal
DNS returns no result; it keeps HTTPS hostname certificate validation. This
does not change DNS behavior in browsers. The APK ignores saved **Server
settings** addresses and does not have LAN or Tailscale fallbacks configured. Open
`https://sturdily-morse-sitter.ngrok-free.dev/api/v1/health/ready` in the
phone's browser; `{"status":"ready"}` confirms that the public route can reach
Laravel and its selected database.

Ngrok forwards only to Laravel on `127.0.0.1:8000`; it does not publish the
web dashboard or MySQL. Do not expose or forward the MySQL port.

The Laravel API is already managed by the Windows `RESQPERATION Mobile Api`
scheduled task. The web dashboard has a separate Windows task. Install or
repair it once from an elevated PowerShell:

```powershell
& 'C:\backend\G10CAPSTONE\resqperation-system\frontend-web\scripts\windows\install-web-dashboard.ps1'
```

This registers the dashboard for Windows boot and user logon, starts it, and
allows port `5175` from Tailscale or the private LAN. Keep the Windows LAN
network profile set to **Private**. To install or repair the mobile API and
queue startup tasks, use `backend-laravel\scripts\windows\install-mobile-backend.ps1`
from elevated PowerShell.

Ngrok runs under the Windows account configured for Ngrok. Its scheduled task
starts and supervises the tunnel at sign-in, even on battery. Install or repair
that task once under the same Windows account:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\install-ngrok-tunnel.ps1
```

The task uses that account's existing Ngrok configuration. The computer must
be awake, connected to the internet, and signed in to Windows; the Laravel API
and database must also be running. After the task is installed, no terminal
needs to stay open. The Ngrok address is the project's configured domain; it
works only while the tunnel is running and the account allows it. It is not a
substitute for an organization-controlled production API domain. Never put
secrets in public Expo build variables.

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

The backend computer, database, and API must remain running. Windows
`RESQPERATION Mobile Api` and `RESQPERATION Mobile Queue` tasks manage the API
and worker; Ngrok is managed by `RESQPERATION Ngrok Tunnel`. To check an HTTPS
route from a phone, append `/health/ready` to its base address. It should show
`{"status":"ready"}`. The workspace cannot verify a phone's network path or
certificate trust.

OneSignal remains configured and independent of the API transport. The phone
and backend need internet access for push delivery.
