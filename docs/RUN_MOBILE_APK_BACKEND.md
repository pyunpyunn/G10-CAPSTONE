# Run the mobile APK backend

The backend on this computer is now configured to run automatically for APK Build 20. **You no longer need to open terminals for normal use.** Keep the server computer powered on, awake and connected to the network; connect the phone to Tailscale and open the app.

## Automatic startup

Windows Task Scheduler contains two background tasks:

- **RESQPERATION Mobile Api** — serves the Laravel API on port 8000.
- **RESQPERATION Mobile Queue** — processes broadcasts, operations and navigation jobs.

They run under the built-in **LOCAL SERVICE** account at Windows startup (with a 20-second delay) and at logon. A supervisor restarts an exited PHP process after five seconds. Windows also retries a failed supervisor task after one minute. Duplicate task instances are ignored.

MySQL95 and Tailscale start automatically. MySQL crash recovery is configured, and Tailscale unattended mode keeps the connection available after Windows logout. The stopped MySQL80 service is not used by this setup.

The web dashboard also starts automatically on port 5175 through **RESQPERATION Web Dashboard**. See [the web dashboard guide](RUN_WEB_DASHBOARD.md). The status command below checks all three tasks.

API readiness and automatic API/queue recovery were tested successfully on 23 October 2026. A full computer reboot was not performed during setup.

### Check the backend status

Run in PowerShell if you need to check it:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\get-mobile-backend-status.ps1
```

Look for `api_ready: true`, task state `Running` and `process_running: true` for both tasks.

### Logs

```text
C:\backend\G10CAPSTONE\resqperation-system\backend-laravel\storage\logs\mobile-backend
```

This folder contains API/queue output, errors and supervisor restart history. The status command prints the current log filenames. The installer transcript is `storage\logs\mobile-backend-install.log`.

### After updating backend code

The API loads PHP code on subsequent requests. To make the persistent queue worker load changed code, request a graceful restart; its supervisor starts it again automatically:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan queue:restart
```

### Reinstall the automatic setup

If the project or PHP paths change, update the setup as needed, then run this once in **PowerShell as administrator**:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\install-mobile-backend.ps1 -TestRecovery
```

## Manual fallback commands

Use the commands below only if the automatic tasks have been stopped/disabled. **Do not start duplicate servers or workers while the automatic tasks are running.** In manual mode, keep both terminal windows open.

### 1. Start the Laravel API server

Open PowerShell and run:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
$env:APP_DEBUG = 'false'
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

### 2. Start the queue worker

Open a second PowerShell terminal and run:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan queue:work operations_outbox --queue=broadcasts,operations,navigation --sleep=1 --tries=3 --timeout=120
```

## Server API address in the mobile app

On the login page, open **Server settings**. Enter this exact **Server API Address**, then tap **Save server address**:

```text
http://100.121.155.55:8000/api/v1
```

Tailscale must be connected on both the server computer and the phone, with access permitted between them. The database configured for Laravel must also be running.

## Check the connection

On the phone, open this address in Chrome:

```text
http://100.121.155.55:8000/api/v1/health/ready
```

If it cannot open or times out, check Tailscale connectivity, the running Laravel server and Windows Firewall access to TCP port 8000 before trying to sign in again.

## Download the APK

With Tailscale connected, open this page and tap **Download APK**:

```text
http://100.121.155.55:8000/downloads/index.html
```

The installed release APK does not require Expo or Metro to run. It requires the Laravel API for sign-in and live rescue data. Installing the APK does not start the backend automatically.
